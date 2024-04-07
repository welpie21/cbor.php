<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\TestCase;

class CborFloatTest extends TestCase
{
    /**
     * @throws CborException
     */
    public function testFloat16(): void
    {
        $data = [
            1.5,
            -3.75,
            65504.0,
            -0.0625,
            0.1
        ];

        foreach ($data as $item) {
            $encode = CborEncoder::encode($item);
            $decode = CborDecoder::decode($encode);

            $this->assertEquals($item, $decode);
        }
    }

    /**
     * @throws CborException
     */
    public function testFloat32(): void
    {
        $data = [
            3.14159,
            -123.456,
            1.0e10,
            9.999999e7,
            1.2345678e30
        ];

        foreach ($data as $item) {
            $encode = CborEncoder::encode($item);
            $decode = CborDecoder::decode($encode);

            $this->assertEquals($item, $decode);
        }
    }

    /**
     * @throws CborException
     */
    public function testFloat64(): void
    {
        $data = [
            123456789.012345,
            -987654321.987654,
            1.0e100,
            -1.2345678901234567e-300,
            0.000000000000000000000000000000000000000000000000000000000000000001
        ];

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);
        $this->assertEquals($data, $decode);
    }

    /**
     * @throws CborException
     */
    public function testNan(): void
    {
        $data = NAN;

        $encode = CborEncoder::encode($data);
        $decode = CborDecoder::decode($encode);

        $this->assertTrue(is_nan($decode));
    }
}