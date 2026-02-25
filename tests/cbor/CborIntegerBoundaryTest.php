<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\TestCase;

/**
 * Tests integer encoding boundary conditions.
 *
 * CBOR uses the following additional-info values for unsigned integers:
 *   0–23:  value is the additional info itself (1 byte total)
 *   24:    one extra byte follows (uint8)
 *   25:    two extra bytes follow (uint16 big-endian)
 *   26:    four extra bytes follow (uint32 big-endian)
 *   27:    eight extra bytes follow (uint64 big-endian)
 *
 * Negative integers are encoded as major type 1 with value = abs(n) - 1.
 */
class CborIntegerBoundaryTest extends TestCase
{
    /**
     * @throws CborException
     */
    public function testZeroRoundtrip(): void
    {
        $encoded = CborEncoder::encode(0);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(0, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testZeroExactBytes(): void
    {
        // 0 → 0x00 (major type 0, additional info 0)
        $encoded = CborEncoder::encode(0);
        $this->assertEquals("\x00", $encoded);
        $this->assertEquals(1, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testBoundary23(): void
    {
        // 23 is the maximum value that fits in the additional-info field directly
        $encoded = CborEncoder::encode(23);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(23, $decoded);
        // 0x17 = major type 0 | 23
        $this->assertEquals("\x17", $encoded);
        $this->assertEquals(1, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testBoundary24(): void
    {
        // 24 requires an extra uint8 byte
        $encoded = CborEncoder::encode(24);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(24, $decoded);
        // 0x18 0x18 = major type 0, additional 24, then value 24
        $this->assertEquals("\x18\x18", $encoded);
        $this->assertEquals(2, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testBoundary255(): void
    {
        // 255 is the maximum uint8 value
        $encoded = CborEncoder::encode(255);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(255, $decoded);
        // 0x18 0xff
        $this->assertEquals("\x18\xff", $encoded);
        $this->assertEquals(2, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testBoundary256(): void
    {
        // 256 requires a uint16 (two extra bytes)
        $encoded = CborEncoder::encode(256);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(256, $decoded);
        // 0x19 0x01 0x00
        $this->assertEquals("\x19\x01\x00", $encoded);
        $this->assertEquals(3, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testBoundary65535(): void
    {
        // 65535 is the maximum uint16 value
        $encoded = CborEncoder::encode(65535);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(65535, $decoded);
        // 0x19 0xff 0xff
        $this->assertEquals("\x19\xff\xff", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testBoundary65536(): void
    {
        // 65536 requires a uint32 (four extra bytes)
        $encoded = CborEncoder::encode(65536);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(65536, $decoded);
        // 0x1a 0x00 0x01 0x00 0x00
        $this->assertEquals("\x1a\x00\x01\x00\x00", $encoded);
        $this->assertEquals(5, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testBoundary4294967295(): void
    {
        // 4294967295 = 0xFFFFFFFF is the maximum uint32 value
        $encoded = CborEncoder::encode(4294967295);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(4294967295, $decoded);
        // 0x1a 0xff 0xff 0xff 0xff
        $this->assertEquals("\x1a\xff\xff\xff\xff", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testBoundary4294967296(): void
    {
        // 4294967296 requires a uint64 (eight extra bytes)
        $encoded = CborEncoder::encode(4294967296);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(4294967296, $decoded);
        // 0x1b followed by 8 bytes
        $this->assertEquals("\x1b", $encoded[0]);
        $this->assertEquals(9, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testNegativeOne(): void
    {
        // -1 encodes as major type 1, additional 0 (value = abs(-1) - 1 = 0)
        $encoded = CborEncoder::encode(-1);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(-1, $decoded);
        // 0x20 = major type 1 | 0
        $this->assertEquals("\x20", $encoded);
        $this->assertEquals(1, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testNegativeBoundaryMinus24(): void
    {
        // -24 → abs(-24)-1 = 23 (fits inline, major type 1)
        $encoded = CborEncoder::encode(-24);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(-24, $decoded);
        // 0x37 = major type 1 | 23
        $this->assertEquals("\x37", $encoded);
        $this->assertEquals(1, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testNegativeBoundaryMinus25(): void
    {
        // -25 → abs(-25)-1 = 24 (needs extra uint8, major type 1)
        $encoded = CborEncoder::encode(-25);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(-25, $decoded);
        // 0x38 0x18 = major type 1, additional 24, value 24
        $this->assertEquals("\x38\x18", $encoded);
        $this->assertEquals(2, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testNegativeBoundaryMinus256(): void
    {
        // -256 → abs(-256)-1 = 255 = 0xFF (uint8)
        $encoded = CborEncoder::encode(-256);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(-256, $decoded);
        // 0x38 0xff
        $this->assertEquals("\x38\xff", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testNegativeBoundaryMinus257(): void
    {
        // -257 → abs(-257)-1 = 256 = 0x0100 (needs uint16)
        $encoded = CborEncoder::encode(-257);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(-257, $decoded);
        // 0x39 0x01 0x00
        $this->assertEquals("\x39\x01\x00", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testRfc7049Example1(): void
    {
        // RFC 7049 Appendix A: 1 → 0x01
        $encoded = CborEncoder::encode(1);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(1, $decoded);
        $this->assertEquals("\x01", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testRfc7049Example10(): void
    {
        // RFC 7049 Appendix A: 10 → 0x0a
        $encoded = CborEncoder::encode(10);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(10, $decoded);
        $this->assertEquals("\x0a", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testRfc7049Example100(): void
    {
        // RFC 7049 Appendix A: 100 → 0x18 0x64
        $encoded = CborEncoder::encode(100);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(100, $decoded);
        $this->assertEquals("\x18\x64", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testRfc7049Example1000(): void
    {
        // RFC 7049 Appendix A: 1000 → 0x19 0x03 0xe8
        $encoded = CborEncoder::encode(1000);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(1000, $decoded);
        $this->assertEquals("\x19\x03\xe8", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testRfc7049Example1000000(): void
    {
        // RFC 7049 Appendix A: 1000000 → 0x1a 0x00 0x0f 0x42 0x40
        $encoded = CborEncoder::encode(1000000);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(1000000, $decoded);
        $this->assertEquals("\x1a\x00\x0f\x42\x40", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testRfc7049ExampleNeg10(): void
    {
        // RFC 7049 Appendix A: -10 → 0x29 (major type 1 | 9, since abs(-10)-1=9)
        $encoded = CborEncoder::encode(-10);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(-10, $decoded);
        $this->assertEquals("\x29", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testRfc7049ExampleNeg100(): void
    {
        // RFC 7049 Appendix A: -100 → 0x38 0x63 (abs(-100)-1=99=0x63)
        $encoded = CborEncoder::encode(-100);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(-100, $decoded);
        $this->assertEquals("\x38\x63", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testRfc7049ExampleNeg1000(): void
    {
        // RFC 7049 Appendix A: -1000 → 0x39 0x03 0xe7 (abs(-1000)-1=999=0x3e7)
        $encoded = CborEncoder::encode(-1000);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(-1000, $decoded);
        $this->assertEquals("\x39\x03\xe7", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeIntFromRawBytes(): void
    {
        // Decode 25 from raw bytes: 0x18 0x19
        $decoded = CborDecoder::decode("\x18\x19");
        $this->assertSame(25, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testAllSingleBytePositiveIntegers(): void
    {
        // All values 0–23 should encode as a single byte
        for ($i = 0; $i <= 23; $i++) {
            $encoded = CborEncoder::encode($i);
            $this->assertEquals(1, strlen($encoded), "Integer $i should encode as 1 byte");
            $decoded = CborDecoder::decode($encoded);
            $this->assertSame($i, $decoded, "Integer $i should round-trip correctly");
        }
    }

    /**
     * @throws CborException
     */
    public function testAllSingleByteNegativeIntegers(): void
    {
        // Values -1 through -24 should encode as a single byte (abs(n)-1 fits in 0–23)
        for ($i = -1; $i >= -24; $i--) {
            $encoded = CborEncoder::encode($i);
            $this->assertEquals(1, strlen($encoded), "Integer $i should encode as 1 byte");
            $decoded = CborDecoder::decode($encoded);
            $this->assertSame($i, $decoded, "Integer $i should round-trip correctly");
        }
    }
}