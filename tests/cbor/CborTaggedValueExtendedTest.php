<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\classes\TaggedValue;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\TestCase;

/**
 * Tests CBOR tagged values (major type 6) beyond the basic use case.
 *
 * Tags 0–23 are encoded with the tag number directly in the initial byte's
 * additional-info field. Tags 0–23 are enough to cover the most common
 * standard CBOR tags (datetime, epoch, bignum, base64url, etc.).
 */
class CborTaggedValueExtendedTest extends TestCase
{
    /**
     * @throws CborException
     */
    public function testTaggedValueDecodesAsTaggedValueWithoutReplacer(): void
    {
        // Without a replacer, the decoder returns a TaggedValue instance
        $original = new TaggedValue(1, "hello");
        $encoded = CborEncoder::encode($original);
        $decoded = CborDecoder::decode($encoded);

        $this->assertInstanceOf(TaggedValue::class, $decoded);
        $this->assertSame(1, $decoded->tag);
        $this->assertSame("hello", $decoded->value);
    }

    /**
     * @throws CborException
     */
    public function testTaggedValueTag0DatetimeString(): void
    {
        // Tag 0 = standard date/time string (RFC 7049)
        $datetime = "2024-01-15T12:00:00Z";
        $original = new TaggedValue(0, $datetime);
        $encoded = CborEncoder::encode($original);
        $decoded = CborDecoder::decode($encoded);

        $this->assertInstanceOf(TaggedValue::class, $decoded);
        $this->assertSame(0, $decoded->tag);
        $this->assertSame($datetime, $decoded->value);
    }

    /**
     * @throws CborException
     */
    public function testTaggedValueTag1EpochTimestamp(): void
    {
        // Tag 1 = epoch-based date/time (numeric timestamp)
        $timestamp = 1705316400;
        $original = new TaggedValue(1, $timestamp);
        $encoded = CborEncoder::encode($original);
        $decoded = CborDecoder::decode($encoded);

        $this->assertInstanceOf(TaggedValue::class, $decoded);
        $this->assertSame(1, $decoded->tag);
        $this->assertSame($timestamp, $decoded->value);
    }

    /**
     * @throws CborException
     */
    public function testTaggedValueTag23ExactFirstByte(): void
    {
        // Tag 23 is the maximum value that encodes in the initial byte
        // First byte: 0xC0 | 23 = 0xD7
        $original = new TaggedValue(23, "test");
        $encoded = CborEncoder::encode($original);
        $this->assertEquals("\xd7", $encoded[0]);
    }

    /**
     * @throws CborException
     */
    public function testTaggedValueTag0ExactFirstByte(): void
    {
        // Tag 0: 0xC0 | 0 = 0xC0
        $original = new TaggedValue(0, "test");
        $encoded = CborEncoder::encode($original);
        $this->assertEquals("\xc0", $encoded[0]);
    }

    /**
     * @throws CborException
     */
    public function testTaggedValueWithArrayContent(): void
    {
        $original = new TaggedValue(4, [1, 2, 3]);
        $encoded = CborEncoder::encode($original);
        $decoded = CborDecoder::decode($encoded);

        $this->assertInstanceOf(TaggedValue::class, $decoded);
        $this->assertSame(4, $decoded->tag);
        $this->assertSame([1, 2, 3], $decoded->value);
    }

    /**
     * @throws CborException
     */
    public function testTaggedValueWithMapContent(): void
    {
        $original = new TaggedValue(5, ["a" => 1, "b" => 2]);
        $encoded = CborEncoder::encode($original);
        $decoded = CborDecoder::decode($encoded);

        $this->assertInstanceOf(TaggedValue::class, $decoded);
        $this->assertSame(5, $decoded->tag);
        $this->assertEquals(["a" => 1, "b" => 2], $decoded->value);
    }

    /**
     * @throws CborException
     */
    public function testTaggedValueWithNullContent(): void
    {
        $original = new TaggedValue(3, null);
        $encoded = CborEncoder::encode($original);
        $decoded = CborDecoder::decode($encoded);

        $this->assertInstanceOf(TaggedValue::class, $decoded);
        $this->assertSame(3, $decoded->tag);
        $this->assertNull($decoded->value);
    }

