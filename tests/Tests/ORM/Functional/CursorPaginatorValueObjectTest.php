<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Tools\Pagination\CursorPaginator;
use Doctrine\Tests\DbalTypes\BinaryCode;
use Doctrine\Tests\DbalTypes\BinaryCodeType;
use Doctrine\Tests\Models\Pagination\CodedItem;
use Doctrine\Tests\OrmFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_map;
use function base64_decode;
use function json_decode;
use function sprintf;
use function strtr;

class CursorPaginatorValueObjectTest extends OrmFunctionalTestCase
{
    private const DQL = 'SELECT i FROM Doctrine\Tests\Models\Pagination\CodedItem i ORDER BY i.code ASC';

    protected function setUp(): void
    {
        parent::setUp();

        if (Type::hasType(BinaryCodeType::NAME)) {
            Type::overrideType(BinaryCodeType::NAME, BinaryCodeType::class);
        } else {
            Type::addType(BinaryCodeType::NAME, BinaryCodeType::class);
        }

        $this->createSchemaForModels(CodedItem::class);

        // 0xff never appears in UTF-8, so none of the database values can be encoded as JSON
        for ($i = 5; $i >= 1; --$i) {
            $this->_em->persist(new CodedItem(new BinaryCode(sprintf('ff%06x', $i))));
        }

        $this->_em->flush();
        $this->_em->clear();
    }

    protected function tearDown(): void
    {
        $this->_em->getConnection()->executeStatement('DELETE FROM pagination_coded_item');

        parent::tearDown();
    }

    #[DataProvider('queryProducesDuplicates')]
    public function testEncodedCursorsNavigateOverValueObjectsStoredAsBinary(bool $queryProducesDuplicates): void
    {
        $query     = $this->_em->createQuery(self::DQL);
        $paginator = new CursorPaginator(2, $queryProducesDuplicates);

        $firstPage = $paginator->paginate($query);
        self::assertSame(['ff000001', 'ff000002'], self::codes($firstPage->getItems()));

        $secondPage = $paginator->paginate($query, $firstPage->getNextCursorAsString());
        self::assertSame(['ff000003', 'ff000004'], self::codes($secondPage->getItems()));

        $thirdPage = $paginator->paginate($query, $secondPage->getNextCursorAsString());
        self::assertSame(['ff000005'], self::codes($thirdPage->getItems()));
        self::assertFalse($thirdPage->hasNextPage());

        $previousPage = $paginator->paginate($query, $thirdPage->getPreviousCursorAsString());
        self::assertSame(['ff000003', 'ff000004'], self::codes($previousPage->getItems()));
    }

    /** @return iterable<string, array{bool}> */
    public static function queryProducesDuplicates(): iterable
    {
        yield 'plain query' => [false];
        yield 'query producing duplicates' => [true];
    }

    public function testEncodedCursorHoldsTheStringRepresentationOfValueObjects(): void
    {
        $firstPage = (new CursorPaginator(2, false))->paginate($this->_em->createQuery(self::DQL));

        self::assertSame(
            ['i.code' => 'ff000002', '_isNext' => true],
            json_decode((string) base64_decode(strtr($firstPage->getNextCursorAsString(), '-_', '+/')), true),
        );
    }

    /**
     * @param list<CodedItem> $items
     *
     * @return list<string>
     */
    private static function codes(array $items): array
    {
        return array_map(static fn (CodedItem $item): string => (string) $item->code, $items);
    }
}
