<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\TestCase;

/**
 * Tests CBOR map (major type 5) encoding edge cases.
 *
 * PHP associative arrays (non-list arrays) are encoded as CBOR maps.
 * The pair count uses the same length thresholds as arrays.
 */
class CborMapBoundaryTest extends TestCase
{
    /**
     * @throws CborException
     */
    public function testMapMajorTypeIs5(): void
    {
        $encoded = CborEncoder::encode(["a" => 1]);
        $majorType = ord($encoded[0]) >> 5;
        $this->assertSame(5, $majorType);
    }

    /**
     * @throws CborException
     */
    public function testDecodeEmptyMapFromRawBytes(): void
    {
        // 0xa0 = CBOR empty map (major type 5, length 0)
        $decoded = CborDecoder::decode("\xa0");
        $this->assertSame([], $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeMapFromRawBytes(): void
    {
        // RFC 7049 Appendix A: {1: 2, 3: 4} → 0xa2 0x01 0x02 0x03 0x04
        $decoded = CborDecoder::decode("\xa2\x01\x02\x03\x04");
        $this->assertSame([1 => 2, 3 => 4], $decoded);
    }

    /**
     * @throws CborException
     */
    public function testMapWithIntegerKeys(): void
    {
        // PHP arrays with non-sequential integer keys are treated as maps
        $data = [2 => "a", 5 => "b", 10 => "c"];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals($data, $decoded);
        // Verify it's encoded as a map (major type 5)
        $this->assertSame(5, ord($encoded[0]) >> 5);
    }

    /**
     * @throws CborException
     */
    public function testMapWith23Keys(): void
    {
        // 23 pairs: max inline count
        $data = [];
        for ($i = 0; $i < 23; $i++) {
            $data["key_$i"] = $i;
        }
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals($data, $decoded);
        // First byte: 0xa0 | 23 = 0xb7
        $this->assertEquals("\xb7", $encoded[0]);
    }

    /**
     * @throws CborException
     */
    public function testMapWith24Keys(): void
    {
        // 24 pairs: needs extra uint8 for count
        $data = [];
        for ($i = 0; $i < 24; $i++) {
            $data["key_$i"] = $i;
        }
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals($data, $decoded);
        // First byte: 0xb8 (major type 5, additional info 24)
        $this->assertEquals("\xb8", $encoded[0]);
        // Second byte: 0x18 = 24
        $this->assertEquals("\x18", $encoded[1]);
    }

    /**
     * @throws CborException
     */
    public function testMapWith256Keys(): void
    {
        // 256 pairs: needs uint16 for count
        $data = [];
        for ($i = 0; $i < 256; $i++) {
            $data["k$i"] = $i;
        }
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals($data, $decoded);
        // First byte: 0xb9 (major type 5, additional info 25)
        $this->assertEquals("\xb9", $encoded[0]);
    }

    /**
     * @throws CborException
     */
    public function testMapWithNullValues(): void
    {
        $data = ["x" => null, "y" => null];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testMapWithBooleanValues(): void
    {
        $data = ["flag_a" => true, "flag_b" => false];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testMapWithFloatValues(): void
    {
        $data = ["pi" => 3.14159, "e" => 2.71828];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testMapWithNegativeIntegerValues(): void
    {
        $data = ["min" => -1000, "neg" => -1];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testMapKeyOrderIsPreserved(): void
    {
        $data = ["z" => 1, "a" => 2, "m" => 3];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        // PHP preserves insertion order, keys should match
        $this->assertSame(array_keys($data), array_keys($decoded));
        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testMapWithArrayValues(): void
    {
        $data = ["nums" => [1, 2, 3], "strs" => ["a", "b"]];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testMapWithNestedMapValues(): void
    {
        $data = ["outer" => ["inner" => ["deep" => 42]]];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testMapWithNumericStringKeys(): void
    {
        // PHP treats numeric string keys as integers in arrays,
        // so this will encode as a map with integer keys
        $data = ["0" => "zero", "1" => "one"];
        $encoded = CborEncoder::encode($data);
        // Both "0" and "1" are numeric strings; PHP will convert them to int keys,
        // making this a list array → encoded as CBOR array, not map
        $decoded = CborDecoder::decode($encoded);
        // The decoded values should match
        $this->assertEquals(array_values($data), $decoded);
    }

    /**
     * @throws CborException
     */
    public function testMapWithLongStringKeys(): void
    {
        $key = str_repeat("k", 100);
        $data = [$key => "value"];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals($data, $decoded);
    }
}
