<?php

namespace Beau\tests\cbor;

use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\TestCase;

class CborNumberTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    /**
     * @throws CborException
     * @throws \Exception
     */
    public function testInt()
    {
        $data = [
            1,
            10,
            100,
            1000,
            10_000,
            100_000,
            1_000_000,
            10_000_000,
            100_000_000,
            1_000_000_000,
            10_000_000_000,
            100_000_000_000,
            1_000_000_000_000,
            10_000_000_000_000,
            100_000_000_000_000,
            1_000_000_000_000_000,
            10_000_000_000_000_000,
            100_000_000_000_000_000,
            1_000_000_000_000_000_000,
            -1_000_000_000_000_000_000,
            -100_000_000_000_000_000,
            -10_000_000_000_000_000,
            -1_000_000_000_000,
            -100_000_000_000,
            -10_000_000_000,
            -1_000_000_000,
            -100_000_000,
            -10_000_000,
            -1_000_000,
            -100_000,
            -10_000,
            -1_000,
            -100,
            -10,
            -1,
        ];

        foreach ($data as $value) {

            $encoded = CborEncoder::encode($value);
            $decoded = CborDecoder::decode($encoded);

            $this->assertEquals($value, $decoded);
        }
    }

    /**
     * @throws CborException
     * @throws \Exception
     */
    public function testFloat(): void
    {
        $data = [
            1.0,
            10.0,
            100.0,
            1000.0,
            10_000.0,
            100_000.0,
            1_000_000.0,
            10_000_000.0,
            100_000_000.0,
            1_000_000_000.0,
            10_000_000_000.0,
            100_000_000_000.0,
            1_000_000_000_000.0,
            10_000_000_000_000.0,
            100_000_000_000_000.0,
            1_000_000_000_000_000.0,
            10_000_000_000_000_000.0,
            100_000_000_000_000_000.0,
            1_000_000_000_000_000_000.0,
            -1_000_000_000_000_000_000.0,
            -100_000_000_000_000_000.0,
            -10_000_000_000_000_000.0,
            -1_000_000_000_000.0,
            -100_000_000_000.0,
            -10_000_000_000.0,
            -1_000_000_000.0,
            -100_000_000.0,
            -10_000_000.0,
            -1_000_000.0,
            -100_000.0,
            -10_000.0,
            -1_000.0,
            -100.0,
            -10.0,
            -1.0,
        ];

        foreach ($data as $value) {

            $encoded = CborEncoder::encode($value);
            $decoded = CborDecoder::decode($encoded);

            $this->assertEquals($value, $decoded);
        }
    }

    /**
     * @throws CborException
     */
    public function testComplexFloatDecimals(): void
    {
        $data = [
            10.47294,
            100.47294,
            31295.47294,
            100_000.47294,
            -10.47294,
            -100.47294,
            -31295.47294,
            -100_000.47294,
        ];

        foreach ($data as $value) {

            $encoded = CborEncoder::encode($value);
            $decoded = CborDecoder::decode($encoded);

            $this->assertEquals($value, $decoded);
        }
    }
}