<?php

namespace Beau\tests\cbor;

use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\exceptions\CborReduxException;
use PHPUnit\Framework\TestCase;

class CborNumberTest extends TestCase
{
    private static CborEncoder $encoder;

    public static function setUpBeforeClass(): void
    {
        self::$encoder = new CborEncoder();

        parent::setUpBeforeClass();
    }

    /**
     * @throws CborReduxException
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

            $encoded = self::$encoder->encode($value);
            $encoded = bin2hex($encoded);

            $decoded = CborDecoder::parse($encoded);

            var_dump($decoded[0]);

            $this->assertEquals($value, $decoded[0]);
        }
    }

    /**
     * @throws CborReduxException
     * @throws \Exception
     */
    public function testFloat(): void
    {
        $data = [
            1.0,
            10.0,
            -10.0,
            -1.0,
        ];

        foreach ($data as $value) {

            $encoded = self::$encoder->encode($value);
            $encoded = bin2hex($encoded);

            $decoded = CborDecoder::parse($encoded);

            var_dump($decoded[0]);

            $this->assertEquals($value, $decoded[0]);
        }
    }
}