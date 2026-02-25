<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the decoder throws CborException for unsupported CBOR constructs.
 *
 * The decoder handles major types 0–7. Within major type 7 (simple/float),
 * only additional values 20–23 (false/true/null/undefined) and
 * 25–27 (float16/float32/float64) are supported.
 */
class CborErrorTest extends TestCase
{
    /**
     * @throws CborException
     */
    public function testDecodeUnsupportedSimpleValueAdditional0(): void
    {
        $this->expectException(CborException::class);
        // 0xe0 = major type 7, additional 0 → unsupported simple value
        CborDecoder::decode("\xe0");
    }

    /**
     * @throws CborException
     */
    public function testDecodeUnsupportedSimpleValueAdditional1(): void
    {
        $this->expectException(CborException::class);
        // 0xe1 = major type 7, additional 1 → unsupported simple value
        CborDecoder::decode("\xe1");
    }

    /**
     * @throws CborException
     */
    public function testDecodeUnsupportedSimpleValueAdditional16(): void
    {
        $this->expectException(CborException::class);
        // 0xf0 = major type 7, additional 16 → unsupported simple value
        CborDecoder::decode("\xf0");
    }

    /**
     * @throws CborException
     */
    public function testDecodeUnsupportedSimpleValueAdditional19(): void
    {
        $this->expectException(CborException::class);
        // 0xf3 = major type 7, additional 19 → unsupported simple value
        CborDecoder::decode("\xf3");
    }

    /**
     * @throws CborException
     */
    public function testDecodeUnsupportedSimpleValueAdditional24(): void
    {
        $this->expectException(CborException::class);
        // 0xf8 = major type 7, additional 24 → unsupported (extended simple value)
        // In CBOR, additional 24 in major type 7 means the simple value follows in the next byte,
        // but this decoder does not support extended simple values.
        CborDecoder::decode("\xf8\x10");
    }

    /**
     * @throws CborException
     */
    public function testCborExceptionMessageContainsContext(): void
    {
        try {
            CborDecoder::decode("\xe0");
            $this->fail("Expected CborException was not thrown");
        } catch (CborException $e) {
            $this->assertStringContainsString("simple value", $e->getMessage());
        }
    }

    /**
     * @throws CborException
     */
    public function testCborExceptionIsInstanceOfException(): void
    {
        try {
            CborDecoder::decode("\xe0");
            $this->fail("Expected CborException was not thrown");
        } catch (CborException $e) {
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * @throws CborException
     */
    public function testCborExceptionPrefixesMessage(): void
    {
        // CborException prepends "Cbor Exception: " to the message
        try {
            CborDecoder::decode("\xe0");
            $this->fail("Expected CborException was not thrown");
        } catch (CborException $e) {
            $this->assertStringStartsWith("Cbor Exception: ", $e->getMessage());
        }
    }

    /**
     * @throws CborException
     */
    public function testDecodeRangeOfUnsupportedSimpleValues(): void
    {
        // All additional values 0–19 for major type 7 should throw
        for ($additional = 0; $additional <= 19; $additional++) {
            $byte = chr(0xe0 | $additional);
            try {
                CborDecoder::decode($byte);
                $this->fail("Expected CborException for additional=$additional (byte=0x" . bin2hex($byte) . ")");
            } catch (CborException $e) {
                $this->assertStringContainsString((string)$additional, $e->getMessage());
            }
        }
    }
}
