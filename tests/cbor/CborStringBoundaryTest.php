<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\TestCase;

/**
 * Tests text string (major type 3) encoding edge cases.
 *
 * Length encoding thresholds (same as integer additional-info):
 *   0–23 chars:   length fits in initial byte (1 byte overhead)
 *   24–255 chars: additional uint8 byte for length (2 bytes overhead)
 *   256–65535:    additional uint16 for length (3 bytes overhead)
 *   65536+:       additional uint32 for length (5 bytes overhead)
 */
class CborStringBoundaryTest extends TestCase
{
    /**
     * @throws CborException
     */
    public function testEmptyString(): void
    {
        $encoded = CborEncoder::encode("");
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame("", $decoded);
    }

    /**
     * @throws CborException
     */
    public function testEmptyStringExactBytes(): void
    {
        // CBOR "" = 0x60 (major type 3, length 0)
        $encoded = CborEncoder::encode("");
        $this->assertEquals("\x60", $encoded);
        $this->assertEquals(1, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testSingleCharExactBytes(): void
    {
        // "a" → 0x61 0x61 (major type 3, length 1, then 'a')
        $encoded = CborEncoder::encode("a");
        $this->assertEquals("\x61\x61", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testRfc7049ExampleIetf(): void
    {
        // RFC 7049 Appendix A: "IETF" → 0x64 0x49 0x45 0x54 0x46
        $encoded = CborEncoder::encode("IETF");
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame("IETF", $decoded);
        $this->assertEquals("\x64\x49\x45\x54\x46", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testStringBoundary23(): void
    {
        // 23 chars: max length that fits directly in initial byte
        $str = str_repeat("a", 23);
        $encoded = CborEncoder::encode($str);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($str, $decoded);
        // First byte: 0x60 | 23 = 0x77
        $this->assertEquals("\x77", $encoded[0]);
        $this->assertEquals(24, strlen($encoded)); // 1 header + 23 chars
    }

    /**
     * @throws CborException
     */
    public function testStringBoundary24(): void
    {
        // 24 chars: needs an extra uint8 byte for length
        $str = str_repeat("a", 24);
        $encoded = CborEncoder::encode($str);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($str, $decoded);
        // First byte: 0x78 (major type 3, additional info 24)
        $this->assertEquals("\x78", $encoded[0]);
        // Second byte: 0x18 = 24
        $this->assertEquals("\x18", $encoded[1]);
        $this->assertEquals(26, strlen($encoded)); // 2 header + 24 chars
    }

    /**
     * @throws CborException
     */
    public function testStringBoundary255(): void
    {
        // 255 chars: max length for uint8 encoding
        $str = str_repeat("x", 255);
        $encoded = CborEncoder::encode($str);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($str, $decoded);
        $this->assertEquals(257, strlen($encoded)); // 2 header + 255 chars
    }

    /**
     * @throws CborException
     */
    public function testStringBoundary256(): void
    {
        // 256 chars: needs uint16 for length
        $str = str_repeat("x", 256);
        $encoded = CborEncoder::encode($str);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($str, $decoded);
        // First byte: 0x79 (major type 3, additional info 25)
        $this->assertEquals("\x79", $encoded[0]);
        $this->assertEquals(259, strlen($encoded)); // 3 header + 256 chars
    }

    /**
     * @throws CborException
     */
    public function testStringBoundary65535(): void
    {
        // 65535 chars: max length for uint16 encoding
        $str = str_repeat("y", 65535);
        $encoded = CborEncoder::encode($str);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($str, $decoded);
        $this->assertEquals(65538, strlen($encoded)); // 3 header + 65535 chars
    }

    /**
     * @throws CborException
     */
    public function testStringBoundary65536(): void
    {
        // 65536 chars: needs uint32 for length
        $str = str_repeat("z", 65536);
        $encoded = CborEncoder::encode($str);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($str, $decoded);
        // First byte: 0x7a (major type 3, additional info 26)
        $this->assertEquals("\x7a", $encoded[0]);
        $this->assertEquals(65541, strlen($encoded)); // 5 header + 65536 chars
    }

    /**
     * @throws CborException
     */
    public function testUnicodeStringMultiByteChars(): void
    {
        // Multi-byte UTF-8 characters
        $str = "Hello, 世界! 🌍";
        $encoded = CborEncoder::encode($str);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($str, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testUnicodeStringArabic(): void
    {
        $str = "مرحبا بالعالم"; // "Hello World" in Arabic
        $encoded = CborEncoder::encode($str);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($str, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testStringWithEscapeSequences(): void
    {
        // Strings containing tab, newline, carriage return, backslash, quote
        $str = "\t\n\r\\\"";
        $encoded = CborEncoder::encode($str);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($str, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testStringWithNullBytes(): void
    {
        // CBOR encodes string length explicitly, so null bytes are valid
        $str = "hello\x00world";
        $encoded = CborEncoder::encode($str);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($str, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testStringWithHighBytes(): void
    {
        // Raw bytes in the 0x80-0xFF range
        $str = "\x80\x81\xfe\xff";
        $encoded = CborEncoder::encode($str);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($str, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeStringFromRawBytes(): void
    {
        // RFC 7049 example: "IETF" decoded from raw bytes
        $decoded = CborDecoder::decode("\x64\x49\x45\x54\x46");
        $this->assertSame("IETF", $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeEmptyStringFromRawBytes(): void
    {
        $decoded = CborDecoder::decode("\x60");
        $this->assertSame("", $decoded);
    }

    /**
     * @throws CborException
     */
    public function testStringMajorTypeIs3(): void
    {
        // Verify major type 3 (0x60 base) for text strings
        $encoded = CborEncoder::encode("test");
        $majorType = ord($encoded[0]) >> 5;
        $this->assertSame(3, $majorType);
    }
}
