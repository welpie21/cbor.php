<?php

class CborStringTest extends \PHPUnit\Framework\TestCase
{
    public function testMinimalString(): void
    {
        $data = "a";
        $encode = \Beau\CborPHP\CborEncoder::encode($data);
        $decode = \Beau\CborPHP\CborDecoder::decode($encode);
        $this->assertEquals($data, $decode);
    }

    public function testShortString(): void
    {
        $data = "apple";
        $encode = \Beau\CborPHP\CborEncoder::encode($data);
        $decode = \Beau\CborPHP\CborDecoder::decode($encode);
        $this->assertEquals($data, $decode);
    }

    public function testMediumString(): void
    {
        $data = "banana";
        $encode = \Beau\CborPHP\CborEncoder::encode($data);
        $decode = \Beau\CborPHP\CborDecoder::decode($encode);
        $this->assertEquals($data, $decode);
    }

    public function testLongString(): void
    {
        $data = "cherry";
        $encode = \Beau\CborPHP\CborEncoder::encode($data);
        $decode = \Beau\CborPHP\CborDecoder::decode($encode);
        $this->assertEquals($data, $decode);
    }

    public function testVeryLongString(): void
    {
        $data = str_repeat("date", 1000);
        $encode = \Beau\CborPHP\CborEncoder::encode($data);
        $decode = \Beau\CborPHP\CborDecoder::decode($encode);
        $this->assertEquals($data, $decode);
    }
}