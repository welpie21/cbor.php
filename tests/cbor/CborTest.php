<?php

namespace Beau\tests\cbor;

use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\exceptions\CborReduxException;
use PHPUnit\Framework\TestCase;

class CborTest extends TestCase
{
    private static CborEncoder $encoder;
    private static CborDecoder $decoder;

    public static function setUpBeforeClass(): void
    {
        self::$encoder = new CborEncoder();
        self::$decoder = new CborDecoder();

        parent::setUpBeforeClass();
    }

    /**
     * @throws CborReduxException
     */
    public function testEncodeInt()
    {
        $encoded = self::$encoder->encode(10);
        $encoded = bin2hex($encoded);

        $decoded = CborDecoder::parse($encoded);
        var_dump($decoded[0]);

        $this->assertEquals(10, $decoded[0]);
    }
}