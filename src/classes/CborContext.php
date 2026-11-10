<?php

namespace Beau\CborPHP\classes;

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\exceptions\CborException;

/**
 * What a tag handler gets with each call: string conversion for the options of the
 * Cbor instance, and nested encoding and decoding that keeps counting the nesting depth.
 *
 * Tag content always comes to a handler in one form, whatever the options: text strings
 * in UTF-8, and byte strings as plain strings. A handler that puts your own strings in
 * its content converts them with toUtf8() and fromUtf8().
 */
final class CborContext
{
    /**
     * @internal The encoder and decoder make the context for each handler call.
     *
     * @param int $depth the nesting depth of the tag that the handler writes or reads
     */
    public function __construct(
        private readonly CborConfig $config,
        private readonly int $depth,
    ) {
    }

    /**
     * Convert one of your strings, in the stringEncoding of the Cbor instance, to UTF-8
     * for the tag content. With UTF-8, the string stays as it is.
     *
     * @throws CborException for a string that is not valid in stringEncoding
     */
    public function toUtf8(string $text): string
    {
        $encoding = $this->config->stringEncoding;

        if ($encoding === null) {
            return $text;
        }

        if (!mb_check_encoding($text, $encoding)) {
            throw new CborException("String is not valid $encoding");
        }

        return mb_convert_encoding($text, 'UTF-8', $encoding);
    }

    /**
     * Convert a UTF-8 string from the tag content to the stringEncoding of the Cbor
     * instance. With UTF-8, the string stays as it is.
     *
     * @throws CborException for a string that is not valid UTF-8
     */
    public function fromUtf8(string $text): string
    {
        $encoding = $this->config->stringEncoding;

        if ($encoding === null) {
            return $text;
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new CborException("Invalid UTF-8 in tag content");
        }

        return StringConverter::fromUtf8($text, $encoding);
    }

    /**
     * Encode a value with the options of the Cbor instance, inside this tag: for a tag
     * that holds encoded CBOR, such as tag 24. The nesting depth keeps counting.
     *
     * @throws CborException
     */
    public function encode(mixed $value): string
    {
        return CborEncoder::encodeWith($value, $this->config, $this->depth);
    }

    /**
     * Decode CBOR bytes with the options of the Cbor instance, inside this tag: for a
     * tag that holds encoded CBOR, such as tag 24. The nesting depth keeps counting, so
     * nested data cannot get past maxDepth.
     *
     * @throws CborException
     */
    public function decode(string $data): mixed
    {
        return CborDecoder::decodeWith($data, $this->config, $this->depth);
    }
}
