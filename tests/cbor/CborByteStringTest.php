<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\utils\CborByteString;
use PHPUnit\Framework\TestCase;

class CborByteStringTest extends TestCase
{
    public function testMinimalByteString(): void
    {
        $data = new CborByteString("a");

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data->getByteString(), $decode);
    }

    public function testShortByteString(): void
    {
        $data = new CborByteString("apple");

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data->getByteString(), $decode);
    }

    public function testMediumByteString(): void
    {
        $data = new CborByteString("banana");

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data->getByteString(), $decode);
    }

    public function testLongByteString(): void
    {
        $data = new CborByteString("cherry");

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data->getByteString(), $decode);
    }

    /**
     * @throws \Beau\CborPHP\exceptions\CborException
     */
    public function testVeryLongByteString(): void
    {
        $data = new CborByteString(str_repeat("date", 1000));

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertEquals($data->getByteString(), $decode);
    }
}