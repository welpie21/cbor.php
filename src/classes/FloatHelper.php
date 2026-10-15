<?php

namespace Beau\CborPHP\classes;

class FloatHelper
{
    public static function getFloat16(string $buffer, int $offset): float
    {
        $half = IntHelper::getUint16($buffer, $offset);
        $sign = ($half >> 15) & 0x1 ? -1.0 : 1.0;
        $exp = ($half >> 10) & 0x1f;
        $mant = $half & 0x3ff;

        // Zero or subnormal: no implicit leading 1, exponent fixed at 2^-14.
        if ($exp === 0) {
            return $sign * $mant * 2 ** -24;
        }

        if ($exp === 0x1f) {
            return $mant ? NAN : $sign * INF;
        }

        // 2 ** n rather than 1 << n, since the exponent is negative for values below 1.
        return $sign * (1 + $mant / 1024) * 2 ** ($exp - 15);
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