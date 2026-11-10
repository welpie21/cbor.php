<?php

namespace Beau\CborPHP;

use Beau\CborPHP\classes\CborConfig;
use Beau\CborPHP\classes\CborContext;
use Beau\CborPHP\classes\TagRegistry;
use Beau\CborPHP\exceptions\CborException;
use Beau\CborPHP\abstracts\AbstractTaggedValue;
use Beau\CborPHP\utils\CborByteString;
use Beau\CborPHP\utils\CborMap;
use Closure;

class CborEncoder
{
    private string $buffer = "";

    // The options. Each one has its default here, so the encoder for the default options
    // costs nothing to set up; encodeWith() sets them for a Cbor instance.

    /**
     * The mbstring name of the encoding to convert strings from, or null for UTF-8 (no
     * conversion). It is off while the encoder writes the content of a tag handler.
     */
    private ?string $stringEncoding = null;
    private ?TagRegistry $tags = null;
    private ?Closure $replacer = null;
    private int $maxDepth = CborDecoder::DEFAULT_MAX_DEPTH;

    /** The options, for the context of tag handlers. */
    private ?CborConfig $config = null;

    /** The current nesting depth: more than 0 at the start for nested encoding. */
    private int $depth = 0;

    /** @var array<int, CborContext> the context for tag handlers, one per nesting depth */
    private array $contexts = [];

    private static array $lengthPackType = [
        24 => "C",
        25 => "n",
        26 => "N"
    ];

    /**
     * One encoder writes one value. Use {@see CborEncoder::encode()}, or
     * {@see Cbor::encode()} for options.
     */
    private function __construct()
    {
    }

    /**
     * Arrays, maps and tags count one level of nesting each. Without a limit, a value
     * that holds itself (an array with a reference to itself, or a tag handler or
     * replacer that keeps returning new objects) uses up memory, and PHP cannot recover
     * from that. The checks are inline; this is called only when one fails.
     */
    private function tooDeep(): CborException
    {
        return new CborException("Nesting deeper than {$this->maxDepth} levels");
    }

    private function packInitialByte(int $majorType, int $additionalInfo): void
    {
        $this->buffer .= pack("c", $majorType | $additionalInfo);
    }

    private function packNumber(int $majorType, int $value): void
    {
        if ($value <= 23) {
            $this->packInitialByte($majorType, $value);
            return;
        }

        $length = $this->getLength($value);

        if ($length === null) {
            $this->packInitialByte($majorType, 27);
            $this->packBigInt($value);
        } else {
            $this->packInitialByte($majorType, $length);
            $this->buffer .= pack(self::$lengthPackType[$length], $value);
        }
    }

    private function packBigInt(int $value): void
    {
        $this->buffer .= pack("J", $value);
    }

    private function getLength(int $value): ?int
    {
        return match (true) {
            $value < 256 => 24,
            $value < 65536 => 25,
            $value < 4294967296 => 26,
            default => null
        };
    }

    /**
     * @throws CborException
     */
    private function encodeArray(array $array): void
    {
        $arrayLength = count($array);

        if ($this->isAssoc($array, $arrayLength)) {
            $this->encodeMap($array, false);

            return;
        }

        // 0b10000000 = array
        $this->packNumber(0b10000000, $arrayLength);

        if (++$this->depth > $this->maxDepth) {
            throw $this->tooDeep();
        }

        foreach ($array as $value) {
            $this->encodeItem($value);
        }

        $this->depth--;
    }

    /**
     * @param array<int|string, mixed> $map
     * @throws CborException
     */
    private function encodeMap(array $map, bool $stringKeys): void
    {
        $this->packNumber(0b10100000, count($map));

        if (++$this->depth > $this->maxDepth) {
            throw $this->tooDeep();
        }

        foreach ($map as $key => $value) {
            if ($stringKeys && is_int($key)) {
                // Digits from an int are already UTF-8, whatever stringEncoding is.
                $this->packString((string) $key);
            } else {
                $this->encodeItem($key);
            }

            $this->encodeItem($value);
        }

        $this->depth--;
    }

    private function packInt(int $value): void
    {
        if ($value < 0) {
            // -1 - $value, not abs($value) - 1: abs(PHP_INT_MIN) overflows to float.
            $this->packNumber(1 << 5, -1 - $value);
        } else {
            $this->packNumber(0, $value);
        }
    }

    private function packDouble(float $value): void
    {
        if (is_nan($value)) {
            $this->packNaN();
            return;
        }

        $this->packInitialByte(7 << 5, 27);
        $this->buffer .= strrev(pack("d", $value));
    }

    private function isAssoc(array $array, int $length): bool
    {
        return !array_is_list($array);
    }

    /**
     * Convert a PHP string from stringEncoding to UTF-8. Only call it when
     * stringEncoding is set.
     *
     * @throws CborException
     */
    private function toUtf8(string $value): string
    {
        // mbstring writes "?" for an invalid sequence, so check first rather than lose data.
        if (!mb_check_encoding($value, $this->stringEncoding)) {
            throw new CborException("String is not valid {$this->stringEncoding}");
        }

        return mb_convert_encoding($value, 'UTF-8', $this->stringEncoding);
    }

