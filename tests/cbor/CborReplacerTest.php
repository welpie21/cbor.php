<?php

use Beau\CborPHP\Cbor;
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
 * Tests for the encoder replacer and the decoder reviver callbacks.
 *
 * - Replacer: called for values that don't have a native CBOR type or a tag
 *   handler, allowing them to be converted to an encodable type.
 * - Reviver: called for every tagged value (major type 6) without a tag
 *   handler, allowing custom deserialization of tagged CBOR values.
 */
class CborReplacerTest extends TestCase
{
    /**
     * @throws CborException
     */
    public function testEncoderReplacerConvertsCustomTypeToString(): void
    {
        $value = new CborCustomType("hello");

        $encoded = (new Cbor(replacer: function ($value) {
            if ($value instanceof CborCustomType) {
                return $value->value;
            }
            return $value;
        }))->encode($value);

        $decoded = CborDecoder::decode($encoded);
        $this->assertSame("hello", $decoded);
    }

    /**
     * @throws CborException
     */
    public function testEncoderReplacerReturningNull(): void
    {
        $value = new CborCustomType("irrelevant");

        $encoded = (new Cbor(replacer: function ($value) {
            if ($value instanceof CborCustomType) {
                return null;
            }
            return $value;
        }))->encode($value);

        $decoded = CborDecoder::decode($encoded);
        $this->assertNull($decoded);
    }

    /**
     * @throws CborException
     */
    public function testEncoderReplacerReturningInteger(): void
    {
        $value = new CborCustomType("42");

        $encoded = (new Cbor(replacer: function ($value) {
            if ($value instanceof CborCustomType) {
                return (int)$value->value;
            }
            return $value;
        }))->encode($value);

        $decoded = CborDecoder::decode($encoded);
        $this->assertSame(42, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testEncoderReplacerReturningArray(): void
    {
        $value = new CborCustomType("data");

        $encoded = (new Cbor(replacer: function ($value) {
            if ($value instanceof CborCustomType) {
                return ["type" => "custom", "data" => $value->value];
            }
            return $value;
        }))->encode($value);

        $decoded = CborDecoder::decode($encoded);
        $this->assertEquals(["type" => "custom", "data" => "data"], $decoded);
    }

    /**
     * @throws CborException
     */
    public function testEncoderReplacerReturningTaggedValue(): void
    {
        $value = new CborCustomType("hello");

        $encoded = (new Cbor(replacer: function ($value) {
            if ($value instanceof CborCustomType) {
                return new TaggedValue(10, $value->value);
            }
            return $value;
        }))->encode($value);

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

        $encoded = (new Cbor(replacer: function ($value) {
            if ($value instanceof CborCustomType) {
                return new TaggedValue(10, $value->value);
            }
            return $value;
        }))->encode($value);

        $decoded = (new Cbor(reviver: function ($value) {
            if ($value instanceof TaggedValue && $value->tag === 10) {
                return new CborCustomType($value->value);
            }
            return $value;
        }))->decode($encoded);

        $this->assertInstanceOf(CborCustomType::class, $decoded);
        $this->assertSame("hello", $decoded->value);
    }

    /**
     * @throws CborException
     */
    public function testDecoderReplacerTransformsTaggedValue(): void
    {
        $encoded = CborEncoder::encode(new TaggedValue(10, "test"));

        $decoded = (new Cbor(reviver: function ($value) {
            if ($value instanceof TaggedValue && $value->tag === 10) {
                return strtoupper($value->value);
            }
            return $value;
        }))->decode($encoded);

        $this->assertSame("TEST", $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecoderReplacerNotCalledForNonTaggedValues(): void
    {
        $replacerCalled = false;

        $encoded = CborEncoder::encode("plain string");
        $decoded = (new Cbor(reviver: function ($value) use (&$replacerCalled) {
            $replacerCalled = true;
            return $value;
        }))->decode($encoded);

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
        $decoded = (new Cbor(reviver: function ($value) use (&$replacerCalled) {
            $replacerCalled = true;
            return $value;
        }))->decode($encoded);

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
        $decoded = (new Cbor(reviver: function ($value) use (&$replacerCalled) {
            if ($value instanceof TaggedValue) {
                $replacerCalled = true;
            }
            return $value;
        }))->decode($encoded);

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

        $cbor = new Cbor(replacer: null, reviver: null);
        $encoded = $cbor->encode($value);
        $decoded = $cbor->decode($encoded);

        $this->assertSame(42, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDecoderReplacerReceivesTaggedValueWithCorrectTag(): void
    {
        $receivedTag = null;

        $encoded = CborEncoder::encode(new TaggedValue(15, "data"));
        (new Cbor(reviver: function ($value) use (&$receivedTag) {
            if ($value instanceof TaggedValue) {
                $receivedTag = $value->tag;
            }
            return $value;
        }))->decode($encoded);

        $this->assertSame(15, $receivedTag);
    }

    /**
     * @throws CborException
     */
    public function testEncodingObjectWithoutReplacerThrows(): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage("Unsupported type: stdClass");
        CborEncoder::encode(new stdClass());
    }

    /**
     * @throws CborException
     */
    public function testEncodingObjectNestedInArrayWithoutReplacerThrows(): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage("Unsupported type: stdClass");
        CborEncoder::encode([1, ["key" => new stdClass()]]);
    }

    /**
     * @throws CborException
     */
    public function testEncoderReplacerReturningSameValueThrows(): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage("Unsupported type: CborCustomType");
        (new Cbor(replacer: fn($value) => $value))->encode(new CborCustomType("hello"));
    }

    /**
     * @throws CborException
     */
    public function testEncoderReplacerReturningAnotherUnsupportedValueThrows(): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage("Unsupported type: stdClass");
        (new Cbor(replacer: fn($value) => new stdClass()))->encode(new CborCustomType("hello"));
    }

    /**
     * @throws CborException
     */
    public function testEncoderReplacerAppliesToValuesInsideReplacedArray(): void
    {
        // The replacer's return value must be encodable, but values nested
        // inside a returned array are passed to the replacer again.
        $encoded = (new Cbor(replacer: function ($value) {
            if ($value instanceof CborCustomType && $value->value === "outer") {
                return [new CborCustomType("inner")];
            }
            if ($value instanceof CborCustomType) {
                return $value->value;
            }
            return $value;
        }))->encode(new CborCustomType("outer"));

        $this->assertSame(["inner"], CborDecoder::decode($encoded));
    }
}
