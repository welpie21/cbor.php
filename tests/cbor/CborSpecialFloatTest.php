<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\TestCase;

/**
 * Tests special floating-point values and raw float decoding.
 *
 * The encoder always uses float64 (major type 7, additional 27) for
 * non-NaN floats. NaN is encoded as float16 (0xf9 0x7e 0x00) per the
 * CBOR spec recommendation.
 *
 * The decoder supports float16 (additional 25), float32 (additional 26),
 * and float64 (additional 27), allowing interoperability with other
 * CBOR implementations that may emit smaller float encodings.
 */
class CborSpecialFloatTest extends TestCase
{
    /**
     * @throws CborException
     */
    public function testNanExactBytes(): void
    {
        // NaN is encoded as float16 0xf9 0x7e 0x00 (RFC 7049 recommendation)
        $encoded = CborEncoder::encode(NAN);
        $this->assertEquals("\xf9\x7e\x00", $encoded);
        $this->assertEquals(3, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testNanRoundtrip(): void
    {
        $encoded = CborEncoder::encode(NAN);
        $decoded = CborDecoder::decode($encoded);
        $this->assertTrue(is_nan($decoded));
    }

    /**
     * @throws CborException
     */
    public function testPositiveInfinityRoundtrip(): void
    {
        $encoded = CborEncoder::encode(INF);
        $decoded = CborDecoder::decode($encoded);
        $this->assertTrue(is_infinite($decoded));
        $this->assertGreaterThan(0.0, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testPositiveInfinityEncodesAsFloat64(): void
    {
        // INF uses float64 encoding: 0xfb (major 7, additional 27)
        $encoded = CborEncoder::encode(INF);
        $this->assertEquals("\xfb", $encoded[0]);
        $this->assertEquals(9, strlen($encoded)); // 1 header + 8 bytes
    }

    /**
     * @throws CborException
     */
    public function testNegativeInfinityRoundtrip(): void
    {
        $encoded = CborEncoder::encode(-INF);
        $decoded = CborDecoder::decode($encoded);
        $this->assertTrue(is_infinite($decoded));
        $this->assertLessThan(0.0, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testNegativeInfinityEncodesAsFloat64(): void
    {
        $encoded = CborEncoder::encode(-INF);
        $this->assertEquals("\xfb", $encoded[0]);
        $this->assertEquals(9, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testPositiveAndNegativeInfinityAreDifferent(): void
    {
        $posInf = CborEncoder::encode(INF);
        $negInf = CborEncoder::encode(-INF);
        $this->assertNotEquals($posInf, $negInf);
    }

    /**
     * @throws CborException
     */
    public function testZeroFloat(): void
    {
        $encoded = CborEncoder::encode(0.0);
        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals(0.0, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testZeroFloatEncodesAsFloat64(): void
    {
        $encoded = CborEncoder::encode(0.0);
        $this->assertEquals("\xfb", $encoded[0]);
        $this->assertEquals(9, strlen($encoded));
    }

    // ---- Decode float16 from raw bytes (e.g., produced by other CBOR implementations) ----

    /**
     * @throws CborException
     */
    public function testDecodeFloat16Value1_0FromRawBytes(): void
    {
        // float16: 0x3c00 = sign=0, exp=15, mant=0 → 1.0 * 2^(15-15) * (1+0) = 1.0
        // CBOR: 0xf9 0x3c 0x00
        $decoded = CborDecoder::decode("\xf9\x3c\x00");
        $this->assertEquals(1.0, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeFloat16Value1_5FromRawBytes(): void
    {
        // float16: sign=0, exp=15, mant=512 → 1.0 * (1 + 512/1024) = 1.5
        // 0x3e00: 0011 1110 0000 0000
        $decoded = CborDecoder::decode("\xf9\x3e\x00");
        $this->assertEquals(1.5, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeFloat16ValueNeg3_75FromRawBytes(): void
    {
        // -3.75 in float16: sign=1, exp=16, mant=512
        // → -1.0 * 2^(16-15) * (1 + 512/1024) = -1.0 * 2 * 1.5 = -3.0
        // Hmm, let me recompute: -3.75 = -1.0 * 2^1 * 1.875
        // 1.875 = 1 + 0.875 = 1 + 896/1024 = 1 + 0b1110000000/1024
        // exp = 1 + 15 = 16 = 0b10000
        // mant = 0b1110000000 = 0x380
        // half = 1 10000 1110000000 = 1100 0011 1000 0000 = 0xC380
        $decoded = CborDecoder::decode("\xf9\xc3\x80");
        $this->assertEquals(-3.75, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeFloat16PositiveInfinityFromRawBytes(): void
    {
        // float16 +INF: exp=0x1f (31), mant=0 → INF
        // 0x7c00 = 0111 1100 0000 0000
        $decoded = CborDecoder::decode("\xf9\x7c\x00");
        $this->assertTrue(is_infinite($decoded));
        $this->assertGreaterThan(0.0, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeFloat16NaNFromRawBytes(): void
    {
        // float16 NaN: exp=0x1f, mant>0
        // 0x7e00 = 0111 1110 0000 0000 (same as the NaN the encoder emits)
        $decoded = CborDecoder::decode("\xf9\x7e\x00");
        $this->assertTrue(is_nan($decoded));
    }

    // ---- Decode float32 from raw bytes ----

    /**
     * @throws CborException
     */
    public function testDecodeFloat32FromRawBytes(): void
    {
        // float32: 0xfa 0x47 0xc3 0x50 0x00 = 100000.0
        // pack("G", 100000.0) = 0x47c35000
        $decoded = CborDecoder::decode("\xfa\x47\xc3\x50\x00");
        $this->assertEquals(100000.0, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeFloat32ZeroFromRawBytes(): void
    {
        // float32 zero: 0xfa 0x00 0x00 0x00 0x00
        $decoded = CborDecoder::decode("\xfa\x00\x00\x00\x00");
        $this->assertEquals(0.0, $decoded);
    }

    // ---- Decode float64 from raw bytes ----

    /**
     * @throws CborException
     */
    public function testDecodeFloat64FromRawBytes(): void
    {
        // RFC 7049 Appendix A: 1.1 → 0xfb 0x3f 0xf1 0x99 0x99 0x99 0x99 0x99 0x9a
        $decoded = CborDecoder::decode("\xfb\x3f\xf1\x99\x99\x99\x99\x99\x9a");
        $this->assertEqualsWithDelta(1.1, $decoded, 1e-10);
    }

    /**
     * @throws CborException
     */
    public function testDecodeFloat64ZeroFromRawBytes(): void
    {
        // float64 zero: 0xfb followed by 8 zero bytes
        $decoded = CborDecoder::decode("\xfb\x00\x00\x00\x00\x00\x00\x00\x00");
        $this->assertEquals(0.0, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testRegularFloatEncodesAsFloat64(): void
    {
        // All non-NaN floats use float64 (major type 7, additional 27 = 0xfb)
        $values = [1.5, 3.14, -2.71828, 1.0e10, 1.23456789];
        foreach ($values as $value) {
            $encoded = CborEncoder::encode($value);
            $this->assertEquals("\xfb", $encoded[0], "Float $value should start with 0xfb");
            $this->assertEquals(9, strlen($encoded), "Float $value should encode to 9 bytes");
        }
    }
}
