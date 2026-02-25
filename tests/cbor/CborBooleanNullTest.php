<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\TestCase;

class CborBooleanNullTest extends TestCase
{
    /**
     * @throws CborException
     */
    public function testTrueRoundtrip(): void
    {
        $encoded = CborEncoder::encode(true);
        $decoded = CborDecoder::decode($encoded);
        $this->assertTrue($decoded);
    }

    /**
     * @throws CborException
     */
    public function testFalseRoundtrip(): void
    {
        $encoded = CborEncoder::encode(false);
        $decoded = CborDecoder::decode($encoded);
        $this->assertFalse($decoded);
    }

    /**
     * @throws CborException
     */
    public function testNullRoundtrip(): void
    {
        $encoded = CborEncoder::encode(null);
        $decoded = CborDecoder::decode($encoded);
        $this->assertNull($decoded);
    }

    /**
     * @throws CborException
     */
    public function testTrueExactBytes(): void
    {
        // CBOR true = 0xf5 (major type 7, additional 21)
        $encoded = CborEncoder::encode(true);
        $this->assertEquals("\xf5", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testFalseExactBytes(): void
    {
        // CBOR false = 0xf4 (major type 7, additional 20)
        $encoded = CborEncoder::encode(false);
        $this->assertEquals("\xf4", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testNullExactBytes(): void
    {
        // CBOR null = 0xf6 (major type 7, additional 22)
        $encoded = CborEncoder::encode(null);
        $this->assertEquals("\xf6", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodedTrueIsBool(): void
    {
        $encoded = CborEncoder::encode(true);
        $decoded = CborDecoder::decode($encoded);
        $this->assertIsBool($decoded);
        $this->assertTrue($decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodedFalseIsBool(): void
    {
        $encoded = CborEncoder::encode(false);
        $decoded = CborDecoder::decode($encoded);
        $this->assertIsBool($decoded);
        $this->assertFalse($decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodedNullIsNull(): void
    {
        $encoded = CborEncoder::encode(null);
        $decoded = CborDecoder::decode($encoded);
        $this->assertNull($decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeTrueFromRawBytes(): void
    {
        // Decode major type 7, additional 21 → true
        $decoded = CborDecoder::decode("\xf5");
        $this->assertTrue($decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeFalseFromRawBytes(): void
    {
        // Decode major type 7, additional 20 → false
        $decoded = CborDecoder::decode("\xf4");
        $this->assertFalse($decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeNullFromRawBytes(): void
    {
        // Decode major type 7, additional 22 → null
        $decoded = CborDecoder::decode("\xf6");
        $this->assertNull($decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeUndefinedFromRawBytes(): void
    {
        // CBOR undefined (major type 7, additional 23) is decoded as null
        $decoded = CborDecoder::decode("\xf7");
        $this->assertNull($decoded);
    }

    /**
     * @throws CborException
     */
    public function testTrueIsSingleByte(): void
    {
        $encoded = CborEncoder::encode(true);
        $this->assertEquals(1, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testFalseIsSingleByte(): void
    {
        $encoded = CborEncoder::encode(false);
        $this->assertEquals(1, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testNullIsSingleByte(): void
    {
        $encoded = CborEncoder::encode(null);
        $this->assertEquals(1, strlen($encoded));
    }
}
