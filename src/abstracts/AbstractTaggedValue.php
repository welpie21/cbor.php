<?php

namespace Beau\CborPHP\abstracts;

abstract class AbstractTaggedValue
{
    public int $tag;
    public mixed $value;

    public function __construct(
        int $tag,
        mixed $value
    )
    {
        $this->tag = $tag;
        $this->value = $value;
    }
}