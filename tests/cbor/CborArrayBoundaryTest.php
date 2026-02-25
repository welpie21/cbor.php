<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\TestCase;

/**
 * Tests CBOR array (major type 4) encoding edge cases.
 *
 * PHP list arrays (sequential integer keys starting at 0) are encoded as
 * CBOR arrays. The element count uses the same boundary thresholds as integers:
 *   0–23 elements: count in initial byte
 *   24–255 elements: extra uint8
 *   256+ elements: extra uint16
 */
class CborArrayBoundaryTest extends TestCase
{
    /**
     * @throws CborException
     */
    public function testSingleElementArray(): void
    {
        $data = [42];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testSingleElementArrayExactBytes(): void
    {
        // [1] → 0x81 0x01 (major type 4, length 1, integer 1)
        $encoded = CborEncoder::encode([1]);
        $this->assertEquals("\x81\x01", $encoded);
    }

    /**
     * @throws CborException
     */
    public function testArrayWith23Elements(): void
    {
        // 23 elements: max inline count
        $data = range(1, 23);
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals($data, $decoded);
        // First byte: 0x80 | 23 = 0x97
        $this->assertEquals("\x97", $encoded[0]);
    }

    /**
     * @throws CborException
     */
    public function testArrayWith24Elements(): void
    {
        // 24 elements: needs extra uint8 for count
        $data = range(1, 24);
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals($data, $decoded);
        // First byte: 0x98 (major type 4, additional info 24)
        $this->assertEquals("\x98", $encoded[0]);
        // Second byte: 0x18 = 24
        $this->assertEquals("\x18", $encoded[1]);
    }

    /**
     * @throws CborException
     */
    public function testArrayWith255Elements(): void
    {
        $data = array_fill(0, 255, 0);
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testArrayWith256Elements(): void
    {
        // 256 elements: needs uint16 for count
        $data = array_fill(0, 256, 0);
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals($data, $decoded);
        // First byte: 0x99 (major type 4, additional info 25)
        $this->assertEquals("\x99", $encoded[0]);
    }

    /**
     * @throws CborException
     */
    public function testArrayWithNullElements(): void
    {
        $data = [null, null, null];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testArrayWithBooleanElements(): void
    {
        $data = [true, false, true, false];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testArrayWithMixedTypesIncludingBoolAndNull(): void
    {
        $data = [1, "hello", true, false, null, 3.14, -42];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDeeplyNestedArray(): void
    {
        // 10 levels of nesting
        $data = [[[[[[[[[42]]]]]]]]];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testArrayMajorTypeIs4(): void
    {
        $encoded = CborEncoder::encode([1, 2, 3]);
        $majorType = ord($encoded[0]) >> 5;
        $this->assertSame(4, $majorType);
    }

    /**
     * @throws CborException
     */
    public function testDecodeArrayFromRawBytes(): void
    {
        // RFC 7049 Appendix A: [1, 2, 3] → 0x83 0x01 0x02 0x03
        $decoded = CborDecoder::decode("\x83\x01\x02\x03");
        $this->assertSame([1, 2, 3], $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeEmptyArrayFromRawBytes(): void
    {
        // RFC 7049 Appendix A: [] → 0x80
        $decoded = CborDecoder::decode("\x80");
        $this->assertSame([], $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecodeNestedArrayFromRawBytes(): void
    {
        // RFC 7049 Appendix A: [[1], [2, 3], [4, 5]] → 0x83 0x81 0x01 0x82 0x02 0x03 0x82 0x04 0x05
        $decoded = CborDecoder::decode("\x83\x81\x01\x82\x02\x03\x82\x04\x05");
        $this->assertSame([[1], [2, 3], [4, 5]], $decoded);
    }

    /**
     * @throws CborException
     */
    public function testArrayPreservesOrder(): void
    {
        $data = [3, 1, 4, 1, 5, 9, 2, 6, 5, 3, 5];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testArrayWithNegativeIntegers(): void
    {
        $data = [-1, -23, -24, -25, -255, -256, -1000];
        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);
        $this->assertSame($data, $decoded);
    }
}
