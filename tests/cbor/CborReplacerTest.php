<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\classes\TaggedValue;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\TestCase;

class CborCustomType
{
    public function __construct(public string $value) {}
}

/**
 * Tests for the encoder and decoder replacer/reviver callback mechanism.
 *
 * - Encoder replacer: called for values that don't have a native CBOR type,
 *   allowing them to be converted to an encodable type.
 * - Decoder replacer: called for every tagged value (major type 6),
 *   allowing custom deserialization of tagged CBOR values.
 */
class CborReplacerTest extends TestCase
{
    /**
     * @throws CborException
     */
    public function testEncoderReplacerConvertsCustomTypeToString(): void
    {
        $value = new CborCustomType("hello");

        $encoded = CborEncoder::encode($value, function ($key, $value) {
            if ($value instanceof CborCustomType) {
                return $value->value;
            }
            return $value;
        });

        $decoded = CborDecoder::decode($encoded);
        $this->assertSame("hello", $decoded);
    }

    /**
     * @throws CborException
     */
    public function testEncoderReplacerReturningNull(): void
    {
        $value = new CborCustomType("irrelevant");

        $encoded = CborEncoder::encode($value, function ($key, $value) {
            if ($value instanceof CborCustomType) {
                return null;
            }
            return $value;
        });

        $decoded = CborDecoder::decode($encoded);
        $this->assertNull($decoded);
    }

    /**
     * @throws CborException
     */
    public function testEncoderReplacerReturningInteger(): void
    {
        $value = new CborCustomType("42");

        $encoded = CborEncoder::encode($value, function ($key, $value) {
            if ($value instanceof CborCustomType) {
                return (int)$value->value;
            }
            return $value;
        });

        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(42, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testEncoderReplacerReturningArray(): void
    {
        $value = new CborCustomType("data");

        $encoded = CborEncoder::encode($value, function ($key, $value) {
            if ($value instanceof CborCustomType) {
                return ["type" => "custom", "data" => $value->value];
            }
            return $value;
        });

        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals(["type" => "custom", "data" => "data"], $decoded);
    }

    /**
     * @throws CborException
     */
    public function testEncoderReplacerReturningTaggedValue(): void
    {
        $value = new CborCustomType("hello");

        // Tag must be ≤ 23 for the current encoder implementation
        $encoded = CborEncoder::encode($value, function ($key, $value) {
            if ($value instanceof CborCustomType) {
                return new TaggedValue(10, $value->value);
            }
            return $value;
        });

        $decoded = CborDecoder::decode($encoded);
        $this->assertInstanceOf(TaggedValue::class, $decoded);
        $this->assertSame(10, $decoded->tag);
        $this->assertSame("hello", $decoded->value);
    }

    /**
     * @throws CborException
     */
    public function testEncoderAndDecoderReplacerRoundtrip(): void
    {
        $value = new CborCustomType("hello");

        // Tag must be ≤ 23 for the current encoder implementation
        $encoded = CborEncoder::encode($value, function ($key, $value) {
            if ($value instanceof CborCustomType) {
                return new TaggedValue(10, $value->value);
            }
            return $value;
        });

        $decoded = CborDecoder::decode($encoded, function ($key, $value) {
            if ($value instanceof TaggedValue && $value->tag === 10) {
                return new CborCustomType($value->value);
            }
            return $value;
        });

        $this->assertInstanceOf(CborCustomType::class, $decoded);
        $this->assertSame("hello", $decoded->value);
    }

    /**
     * @throws CborException
     */
    public function testDecoderReplacerTransformsTaggedValue(): void
    {
        // Tag must be ≤ 23 for the current encoder implementation
        $encoded = CborEncoder::encode(new TaggedValue(10, "test"));

        $decoded = CborDecoder::decode($encoded, function ($key, $value) {
            if ($value instanceof TaggedValue && $value->tag === 10) {
                return strtoupper($value->value);
            }
            return $value;
        });

        $this->assertSame("TEST", $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecoderReplacerNotCalledForNonTaggedValues(): void
    {
        $replacerCalled = false;

        $encoded = CborEncoder::encode("plain string");
        $decoded = CborDecoder::decode($encoded, function ($key, $value) use (&$replacerCalled) {
            $replacerCalled = true;
            return $value;
        });

        $this->assertFalse($replacerCalled, "Decoder replacer should only be called for tagged values");
        $this->assertSame("plain string", $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecoderReplacerNotCalledForIntegers(): void
    {
        $replacerCalled = false;

        $encoded = CborEncoder::encode(42);
        $decoded = CborDecoder::decode($encoded, function ($key, $value) use (&$replacerCalled) {
            $replacerCalled = true;
            return $value;
        });

        $this->assertFalse($replacerCalled);
        $this->assertSame(42, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecoderReplacerCalledForTaggedValueInsideArray(): void
    {
        $replacerCalled = false;

        $encoded = CborEncoder::encode([1, new TaggedValue(0, "ts"), 2]);
        $decoded = CborDecoder::decode($encoded, function ($key, $value) use (&$replacerCalled) {
            if ($value instanceof TaggedValue) {
                $replacerCalled = true;
            }
            return $value;
        });

        $this->assertTrue($replacerCalled, "Replacer should be called for tagged values inside arrays");
        $this->assertSame(1, $decoded[0]);
        $this->assertSame(2, $decoded[2]);
    }

    /**
     * @throws CborException
     */
    public function testNullReplacerBehavesAsPassThrough(): void
    {
        $value = 42;

        $encoded = CborEncoder::encode($value, null);
        $decoded = CborDecoder::decode($encoded, null);

        $this->assertSame(42, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecoderReplacerReceivesTaggedValueWithCorrectTag(): void
    {
        $receivedTag = null;

        $encoded = CborEncoder::encode(new TaggedValue(15, "data"));
        CborDecoder::decode($encoded, function ($key, $value) use (&$receivedTag) {
            if ($value instanceof TaggedValue) {
                $receivedTag = $value->tag;
            }
            return $value;
        });

        $this->assertSame(15, $receivedTag);
    }
}
