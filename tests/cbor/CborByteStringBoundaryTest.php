<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\exceptions\CborException;
use Beau\CborPHP\utils\CborByteString;
use PHPUnit\Framework\TestCase;

/**
 * Tests byte string (major type 2) encoding edge cases.
 *
 * Byte strings use the same length encoding thresholds as text strings,
 * but with major type 2 (base byte 0x40 instead of 0x60).
 */
class CborByteStringBoundaryTest extends TestCase
{
    /**
     * @throws CborException
     */
    public function testEmptyByteString(): void
    {
        $data = new CborByteString("");
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame("", $decoded);
    }

    /**
     * @throws CborException
     */
    public function testEmptyByteStringExactBytes(): void
    {
        // CBOR empty byte string = 0x40 (major type 2, length 0)
        $data = new CborByteString("");
        $encoded = CborEncoder::encode($data);
        $this->assertEquals("\x40", $encoded);
        $this->assertEquals(1, strlen($encoded));
    }

    /**
     * @throws CborException
     */
    public function testByteStringMajorTypeIs2(): void
    {
        $data = new CborByteString("hello");
        $encoded = CborEncoder::encode($data);
        $majorType = ord($encoded[0]) >> 5;
        $this->assertSame(2, $majorType);
    }

    /**
     * @throws CborException
     */
    public function testByteStringWithBinaryData(): void
    {
        // Byte string with all 256 possible byte values
        $bytes = "\x00\x01\x02\xfe\xff";
        $data = new CborByteString($bytes);
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($bytes, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testByteStringWithAllZeroBytes(): void
    {
        $bytes = str_repeat("\x00", 16);
        $data = new CborByteString($bytes);
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($bytes, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testByteStringWithAllHighBytes(): void
    {
        $bytes = str_repeat("\xff", 16);
        $data = new CborByteString($bytes);
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($bytes, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testByteStringBoundary23(): void
    {
        // 23 bytes: max length that fits in initial byte
        $bytes = str_repeat("\xAB", 23);
        $data = new CborByteString($bytes);
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($bytes, $decoded);
        // First byte: 0x40 | 23 = 0x57
        $this->assertEquals("\x57", $encoded[0]);
        $this->assertEquals(24, strlen($encoded)); // 1 header + 23 bytes
    }

    /**
     * @throws CborException
     */
    public function testByteStringBoundary24(): void
    {
        // 24 bytes: needs extra uint8 byte for length
        $bytes = str_repeat("\xAB", 24);
        $data = new CborByteString($bytes);
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($bytes, $decoded);
        // First byte: 0x58 (major type 2, additional info 24)
        $this->assertEquals("\x58", $encoded[0]);
        // Second byte: 0x18 = 24
        $this->assertEquals("\x18", $encoded[1]);
        $this->assertEquals(26, strlen($encoded)); // 2 header + 24 bytes
    }

    /**
     * @throws CborException
     */
    public function testByteStringBoundary255(): void
    {
        // 255 bytes: max for uint8 length
        $bytes = str_repeat("\xCD", 255);
        $data = new CborByteString($bytes);
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($bytes, $decoded);
        $this->assertEquals(257, strlen($encoded)); // 2 header + 255 bytes
    }

    /**
     * @throws CborException
     */
    public function testByteStringBoundary256(): void
    {
        // 256 bytes: needs uint16 for length
        $bytes = str_repeat("\xDE", 256);
        $data = new CborByteString($bytes);
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($bytes, $decoded);
        // First byte: 0x59 (major type 2, additional info 25)
        $this->assertEquals("\x59", $encoded[0]);
        $this->assertEquals(259, strlen($encoded)); // 3 header + 256 bytes
    }

    /**
     * @throws CborException
     */
    public function testByteStringBoundary65536(): void
    {
        // 65536 bytes: needs uint32 for length
        $bytes = str_repeat("\xAB", 65536);
        $data = new CborByteString($bytes);
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($bytes, $decoded);
        // First byte: 0x5a (major type 2, additional info 26)
        $this->assertEquals("\x5a", $encoded[0]);
    }

    /**
     * @throws CborException
     */
    public function testByteStringVsTextStringHaveDifferentMajorTypes(): void
    {
        $text = "hello";
        $bytes = new CborByteString("hello");

        $textEncoded = CborEncoder::encode($text);
        $bytesEncoded = CborEncoder::encode($bytes);

        // Major type for text string is 3, for byte string is 2
        $textMajorType = ord($textEncoded[0]) >> 5;
        $bytesMajorType = ord($bytesEncoded[0]) >> 5;

        $this->assertSame(3, $textMajorType);
        $this->assertSame(2, $bytesMajorType);
        $this->assertNotEquals($textEncoded, $bytesEncoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeByteStringFromRawBytes(): void
    {
        // 0x44 0x01 0x02 0x03 0x04 = byte string of length 4
        $decoded = CborDecoder::decode("\x44\x01\x02\x03\x04");
        $this->assertSame("\x01\x02\x03\x04", $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeEmptyByteStringFromRawBytes(): void
    {
        $decoded = CborDecoder::decode("\x40");
        $this->assertSame("", $decoded);
    }

    /**
     * @throws CborException
     */
    public function testByteStringWithEmbeddedCborBytes(): void
    {
        // Byte strings can hold arbitrary bytes, including valid CBOR
        $inner = CborEncoder::encode(42);
        $data = new CborByteString($inner);
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        // Decoded should be the raw inner bytes, not the integer 42
        $this->assertSame($inner, $decoded);
    }
}
