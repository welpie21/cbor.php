<?php

namespace Beau\CborPHP\classes;

class FloatHelper
{
    public static function getFloat16(array $buffer, int $offset): float
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

        return pow(-1, $sign) * pow(2, $exp - 15) * (1 + $mant / 1024);
    }

    public static function getFloat32(array $buffer, int $offset): float
    {
        $int = IntHelper::getUint32($buffer, $offset);
        $sign = ($int >> 31) & 0x1;
        $exp = ($int >> 23) & 0xff;
        $mant = $int & 0x7fffff;

        if ($exp === 0) {
            return $sign ? -0.0 : 0.0;
        }

        if ($exp === 0xff) {
            return $mant ? NAN : INF;
        }

        return pow(-1, $sign) * pow(2, $exp - 127) * (1 + $mant / 8388608);
    }

    public static function getFloat64(array $buffer, int $offset): float
    {
        $int = IntHelper::getUint64($buffer, $offset);
        $sign = ($int >> 63) & 0x1;
        $exp = ($int >> 52) & 0x7ff;
        $mant = $int & 0xfffffffffffff;

        if ($exp === 0) {
            return $sign ? -0.0 : 0.0;
        }

        if ($exp === 0x7ff) {
            return $mant ? NAN : INF;
        }

        return pow(-1, $sign) * pow(2, $exp - 1023) * (1 + $mant / 4503599627370496);
    }
}