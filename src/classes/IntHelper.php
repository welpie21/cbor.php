<?php

namespace Beau\CborPHP\classes;

class IntHelper
{
    public static function getUint8(array $buffer, int $offset): int
    {
        return $buffer[$offset];
    }

    public static function getUint16(array $buffer, int $offset): int
    {
        return ($buffer[$offset] << 8) + $buffer[$offset + 1];
    }

    public static function getUint32(array $buffer, int $offset): int
    {
        return ($buffer[$offset] << 24) +
            ($buffer[$offset + 1] << 16) +
            ($buffer[$offset + 2] << 8) +
            $buffer[$offset + 3];
    }

    public static function getUint64(array $buffer, int $offset): int
    {
        return ($buffer[$offset] << 56) +
            ($buffer[$offset + 1] << 48) +
            ($buffer[$offset + 2] << 40) +
            ($buffer[$offset + 3] << 32) +
            ($buffer[$offset + 4] << 24) +
            ($buffer[$offset + 5] << 16) +
            ($buffer[$offset + 6] << 8) +
            $buffer[$offset + 7];
    }
}