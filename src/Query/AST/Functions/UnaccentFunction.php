<?php

declare(strict_types=1);

namespace Doctrine\ORM\Query\AST\Functions;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

use function assert;
use function method_exists;

/**
 * "UNACCENT" "(" StringPrimary ")"
 *
 * Removes the diacritics of the letters of a string, like unaccent() of PostgreSQL.
 */
class UnaccentFunction extends FunctionNode
{
    public Node $stringPrimary;

    public function getSql(SqlWalker $sqlWalker): string
    {
        $platform = $sqlWalker->getConnection()->getDatabasePlatform();
        assert(method_exists($platform, 'getUnaccentExpression'));

        return $platform->getUnaccentExpression($sqlWalker->walkStringPrimary($this->stringPrimary));
    }

    public function parse(Parser $parser): void
    {
        if (! method_exists(AbstractPlatform::class, 'getUnaccentExpression')) {
            $parser->semanticalError('UNACCENT() requires doctrine/dbal 4.6 or newer.');
        }

        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->stringPrimary = $parser->StringPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
