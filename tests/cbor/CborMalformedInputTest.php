<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the decoder throws CborException for malformed input instead of
 * emitting PHP warnings or returning partial data.
 *
 * - Truncated input: the data ends before the item (or its argument) is complete.
 * - Indefinite-length items (additional info 31) are not supported.
 * - Additional info 28–30 is reserved by RFC 8949 and is not well-formed.
 * - An 8-byte argument above PHP_INT_MAX does not fit in a PHP int, whether it
 *   is an integer, a string/array/map length or a tag number.
 */
class CborMalformedInputTest extends TestCase
{
    public static function truncatedInputProvider(): array
    {
        return [
            'empty input' => [""],
            'uint8 argument missing' => ["18"],
            'uint16 argument missing' => ["19"],
            'uint16 argument partial' => ["1901"],
            'uint32 argument partial' => ["1a000001"],
            'uint64 argument partial' => ["1b00000000000001"],
            'negative int argument missing' => ["38"],
            'text string length missing' => ["78"],
            'text string content partial' => ["6261"],
            'byte string content partial' => ["430102"],
            'array element missing' => ["8201"],
            'map key missing' => ["a1"],
            'map value missing' => ["a16161"],
            'tag content missing' => ["c1"],
            'tag argument missing' => ["d8"],
            'float16 partial' => ["f900"],
            'float32 partial' => ["fa0000"],
            'float64 partial' => ["fb00000000"],
        ];
    }

    /**
     * @throws CborException
     */
    #[DataProvider('truncatedInputProvider')]
    public function testTruncatedInputThrows(string $hex): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage("Unexpected end of data");
        CborDecoder::decode(hex2bin($hex));
    }

    public static function indefiniteLengthProvider(): array
    {
        return [
            'indefinite byte string' => ["5f4101ff"],
            'indefinite text string' => ["7f6161ff"],
            'indefinite array' => ["9f01ff"],
            'indefinite map' => ["bf616101ff"],
        ];
    }

    /**
     * @throws CborException
     */
    #[DataProvider('indefiniteLengthProvider')]
    public function testIndefiniteLengthItemThrows(string $hex): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage("Indefinite-length items are not supported");
        CborDecoder::decode(hex2bin($hex));
    }

    public static function reservedAdditionalInfoProvider(): array
    {
        return [
            'unsigned int, additional 28' => ["1c", 28],
            'negative int, additional 29' => ["3d", 29],
            'array, additional 30' => ["9e", 30],
            'tag, additional 28' => ["dc", 28],
        ];
    }

    /**
     * @throws CborException
     */
    #[DataProvider('reservedAdditionalInfoProvider')]
    public function testReservedAdditionalInfoThrows(string $hex, int $additional): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage("Invalid additional information: $additional");
        CborDecoder::decode(hex2bin($hex));
    }

    public static function argumentOutOfRangeProvider(): array
    {
        return [
            'unsigned int 2^63' => ["1b8000000000000000"],
            'unsigned int 2^64-1' => ["1bffffffffffffffff"],
            'negative int -2^63-1' => ["3b8000000000000000"],
            'negative int -2^64' => ["3bffffffffffffffff"],
            'byte string length 2^64-1' => ["5bffffffffffffffff"],
            'text string length 2^63' => ["7b8000000000000000"],
            'array length 2^64-1' => ["9bffffffffffffffff"],
            'map length 2^63' => ["bb8000000000000000"],
            'tag number 2^64-1' => ["dbffffffffffffffff00"],
        ];
    }

    /**
     * @throws CborException
     */
    #[DataProvider('argumentOutOfRangeProvider')]
    public function testArgumentAbovePhpIntMaxThrows(string $hex): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage("Argument out of range at offset 1");
        CborDecoder::decode(hex2bin($hex));
    }

    public static function largestArgumentProvider(): array
    {
        return [
            'unsigned int PHP_INT_MAX' => ["1b7fffffffffffffff", PHP_INT_MAX],
            'negative int PHP_INT_MIN' => ["3b7fffffffffffffff", PHP_INT_MIN],
        ];
    }

    /**
     * @throws CborException
     */
    #[DataProvider('largestArgumentProvider')]
    public function testLargestArgumentStillDecodes(string $hex, int $expected): void
    {
        $this->assertSame($expected, CborDecoder::decode(hex2bin($hex)));
    }
}
