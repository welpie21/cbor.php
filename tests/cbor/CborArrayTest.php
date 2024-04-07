<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\TestCase;

class CborArrayTest extends TestCase
{
    public function testArrayOfNumbers(): void
    {
        $data = [
            -100,
            -10,
            100,
            10
        ];

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    public function testArrayOfStrings(): void
    {
        $data = [
            "apple",
            "banana",
            "cherry",
            "date"
        ];

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    public function testArrayOfArrays(): void
    {
        $data = [
            [1, 2, 3],
            [4, 5, 6],
            [7, 8, 9],
            [10, 11, 12]
        ];

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
            ["a" => 10, "b" => 11, "c" => 12]
        ];

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    public function testArrayOfMixed(): void
    {
        $data = [
            1,
            "banana",
            3.14,
            "date"
        ];

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    /**
     * @throws CborException
     */
    public function testArrayOfArraysOfArrays(): void
    {
        $data = [
            [1, 2, 3],
            [4, 5, 6],
            [7, 8, 9],
            [10, 11, 12]
        ];

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    /**
     * @throws CborException
     */
    public function testArrayOfFlaots(): void
    {
        $data = [
            1.1234,
            2.2234,
            3.3425,
            4.4238349
        ];

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }
}