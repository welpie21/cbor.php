<?php

namespace Beau\CborPHP\utils;

/**
 * A value the encoder writes as a CBOR map, whatever its keys.
 *
 * A PHP array cannot say that on its own: `[]` is written as an empty array,
 * and a list (keys `0, 1, 2, …`) as an array. PHP also turns a numeric string
 * key such as `"1"` into the int `1`, so a map whose keys are all text cannot
 * be kept in an array as it is. With `$stringKeys`, int keys are written as
 * text strings.
 */
class CborMap
{
    /**
     * @param array<int|string, mixed> $entries
     * @param bool $stringKeys write int keys as text strings (`1` as `"1"`)
     */
    public function __construct(
        public readonly array $entries = [],
        public readonly bool $stringKeys = false,
    ) {
    }
}