    /**
     * @throws CborException
     */
    public function testTaggedValueWithIntegerContent(): void
    {
        $original = new TaggedValue(2, 12345);
        $encoded = CborEncoder::encode($original);
        $decoded = CborDecoder::decode($encoded);

        $this->assertInstanceOf(TaggedValue::class, $decoded);
        $this->assertSame(2, $decoded->tag);
        $this->assertSame(12345, $decoded->value);
    }

    /**
     * @throws CborException
     */
    public function testTaggedValueWithBooleanContent(): void
    {
        $original = new TaggedValue(6, true);
        $encoded = CborEncoder::encode($original);
        $decoded = CborDecoder::decode($encoded);

        $this->assertInstanceOf(TaggedValue::class, $decoded);
        $this->assertSame(6, $decoded->tag);
        $this->assertTrue($decoded->value);
    }

    /**
     * @throws CborException
     */
    public function testTaggedValueWithFloatContent(): void
    {
        $original = new TaggedValue(7, 3.14);
        $encoded = CborEncoder::encode($original);
        $decoded = CborDecoder::decode($encoded);

        $this->assertInstanceOf(TaggedValue::class, $decoded);
        $this->assertSame(7, $decoded->tag);
        $this->assertEqualsWithDelta(3.14, $decoded->value, 1e-10);
    }

    /**
     * @throws CborException
     */
    public function testArrayOfTaggedValues(): void
    {
        $encoded = CborEncoder::encode([
            new TaggedValue(1, "first"),
            new TaggedValue(2, "second"),
            new TaggedValue(3, 42),
        ]);
        $decoded = CborDecoder::decode($encoded);

        $this->assertIsArray($decoded);
        $this->assertCount(3, $decoded);
        $this->assertInstanceOf(TaggedValue::class, $decoded[0]);
        $this->assertInstanceOf(TaggedValue::class, $decoded[1]);
        $this->assertInstanceOf(TaggedValue::class, $decoded[2]);
        $this->assertSame(1, $decoded[0]->tag);
        $this->assertSame("first", $decoded[0]->value);
        $this->assertSame(2, $decoded[1]->tag);
        $this->assertSame("second", $decoded[1]->value);
        $this->assertSame(3, $decoded[2]->tag);
        $this->assertSame(42, $decoded[2]->value);
    }

    /**
     * @throws CborException
     */
    public function testTaggedValueInMapValue(): void
    {
        $encoded = CborEncoder::encode([
            "created_at" => new TaggedValue(1, 1705316400),
        ]);
        $decoded = CborDecoder::decode($encoded);

        $this->assertArrayHasKey("created_at", $decoded);
        $this->assertInstanceOf(TaggedValue::class, $decoded["created_at"]);
        $this->assertSame(1, $decoded["created_at"]->tag);
        $this->assertSame(1705316400, $decoded["created_at"]->value);
    }

    /**
     * @throws CborException
     */
    public function testAllTagValues0Through23Roundtrip(): void
    {
        for ($tag = 0; $tag <= 23; $tag++) {
            $original = new TaggedValue($tag, "value");
            $encoded = CborEncoder::encode($original);
            $decoded = CborDecoder::decode($encoded);

            $this->assertInstanceOf(TaggedValue::class, $decoded, "Tag $tag should decode to TaggedValue");
            $this->assertSame($tag, $decoded->tag, "Tag $tag should roundtrip correctly");
            $this->assertSame("value", $decoded->value, "Tag $tag content should roundtrip correctly");
        }
    }

    /**
     * @throws CborException
     */
    public function testTaggedValueEncodesWithMajorType6(): void
    {
        $original = new TaggedValue(1, "test");
        $encoded = CborEncoder::encode($original);
        $majorType = ord($encoded[0]) >> 5;
        $this->assertSame(6, $majorType);
    }

    /**
     * @throws CborException
     */
    public function testDecodeTaggedValueFromRawBytes(): void
    {
        // 0xc1 = major type 6, tag 1; 0x0a = integer 10
        $decoded = CborDecoder::decode("\xc1\x0a");
        $this->assertInstanceOf(TaggedValue::class, $decoded);
        $this->assertSame(1, $decoded->tag);
        $this->assertSame(10, $decoded->value);
    }
}
