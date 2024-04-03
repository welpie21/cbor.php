<?php

namespace Beau\CborPHP\utils;

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