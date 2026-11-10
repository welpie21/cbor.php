<?php

namespace Beau\CborPHP\enums;

/**
 * The encoding of the PHP strings that the encoder reads and the decoder returns.
 *
 * A CBOR text string is always UTF-8 (RFC 8949, section 3.1), so this does not
 * change the bytes on the wire. With an encoding other than UTF-8, the encoder
 * converts each PHP string to UTF-8, and the decoder converts each text string
 * from UTF-8. Byte strings are never converted.
 *
 * The cases are Unicode encodings only, so a conversion never loses a character.
 * Each value is the name mbstring uses for the encoding.
 *
 * UTF16 and UTF32 read a byte order mark (BOM). To start a string with U+FEFF, put a
 * BOM before it. The decoder does that for the strings it returns.
 */
enum StringEncoding: string
{
    case UTF8 = 'UTF-8';

    /**
     * Reads a byte order mark if there is one, else big-endian. Writes big-endian, with no
     * byte order mark, except before text that starts with U+FEFF or U+FFFE.
     */
    case UTF16 = 'UTF-16';
    case UTF16BE = 'UTF-16BE';
    case UTF16LE = 'UTF-16LE';

    /**
     * Reads a byte order mark if there is one, else big-endian. Writes big-endian, with no
     * byte order mark, except before text that starts with U+FEFF.
     */
    case UTF32 = 'UTF-32';
    case UTF32BE = 'UTF-32BE';
    case UTF32LE = 'UTF-32LE';
}
