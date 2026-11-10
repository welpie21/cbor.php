<?php

namespace Beau\CborPHP\classes;

/**
 * Converts text from UTF-8 to the string encoding of a Cbor instance.
 *
 * @internal
 */
final class StringConverter
{
    /**
     * Convert valid UTF-8 text to $encoding, in a form the encoder reads back as it was.
     *
     * @param string $encoding an mbstring encoding name
     */
    public static function fromUtf8(string $text, string $encoding): string
    {
        return self::keepLeadingCharacter(mb_convert_encoding($text, $encoding, 'UTF-8'), $encoding);
    }

    /**
     * UTF-16 and UTF-32 read a byte order mark (BOM) at the start of a string. Text that
     * starts with U+FEFF, or in UTF-16 with U+FFFE, looks like a BOM: the encoder would
     * drop the character, or turn the byte order around. Such text gets a big-endian BOM
     * before it. Other text, and other encodings, stay as they are.
     *
     * @param string $converted text already converted to $encoding
     */
    public static function keepLeadingCharacter(string $converted, string $encoding): string
    {
        if ($encoding === 'UTF-16' && (str_starts_with($converted, "\xFE\xFF") || str_starts_with($converted, "\xFF\xFE"))) {
            return "\xFE\xFF" . $converted;
        }

        if ($encoding === 'UTF-32' && str_starts_with($converted, "\x00\x00\xFE\xFF")) {
            return "\x00\x00\xFE\xFF" . $converted;
        }

        return $converted;
    }
}
