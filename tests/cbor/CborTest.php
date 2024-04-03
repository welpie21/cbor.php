<?php

namespace Beau\tests\cbor;

use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\exceptions\CborReduxException;
use PHPUnit\Framework\TestCase;

class CborTest extends TestCase
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
    public function testEncodeInt()
    {
        $data = [10, 1000, 10_000, 100_000, 1_000_000, 10_000_000, 100_000_000, 1_000_000_000];

        foreach ($data as $value) {

            $encoded = self::$encoder->encode($value);
            $encoded = bin2hex($encoded);

            $decoded = CborDecoder::parse($encoded);

            $this->assertEquals($value, $decoded[0]);
        }
    }
}