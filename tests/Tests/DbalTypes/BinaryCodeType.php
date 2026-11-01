<?php

declare(strict_types=1);

namespace Doctrine\Tests\DbalTypes;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\Mocks\CompatibilityType;

use function bin2hex;
use function ctype_xdigit;
use function hex2bin;
use function is_resource;
use function is_string;
use function stream_get_contents;
use function strlen;

/**
 * Stores a {@see BinaryCode} as the 4 bytes it encodes. Like Symfony's UID types, it also
 * accepts the string representation of the value object, in both directions.
 */
final class BinaryCodeType extends Type
{
    use CompatibilityType;

    public const NAME = 'binary_code';

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string|null
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BinaryCode) {
            $value = $value->hex;
        }

        if (! is_string($value) || strlen($value) !== 8 || ! ctype_xdigit($value)) {
            throw new ConversionException('Expected a BinaryCode or its string representation.');
        }

        return (string) hex2bin($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): BinaryCode|null
    {
        // DBAL 3 may hydrate binary columns as a stream
        if (is_resource($value)) {
            $value = (string) stream_get_contents($value);
        }

        if ($value === null || $value instanceof BinaryCode) {
            return $value;
        }

        if (is_string($value) && strlen($value) === 4) {
            return new BinaryCode(bin2hex($value));
        }

        if (is_string($value) && strlen($value) === 8 && ctype_xdigit($value)) {
            return new BinaryCode($value);
        }

        throw new ConversionException('Expected 4 bytes or the string representation of a BinaryCode.');
    }

    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBinaryTypeDeclarationSQL(['length' => 4, 'fixed' => true]);
    }

    private function doGetBindingType(): ParameterType|int
    {
        return ParameterType::BINARY;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
