<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\Tests\Models\CMS\CmsUser;
use Doctrine\Tests\OrmFunctionalTestCase;

use function method_exists;
use function sprintf;

class QueryDqlUnaccentFunctionTest extends OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        $this->useModelSet('cms');

        parent::setUp();

        if (! method_exists(AbstractPlatform::class, 'getUnaccentExpression')) {
            self::markTestSkipped('UNACCENT() requires doctrine/dbal 4.6 or newer.');
        }

        if ($this->_em->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            try {
                $this->_em->getConnection()->executeStatement('CREATE EXTENSION IF NOT EXISTS unaccent');
            } catch (Exception $e) {
                self::markTestSkipped('The unaccent extension is not available: ' . $e->getMessage());
            }
        }

        foreach (['emile' => 'Émile Zola', 'zoe' => 'Zoë Ørsted', 'lech' => 'Lech Wałęsa'] as $username => $name) {
            $user           = new CmsUser();
            $user->status   = 'active';
            $user->username = $username;
            $user->name     = $name;

            $this->_em->persist($user);
        }

        $this->_em->flush();
        $this->_em->clear();
    }

    public function testUnaccentInSelectClause(): void
    {
        $result = $this->_em->createQuery(sprintf(
            'SELECT u.username, UNACCENT(u.name) AS name FROM %s u ORDER BY u.username',
            CmsUser::class,
        ))->getArrayResult();

        self::assertSame([
            ['username' => 'emile', 'name' => 'Emile Zola'],
            ['username' => 'lech', 'name' => 'Lech Walesa'],
            ['username' => 'zoe', 'name' => 'Zoe Orsted'],
        ], $result);
    }

    public function testUnaccentInWhereClause(): void
    {
        $usernames = $this->_em->createQuery(sprintf(
            'SELECT u.username FROM %s u WHERE LOWER(UNACCENT(u.name)) LIKE LOWER(UNACCENT(:name)) ORDER BY u.username',
            CmsUser::class,
        ))
            ->setParameter('name', '%wałesa%')
            ->getSingleColumnResult();

        self::assertSame(['lech'], $usernames);
    }
}
