<?php

namespace Beau\CborReduxPhp\classes;

readonly class TaggedValue
{
    public function __construct(
        private int   $tag,
        private mixed $value
    )
    {
    }

    public function getTag(): int
    {
        return $this->tag;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}