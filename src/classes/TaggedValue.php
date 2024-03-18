<?php

namespace Beau\CborReduxPhp\classes;

class TaggedValue
{
    public function __construct(
        public readonly int   $tag,
        public readonly mixed $value
    )
    {
    }
}