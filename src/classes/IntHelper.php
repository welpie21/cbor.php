<?php

namespace Beau\CborPHP\classes;

class IntHelper
{
    public static function getUint8(string $buffer, int $offset): int
    {
        return ord($buffer[$offset]);
    }

    public static function getUint16(string $buffer, int $offset): int
    {
        return (ord($buffer[$offset]) << 8) + ord($buffer[$offset + 1]);
    }

    public static function getUint32(string $buffer, int $offset): int
    {
        return (ord($buffer[$offset]) << 24) +
            (ord($buffer[$offset + 1]) << 16) +
            (ord($buffer[$offset + 2]) << 8) +
            ord($buffer[$offset + 3]);
    }

    public static function getUint64(string $buffer, int $offset): int
    {
        return (ord($buffer[$offset]) << 56) +
            (ord($buffer[$offset + 1]) << 48) +
            (ord($buffer[$offset + 2]) << 40) +
            (ord($buffer[$offset + 3]) << 32) +
            (ord($buffer[$offset + 4]) << 24) +
            (ord($buffer[$offset + 5]) << 16) +
            (ord($buffer[$offset + 6]) << 8) +
            ord($buffer[$offset + 7]);
    }
}