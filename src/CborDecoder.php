<?php

namespace Beau\CborPHP;

use Beau\CborPHP\classes\CborConfig;
use Beau\CborPHP\classes\CborContext;
use Beau\CborPHP\classes\FloatHelper;
use Beau\CborPHP\classes\IntHelper;
use Beau\CborPHP\classes\StringConverter;
use Beau\CborPHP\classes\TaggedValue;
use Beau\CborPHP\exceptions\CborException;
use Beau\CborPHP\interfaces\CborTag;
use Beau\CborPHP\utils\CborByteString;
use Closure;
use Throwable;

class CborDecoder
{
    /** The deepest nesting of arrays, maps and tags the decoder accepts by default. */
    public const DEFAULT_MAX_DEPTH = 512;

    private string $buffer;
    private int $length;
    private int $offset = 0;

    // The options. Each one has its default here, so the decoder for the default options
    // costs little to set up; decodeWith() sets them for a Cbor instance.

    /** Return byte strings as CborByteString. Off while reading the content of a tag handler. */
    private bool $byteStrings = false;
    private int $maxDepth = self::DEFAULT_MAX_DEPTH;
    private bool $strict = false;

    /**
     * The mbstring name of the encoding to convert text strings to, or null for UTF-8 (no
     * conversion). Off while reading the content of a tag handler.
     */
    private ?string $stringEncoding = null;

    /** @var array<int, CborTag> tag handlers by tag number */
    private array $tags = [];
    private ?Closure $reviver = null;

    /** The options, for the context of tag handlers. */
    private ?CborConfig $config = null;

    /** The current nesting depth: more than 0 at the start for nested decoding. */
    private int $depth = 0;

    /** @var array<int, CborContext> the context for tag handlers, one per nesting depth */
    private array $contexts = [];

    private static array $byteLength = [
        24 => 1,
        25 => 2,
        26 => 4,
        27 => 8
    ];

    /**
     * One decoder reads one item. Use {@see CborDecoder::decode()}, or
     * {@see Cbor::decode()} for options.
     */
    private function __construct(string $buffer)
    {
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

            if (!is_int($key) && !is_string($key)) {
                // An array, map, tag or byte string cannot be a PHP array key.
                // Strict mode also refuses the keys PHP would change: a float,
                // bool or null key would become an int or "".
                if ($this->strict || !(is_float($key) || is_bool($key) || $key === null)) {
                    throw new CborException("Unsupported map key of type " . get_debug_type($key) . " at offset $offset");
                }

                $key = self::toArrayKey($key);
            }

            // PHP would keep only the last value. Strict mode refuses the input instead, also
            // for keys that only PHP makes equal, such as 1 and "1".
            if ($this->strict && array_key_exists($key, $map)) {
                throw new CborException("Duplicate map key at offset $offset");
            }

            $value = $this->decodeNext();
            $map[$key] = $value;
        }

        $this->depth--;

        return $map;
    }

    /**
     * The key PHP makes of a bool, float or null, without the notices PHP raises when it
     * converts one itself. PHP 8.1 and later deprecate a float key that is not a whole
     * number, and PHP 8.5 warns for a float that is not an int and deprecates a null key.
     * A framework error handler turns such a notice into an exception.
     */
    private static function toArrayKey(bool|float|null $key): int|string
    {
        if ($key === null) {
            return '';
        }

        if (is_bool($key)) {
            return (int) $key;
        }

        if (is_nan($key) || is_infinite($key)) {
            return 0;
        }

        // Outside the int range, PHP wraps the value around modulo 2^64. Wrap it first, so
        // the cast below never gets a float that it cannot represent. Floats this large are
        // whole numbers, and every step here is exact.
        if ($key >= 9.2233720368547758E18 || $key < -9.2233720368547758E18) {
            $key = fmod($key, 18446744073709551616.0);

            if ($key >= 9.2233720368547758E18) {
                $key -= 18446744073709551616.0;
            } elseif ($key < -9.2233720368547758E18) {
                $key += 18446744073709551616.0;
            }
        }

        // An explicit cast truncates toward zero, as PHP does for a key, and raises nothing.
        return (int) $key;
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

            // Only UTF-16 and UTF-32 read a byte order mark, so only they need a check.
            if ($this->stringEncoding === 'UTF-16' || $this->stringEncoding === 'UTF-32') {
                $result = StringConverter::keepLeadingCharacter($result, $this->stringEncoding);
            }
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
        $start = $this->offset - 1;
        $tag = $this->decodeInt($additional);
        $this->enter();

        if (isset($this->tags[$tag])) {
            $handler = $this->tags[$tag];

            // A handler gets its content in one form, whatever the options: text in
            // UTF-8, and byte strings as plain strings.
            $stringEncoding = $this->stringEncoding;
            $byteStrings = $this->byteStrings;
            $this->stringEncoding = null;
            $this->byteStrings = false;
            $content = $this->decodeNext();
            $this->stringEncoding = $stringEncoding;
            $this->byteStrings = $byteStrings;

            // The content can be anything the sender wrote. Whatever the handler throws
            // for it, the caller gets a CborException.
            try {
                $value = $handler->decode($content, $this->contexts[$this->depth] ??= new CborContext($this->config, $this->depth));
            } catch (CborException $e) {
                throw $e;
            } catch (Throwable $e) {
                throw new CborException("Tag $tag at offset $start: " . get_debug_type($handler) . "::decode() failed: " . $e->getMessage(), $e);
            }

            $this->depth--;
            return $value;
        }

        $value = $this->decodeNext();
        $this->depth--;

        if ($this->reviver === null) {
            return new TaggedValue($tag, $value);
        }

        try {
            return ($this->reviver)(new TaggedValue($tag, $value));
        } catch (CborException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new CborException("Tag $tag at offset $start: the reviver failed: " . $e->getMessage(), $e);
        }
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
     * @internal Use {@see Cbor::decode()}, or {@see CborContext::decode()} in a tag handler.
     * @param int $depth the nesting depth to start at
     * @throws CborException
     */
    public static function decodeWith(string $data, CborConfig $config, int $depth = 0): mixed
    {
        $decoder = new self($data);
        $decoder->byteStrings = $config->byteStrings;
        $decoder->maxDepth = $config->maxDepth;
        $decoder->strict = $config->strict;
        $decoder->stringEncoding = $config->stringEncoding;
        $decoder->tags = $config->tagsByNumber;
        $decoder->reviver = $config->reviver;
        $decoder->config = $config;
        $decoder->depth = $depth;

        return $decoder->decodeAll();
    }
}