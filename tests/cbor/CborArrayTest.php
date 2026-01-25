<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\TestCase;

class CborArrayTest extends TestCase
{
    /**
     * Test that an empty PHP array [] is encoded as a CBOR array (0x80).
     *
     * CBOR major types:
     * - 0x80 = array with 0 items (major type 4, length 0)
     */
    public function testEmptyArrayEncodesAsCborArray(): void
    {
        $emptyArray = [];

        $encoded = CborEncoder::encode($emptyArray);

        // Empty CBOR array should be exactly one byte: 0x80
        $this->assertEquals(
            "\x80",
            $encoded,
            "Empty PHP array [] should encode as CBOR array (0x80)",
        );
    }

    /**
     * Test roundtrip: empty array should remain an empty array after encode/decode.
     */
    public function testEmptyArrayRoundtrip(): void
    {
        $emptyArray = [];

        $encoded = CborEncoder::encode($emptyArray);
        $decoded = CborDecoder::decode($encoded);

        $this->assertIsArray($decoded);
        $this->assertEmpty($decoded);
        $this->assertSame([], $decoded);
    }

    /**
     * Test that an empty array encodes with the same CBOR major type as other sequential arrays.
     */
    public function testEmptyArrayUsesSameMajorTypeAsSequentialArrays(): void
    {
        $emptyArray = [];
        $sequentialArray = [1, 2, 3];

        $emptyEncoded = CborEncoder::encode($emptyArray);
        $sequentialEncoded = CborEncoder::encode($sequentialArray);

        // Extract the major type (high 3 bits) from the first byte
        $emptyMajorType = ord($emptyEncoded[0]) >> 5;
        $sequentialMajorType = ord($sequentialEncoded[0]) >> 5;

        $this->assertEquals(
            $sequentialMajorType,
            $emptyMajorType,
            "Empty array should encode with the same CBOR major type as other sequential arrays",
        );
    }

    public function testArrayOfNumbers(): void
    {
        $data = [-100, -10, 100, 10];

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    public function testArrayOfStrings(): void
    {
        $data = ["apple", "banana", "cherry", "date"];

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    public function testArrayOfArrays(): void
    {
        $data = [[1, 2, 3], [4, 5, 6], [7, 8, 9], [10, 11, 12]];

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    public function testArrayOfMaps(): void
    {
        $data = [
            ["a" => 1, "b" => 2, "c" => 3],
            ["a" => 4, "b" => 5, "c" => 6],
            ["a" => 7, "b" => 8, "c" => 9],
            ["a" => 10, "b" => 11, "c" => 12],
        ];

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    public function testArrayOfMixed(): void
    {
        $data = [1, "banana", 3.14, "date"];

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    /**
     * @throws CborException
     */
    public function testArrayOfArraysOfArrays(): void
    {
        $data = [[1, 2, 3], [4, 5, 6], [7, 8, 9], [10, 11, 12]];

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    /**
     * @throws CborException
     */
    public function testArrayOfFlaots(): void
    {
        $data = [1.1234, 2.2234, 3.3425, 4.4238349];

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }
}
