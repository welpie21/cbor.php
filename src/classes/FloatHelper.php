<?php

namespace Beau\CborPHP\classes;

class FloatHelper
{
    public static function getFloat16(string $buffer, int $offset): float
    {
        $half = IntHelper::getUint16($buffer, $offset);
        $sign = ($half >> 15) & 0x1;
        $exp = ($half >> 10) & 0x1f;
        $mant = $half & 0x3ff;

        if ($exp === 0) {
            return $sign ? -0.0 : 0.0;
        }

        if ($exp === 0x1f) {
            return $mant ? NAN : INF;
        }

        return ($sign ? -1.0 : 1.0) * (1 << ($exp - 15)) * (1 + $mant / 1024);
    }

    public static function getFloat32(string $buffer, int $offset): float
    {
        return unpack("G", substr($buffer, $offset, 4))[1];
    }

    public static function getFloat64(string $buffer, int $offset): float
    {
        return unpack("E", substr($buffer, $offset, 8))[1];
    }
}