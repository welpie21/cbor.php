<?php

namespace Beau\CborPHP;

use Beau\CborPHP\classes\TaggedValue;
use Beau\CborPHP\classes\TagRegistry;
use Beau\CborPHP\enums\StringEncoding;
use Beau\CborPHP\exceptions\CborException;
use Beau\CborPHP\interfaces\CborTag;
use Closure;

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

    private readonly ?TagRegistry $registry;

    /** @var array<int, CborTag> */
    private readonly array $tagsByNumber;

    /** The mbstring name of the string encoding, or null for UTF-8 (no conversion). */
    private readonly ?string $mbEncoding;

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
     *                          plain string
     * @param int $maxDepth the deepest nesting of arrays, maps and tags the decoder accepts
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
        $this->tags = array_values($tags);
        $this->registry = $this->tags === [] ? null : new TagRegistry($this->tags);
        $this->tagsByNumber = $this->registry?->byNumber ?? [];

        if ($stringEncoding === null || $stringEncoding === StringEncoding::UTF8) {
            $this->mbEncoding = null;
        } elseif (!function_exists('mb_convert_encoding')) {
            throw new CborException("The mbstring extension is required for stringEncoding {$stringEncoding->value}");
        } else {
            $this->mbEncoding = $stringEncoding->value;
        }
    }

    /**
     * Encode a value and return the CBOR bytes.
     *
     * @throws CborException
     */
    public function encode(mixed $value): string
    {
        return CborEncoder::encodeWith($value, $this->mbEncoding, $this->registry, $this->replacer);
    }

    /**
     * Decode one CBOR item.
     *
     * @throws CborException
     */
    public function decode(string $data): mixed
    {
        return CborDecoder::decodeWith(
            $data,
            $this->byteStrings,
            $this->maxDepth,
            $this->strict,
            $this->mbEncoding,
            $this->tagsByNumber,
            $this->reviver,
        );
    }

    /**
     * Return a copy with some options changed. Give the options by name, as for the
     * constructor. The options you leave out keep their value.
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