    /**
     * Write the bytes as they are. A text string must already be UTF-8.
     */
    private function packString(string $value, bool $byte = false): void
    {
        $length = strlen($value);

        if ($byte) {
            $this->packNumber(2 << 5, $length);
            $this->buffer .= $value;
        } else {
            $this->packNumber(3 << 5, $length);
            $this->buffer .= $value;
        }
    }

    private function packBoolean(bool $value): void
    {
        $this->packInitialByte(7 << 5, $value ? 21 : 20);
    }

    private function packNull(): void
    {
        $this->packInitialByte(7 << 5, 22);
    }

    private function packNaN(): void
    {
        $this->buffer .= hex2bin("f97e00"); // War crime has been committed here.
    }

    /**
     * Write an object that has no built-in mapping: with its tag handler if it has
     * one, else as the value the replacer returns.
     *
     * @param bool $replaced Whether $value was returned by the replacer. A replaced
     *                       value must be encodable, so the replacer is not called again.
     * @throws CborException
     */
    private function encodeOther(mixed $value, bool $replaced): void
    {
        if ($this->tags !== null && is_object($value)) {
            $tags = $this->tags;
            // Remember the answer for each class, also when there is no handler.
            $entry = $tags->byClass[$value::class] ??= $tags->find($value);

            if ($entry !== false) {
                [$tag, $handler] = $entry;

                if (++$this->depth > $this->maxDepth) {
                    throw $this->tooDeep();
                }

                $content = $handler->encode($value, $this->contexts[$this->depth] ??= new CborContext($this->config, $this->depth));

                // Encoding the object itself again would never end.
                if ($content === $value) {
                    throw new CborException(get_debug_type($handler) . "::encode() returned the object itself. Return the tag content instead");
                }

                $this->packNumber(6 << 5, $tag);

                // The handler made the content, so write its strings as they are.
                $stringEncoding = $this->stringEncoding;
                $this->stringEncoding = null;
                $this->encodeItem($content);
                $this->stringEncoding = $stringEncoding;

                $this->depth--;
                return;
            }
        }

        if ($replaced || $this->replacer === null) {
            throw new CborException("Unsupported type: " . get_debug_type($value));
        }

        $this->encodeItem(($this->replacer)($value), true);
    }

    /**
     * @param bool $replaced Whether $value was returned by the replacer. A replaced
     *                       value must be encodable, so the replacer is not called again.
     * @throws CborException
     */
    private function encodeItem(mixed $value, bool $replaced = false): void
    {
        switch (true) {
            case is_int($value):
                $this->packInt($value);
                break;
            case is_double($value):
                $this->packDouble($value);
                break;
            case is_string($value):
                $this->packString($this->stringEncoding === null ? $value : $this->toUtf8($value));
                break;
            case is_array($value):
                $this->encodeArray($value);
                break;
            case is_bool($value):
                $this->packBoolean($value);
                break;
            case is_null($value):
                $this->packNull();
                break;
            case $value instanceof AbstractTaggedValue:
                // Tag numbers are unsigned (RFC 8949, section 3.4).
                if ($value->tag < 0) {
                    throw new CborException("Tag number must not be negative: {$value->tag}");
                }
                $this->packNumber(6 << 5, $value->tag);

                if (++$this->depth > $this->maxDepth) {
                    throw $this->tooDeep();
                }

                $this->encodeItem($value->value);
                $this->depth--;
                break;
            case $value instanceof CborByteString:
                $this->packString($value->getByteString(), true);
                break;
            case $value instanceof CborMap:
                $this->encodeMap($value->entries, $value->stringKeys);
                break;
            default:
                $this->encodeOther($value, $replaced);
        }
    }

    /**
     * Encode a value with the default options: UTF-8 strings, no tag handlers, no
     * replacer, and nesting up to {@see CborDecoder::DEFAULT_MAX_DEPTH}. For options,
     * use {@see Cbor}.
     *
     * @throws CborException
     */
    public static function encode(mixed $value): string
    {
        // Before 3.0 this took a replacer. PHP ignores extra arguments, so say so loudly.
        if (func_num_args() > 1) {
            throw new CborException("CborEncoder::encode() takes only the value. Give options to new Cbor(...) instead");
        }

        $encoder = new self();
        $encoder->encodeItem($value);
        return $encoder->buffer;
    }

    /**
     * @internal Use {@see Cbor::encode()}, or {@see CborContext::encode()} in a tag handler.
     * @param int $depth the nesting depth to start at
     * @throws CborException
     */
    public static function encodeWith(mixed $value, CborConfig $config, int $depth = 0): string
    {
        $encoder = new self();
        $encoder->stringEncoding = $config->stringEncoding;
        $encoder->tags = $config->tags;
        $encoder->replacer = $config->replacer;
        $encoder->maxDepth = $config->maxDepth;
        $encoder->config = $config;
        $encoder->depth = $depth;
        $encoder->encodeItem($value);
        return $encoder->buffer;
    }
}