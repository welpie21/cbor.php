<?php

namespace Beau\CborPHP;

use Beau\CborPHP\classes\FloatHelper;
use Beau\CborPHP\classes\IntHelper;
use Beau\CborPHP\classes\TaggedValue;
use Beau\CborPHP\exceptions\CborException;
use Beau\CborPHP\interfaces\CborTag;
use Beau\CborPHP\utils\CborByteString;
use Closure;

class CborDecoder
{
    /** The deepest nesting of arrays, maps and tags the decoder accepts by default. */
    public const DEFAULT_MAX_DEPTH = 512;

    private string $buffer;
    private int $length;
    private int $offset;
    private int $depth = 0;

    private static array $byteLength = [
        24 => 1,
        25 => 2,
        26 => 4,
        27 => 8
    ];

    /**
     * One decoder reads one item. Use {@see CborDecoder::decode()}, or
     * {@see Cbor::decode()} for options.
     *
     * @param ?string $stringEncoding the mbstring name of the encoding to convert text
     *                                strings to, or null for UTF-8 (no conversion)
     * @param array<int, CborTag> $tags tag handlers by tag number
     */
    private function __construct(
        string $buffer,
        private readonly bool $byteStrings = false,
        private readonly int $maxDepth = self::DEFAULT_MAX_DEPTH,
        private readonly bool $strict = false,
        private readonly ?string $stringEncoding = null,
        private readonly array $tags = [],
        private readonly ?Closure $reviver = null,
    ) {
        $this->offset = 0;
        $this->buffer = $buffer;
        $this->length = strlen($buffer);
    }

    /**
     * Count one more level of nesting. Without a limit, deeply nested input
     * (a few hundred kilobytes of array openings) exhausts memory or the
     * stack, which PHP cannot recover from.
     *
     * @throws CborException
     */
    private function enter(): void
    {
        if (++$this->depth > $this->maxDepth) {
            throw new CborException("Nesting deeper than {$this->maxDepth} levels at offset {$this->offset}");
        }
    }

    // The bounds checks are written inline and only call these on failure,
    // since a method call per item measurably slows down decoding.
    private function unexpectedEnd(int $byteCount): CborException
    {
        $available = $this->length - $this->offset;
        return new CborException("Unexpected end of data at offset {$this->offset}: need $byteCount byte(s), $available available");
    }

    private function invalidAdditionalInfo(int $additional): CborException
    {
        return new CborException($additional === 31
            ? "Indefinite-length items are not supported"
            : "Invalid additional information: $additional");
    }

    /**
     * @throws CborException
     */
    private function decodeArray(int $additional): array
    {
        $length = $this->decodeInt($additional);
        $array = [];
        $this->enter();

        for ($i = 0; $i < $length; $i++) {
            $array[] = $this->decodeNext();
        }

        $this->depth--;

        return $array;
    }

    /**
     * @throws CborException
     */
    private function decodeInt(int $additional, bool $signed = false): int
    {
        if ($additional <= 23) {
            return $signed ? -($additional + 1) : $additional;
        }

        $byteLength = self::$byteLength[$additional] ?? throw $this->invalidAdditionalInfo($additional);

        if ($this->offset + $byteLength > $this->length) {
            throw $this->unexpectedEnd($byteLength);
        }

        $value = match ($byteLength) {
            1 => IntHelper::getUint8($this->buffer, $this->offset),
            2 => IntHelper::getUint16($this->buffer, $this->offset),
            4 => IntHelper::getUint32($this->buffer, $this->offset),
            // A uint64 at or above 2^63 wraps to a negative int. Rejecting it here also covers
            // string/array/map lengths and tag numbers, which all come from this argument.
            8 => ($value = IntHelper::getUint64($this->buffer, $this->offset)) >= 0
                ? $value
                : throw new CborException("Argument out of range at offset {$this->offset}: exceeds PHP_INT_MAX"),
            default => throw new CborException("Invalid byte length: " . $byteLength . " for additional: " . $additional)
        };

        $value = $signed ? -($value + 1) : $value;
        $this->offset += $byteLength;

        return $value;
    }

    /**
     * @throws CborException
     */
    private function decodeMap(int $additional): array
    {
        $length = $this->decodeInt($additional);
        $map = [];
        $this->enter();

        for ($i = 0; $i < $length; $i++) {
            $offset = $this->offset;
            $key = $this->decodeNext();

            // An array, map, tag or byte string cannot be a PHP array key.
            // Strict mode also refuses the keys PHP would change: a float,
            // bool or null key would silently become an int or "".
            if (!is_int($key) && !is_string($key)
                && ($this->strict || !(is_float($key) || is_bool($key) || $key === null))
            ) {
                throw new CborException("Unsupported map key of type " . get_debug_type($key) . " at offset $offset");
            }

            $value = $this->decodeNext();
            $map[$key] = $value;
        }

        $this->depth--;

        return $map;
    }

