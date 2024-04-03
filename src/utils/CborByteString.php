<?php

namespace Beau\CborReduxPhp\utils;

class CborByteString
{
    private string $byteString;

    public function __construct(string $byteString)
    {
        $this->byteString = $byteString;
    }

    public function getByteString(): string
    {
        return $this->byteString;
    }
}