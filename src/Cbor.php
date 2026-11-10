<?php

// Strict types, so that with() checks the option types as the constructor does.
declare(strict_types=1);

namespace Beau\CborPHP;

use Beau\CborPHP\classes\CborConfig;
use Beau\CborPHP\classes\TaggedValue;
use Beau\CborPHP\classes\TagRegistry;
use Beau\CborPHP\enums\StringEncoding;
use Beau\CborPHP\exceptions\CborException;
use Beau\CborPHP\interfaces\CborTag;
use Closure;
use ReflectionFunction;

/**
 * An encoder and a decoder that share one configuration.
 *
 * Configure it once, then call encode() and decode() as often as you like. The
 * options are checked when you create the instance, not on each call. An instance
 * does not change: with() and withTags() return a new one.
 *
 * ```php
 * $cbor = new Cbor(
 *     tags: [new MoneyTag()],
 *     stringEncoding: StringEncoding::UTF16LE,
 * );
 *
 * $bytes = $cbor->encode($value);
 * $value = $cbor->decode($bytes);
 * ```
 */
final class Cbor
{
    /** The option names, in the order of the constructor. */
    private const OPTIONS = ['tags', 'stringEncoding', 'replacer', 'reviver', 'byteStrings', 'maxDepth', 'strict'];

    /** @var list<CborTag> */
    private readonly array $tags;

    /** The options in the form the encoder and decoder use. */
    private readonly CborConfig $config;

    /**
     * @param list<CborTag> $tags handlers for custom tags. See {@see CborTag}.
     * @param ?StringEncoding $stringEncoding the encoding of your PHP strings. The encoder
     *                                        converts them to UTF-8, and the decoder converts
     *                                        text strings back. Null means UTF-8.
     * @param ?Closure(mixed): mixed $replacer called by the encoder for a value that has no
     *                                         CBOR mapping and no tag handler. Return a value
     *                                         the encoder supports.
     * @param ?Closure(TaggedValue): mixed $reviver called by the decoder for a tagged value
     *                                              that has no tag handler. Its return value
     *                                              replaces the tagged value.
     * @param bool $byteStrings decode a byte string as a {@see utils\CborByteString}, not a
     *                          plain string. Tag handlers always get plain strings.
     * @param int $maxDepth the deepest nesting of arrays, maps and tags that the encoder
     *                      writes and the decoder accepts
     * @param bool $strict make the decoder refuse bytes after the item, and map keys other
     *                     than an int or a string
     * @throws CborException for an invalid tag handler, or a string encoding other than
     *                       UTF-8 without the mbstring extension
     */
    public function __construct(
        array $tags = [],
        private readonly ?StringEncoding $stringEncoding = StringEncoding::UTF8,
        private readonly ?Closure $replacer = null,
        private readonly ?Closure $reviver = null,
        private readonly bool $byteStrings = false,
        private readonly int $maxDepth = CborDecoder::DEFAULT_MAX_DEPTH,
        private readonly bool $strict = false,
    ) {
        self::checkCallback($replacer, 'The replacer must take one argument, the value. A 2.x replacer took ($key, $value): remove $key');
        self::checkCallback($reviver, 'The reviver must take one argument, the tagged value. A 2.x decoder replacer took ($key, $value): remove $key');

        $this->tags = array_values($tags);
        $registry = $this->tags === [] ? null : new TagRegistry($this->tags);

        if ($stringEncoding === null || $stringEncoding === StringEncoding::UTF8) {
            $mbEncoding = null;
        } elseif (!function_exists('mb_convert_encoding')) {
            throw new CborException("The mbstring extension is required for stringEncoding {$stringEncoding->value}");
        } else {
            $mbEncoding = $stringEncoding->value;
        }

        $this->config = new CborConfig(
            stringEncoding: $mbEncoding,
            tags: $registry,
            tagsByNumber: $registry?->byNumber ?? [],
            replacer: $replacer,
            reviver: $reviver,
            byteStrings: $byteStrings,
            maxDepth: $maxDepth,
            strict: $strict,
        );
    }

    /**
     * Encode a value and return the CBOR bytes.
     *
     * @throws CborException
     */
    public function encode(mixed $value): string
    {
        return CborEncoder::encodeWith($value, $this->config);
    }

    /**
     * Decode one CBOR item.
     *
     * @throws CborException
     */
    public function decode(string $data): mixed
    {
        return CborDecoder::decodeWith($data, $this->config);
    }

    /**
     * Return a copy with some options changed. Give the options by name, as for the
     * constructor. The options you leave out keep their value. The types are checked as
     * for the constructor: a value of the wrong type throws a TypeError.
     *
     * ```php
     * $strict = $cbor->with(strict: true, maxDepth: 64);
     * ```
     *
     * @throws CborException for an unknown option, or an option given by position
     */
    public function with(mixed ...$options): self
    {
        foreach ($options as $name => $_) {
            if (!in_array($name, self::OPTIONS, true)) {
                throw new CborException(is_int($name)
                    ? "Give the options of with() by name, for example with(strict: true)"
                    : "Unknown option: $name. The options are: " . implode(', ', self::OPTIONS));
            }
        }

        return new self(...[...$this->options(), ...$options]);
    }

    /**
     * Return a copy with more tag handlers, after the ones this instance has.
     *
     * @throws CborException
     */
    public function withTags(CborTag ...$tags): self
    {
        return $this->with(tags: [...$this->tags, ...$tags]);
    }

    /**
     * The encoder and decoder call a callback with one argument. One that needs two, as in
     * 2.x, would fail only when it is first called, so refuse it now.
     *
     * @throws CborException
     */
    private static function checkCallback(?Closure $callback, string $message): void
    {
        if ($callback !== null && (new ReflectionFunction($callback))->getNumberOfRequiredParameters() > 1) {
            throw new CborException($message);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'tags' => $this->tags,
            'stringEncoding' => $this->stringEncoding,
            'replacer' => $this->replacer,
            'reviver' => $this->reviver,
            'byteStrings' => $this->byteStrings,
            'maxDepth' => $this->maxDepth,
            'strict' => $this->strict,
        ];
    }
}