    /**
     * @throws CborException
     */
    private function decodeString(int $additional): string
    {
        $length = $this->decodeInt($additional);
        if ($this->offset + $length > $this->length) {
            throw $this->unexpectedEnd($length);
        }
        $result = substr($this->buffer, $this->offset, $length);

        if ($this->stringEncoding !== null) {
            // mbstring writes "?" for an invalid sequence, so check first rather than lose data.
            if (!mb_check_encoding($result, 'UTF-8')) {
                throw new CborException("Invalid UTF-8 in text string at offset {$this->offset}");
            }

            $result = mb_convert_encoding($result, $this->stringEncoding, 'UTF-8');
        }

        $this->offset += $length;
        return $result;
    }

    /**
     * @throws CborException
     */
    private function decodeByteString(int $additional): string|CborByteString
    {
        $length = $this->decodeInt($additional);
        if ($this->offset + $length > $this->length) {
            throw $this->unexpectedEnd($length);
        }
        $result = substr($this->buffer, $this->offset, $length);
        $this->offset += $length;
        return $this->byteStrings ? new CborByteString($result) : $result;
    }

    /**
     * @throws CborException
     */
    private function decodeTag(int $additional): mixed
    {
        $tag = $this->decodeInt($additional);
        $this->enter();
        $value = $this->decodeNext();
        $this->depth--;

        if (isset($this->tags[$tag])) {
            return $this->tags[$tag]->decode($value);
        }

        return $this->reviver === null
            ? new TaggedValue($tag, $value)
            : ($this->reviver)(new TaggedValue($tag, $value));
    }

    /**
     * @throws CborException
     */
    private function decodeFloat(int $additional): float
    {
        $byteLength = self::$byteLength[$additional];
        if ($this->offset + $byteLength > $this->length) {
            throw $this->unexpectedEnd($byteLength);
        }
        $value = match ($byteLength) {
            2 => FloatHelper::getFloat16($this->buffer, $this->offset),
            4 => FloatHelper::getFloat32($this->buffer, $this->offset),
            8 => FloatHelper::getFloat64($this->buffer, $this->offset),
            default => throw new CborException("Invalid byte length: " . $byteLength . " for additional: " . $additional)
        };

        $this->offset += $byteLength;
        return $value;
    }

    /**
     * @throws CborException
     */
    private function decodeSimple(int $additional): bool|null|float
    {
        return match ($additional) {
            20 => false,
            21 => true,
            22, 23 => null,
            25, 26, 27 => $this->decodeFloat($additional),
            default => throw new CborException("Unsupported simple value: $additional")
        };
    }

    /**
     * @throws CborException
     */
    private function decodeNext(): mixed
    {
        $byte = ord($this->buffer[$this->offset] ?? throw $this->unexpectedEnd(1));
        $this->offset++;
        $majorTag = $byte >> 5;
        $additionalInfo = $byte & 0x1F;

        return match ($majorTag) {
            0 => $this->decodeInt($additionalInfo),
            1 => $this->decodeInt($additionalInfo, true),
            2 => $this->decodeByteString($additionalInfo),
            3 => $this->decodeString($additionalInfo),
            4 => $this->decodeArray($additionalInfo),
            5 => $this->decodeMap($additionalInfo),
            6 => $this->decodeTag($additionalInfo),
            7 => $this->decodeSimple($additionalInfo),
            default => throw new CborException("Unsupported major tag: $majorTag")
        };
    }

    /**
     * Decode the item, and in strict mode refuse bytes after it.
     *
     * @throws CborException
     */
    private function decodeAll(): mixed
    {
        $value = $this->decodeNext();

        if ($this->strict && $this->offset !== $this->length) {
            throw new CborException("Unexpected data after the item at offset {$this->offset}");
        }

        return $value;
    }

    /**
     * Decode one CBOR item with the default options: UTF-8 strings, byte strings as plain
     * strings, nesting up to {@see CborDecoder::DEFAULT_MAX_DEPTH}, bytes after the item
     * ignored, and each tagged value as a {@see TaggedValue}. For options, use {@see Cbor}.
     *
     * @throws CborException
     */
    public static function decode(string $data): mixed
    {
        // Before 3.0 this took a replacer and options. PHP ignores extra arguments, so say so loudly.
        if (func_num_args() > 1) {
            throw new CborException("CborDecoder::decode() takes only the data. Give options to new Cbor(...) instead");
        }

        return (new self($data))->decodeAll();
    }

    /**
     * @internal Use {@see Cbor::decode()}.
     * @param array<int, CborTag> $tags
     * @throws CborException
     */
    public static function decodeWith(
        string $data,
        bool $byteStrings,
        int $maxDepth,
        bool $strict,
        ?string $stringEncoding,
        array $tags,
        ?Closure $reviver,
    ): mixed {
        return (new self($data, $byteStrings, $maxDepth, $strict, $stringEncoding, $tags, $reviver))->decodeAll();
    }
}