<?php

namespace Beau\CborReduxPhp\classes;

use Beau\CborReduxPhp\enums\Semantic;
use InvalidArgumentException;

readonly class SimpleValue
{
    private Semantic $semantic;

    public function __construct(
        private int $value
    )
    {
        $this->semantic = match (true) {
            $value === 20 => Semantic::FALSE,
            $value === 21 => Semantic::TRUE,
            $value === 22 => Semantic::NULL,
            $value === 23 => Semantic::UNDEFINED,
            $value > 23 && $value < 32 => Semantic::RESERVED,
            default => Semantic::UNASSIGNED
        };
    }

    public function toPrimitive(): bool|null
    {
        return match ($this->semantic) {
            Semantic::FALSE => false,
            Semantic::TRUE => true,
            default => null
        };
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function getSemantic(): Semantic
    {
        return $this->semantic;
    }

    /**
     * @param bool|int|null $value
     * @return SimpleValue
     * @throws InvalidArgumentException
     */
    public static function create(bool|int|null $value): SimpleValue
    {
        return match (true) {
            $value === false => new SimpleValue(20),
            $value === true => new SimpleValue(21),
            $value === null => new SimpleValue(22),
            empty($value) => new SimpleValue(23),
            is_int($value) && $value >= 0 && $value < 256 => new SimpleValue($value),
            default => throw new InvalidArgumentException("Invalid simple value")
        };
    }
}