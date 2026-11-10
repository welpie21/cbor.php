<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\classes\TaggedValue;
use Beau\CborPHP\enums\StringEncoding;
use Beau\CborPHP\exceptions\CborException;
use Beau\CborPHP\utils\CborByteString;
use Beau\CborPHP\utils\CborMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The stringEncoding option sets the encoding of the PHP strings. CBOR text
 * strings stay UTF-8 on the wire (RFC 8949, section 3.1).
 */
class CborStringEncodingTest extends TestCase
{
    // --- default (UTF-8) ---

    public function testUtf8IsTheDefaultAndWritesTheBytesAsTheyAre(): void
    {
        $this->assertSame('6368c3a9', bin2hex(CborEncoder::encode("h\u{e9}")));
        $this->assertSame("h\u{e9}", CborDecoder::decode(hex2bin('6368c3a9')));
    }

    public function testUtf8DoesNotValidate(): void
    {
        // Unchanged behaviour: without a conversion, invalid UTF-8 passes through.
        $this->assertSame('61ff', bin2hex(CborEncoder::encode("\xff")));
        $this->assertSame("\xff", CborDecoder::decode(hex2bin('61ff')));
    }

    /**
     * @return iterable<string, array{?StringEncoding}>
     */
    public static function utf8(): iterable
    {
        yield 'null' => [null];
        yield 'UTF8' => [StringEncoding::UTF8];
    }

    #[DataProvider('utf8')]
    public function testNullAndUtf8AreTheDefault(?StringEncoding $encoding): void
    {
        $this->assertSame('61ff', bin2hex(CborEncoder::encode("\xff", stringEncoding: $encoding)));
        $this->assertSame("\xff", CborDecoder::decode(hex2bin('61ff'), stringEncoding: $encoding));
    }

    // --- exact bytes ---

    /**
     * @return iterable<string, array{StringEncoding, string}>
     */
    public static function hInEachEncoding(): iterable
    {
        // "hé" in each encoding. On the wire it is always 63 68 c3 a9.
        yield 'UTF-16' => [StringEncoding::UTF16, "\x00h\x00\xe9"];
        yield 'UTF-16BE' => [StringEncoding::UTF16BE, "\x00h\x00\xe9"];
        yield 'UTF-16LE' => [StringEncoding::UTF16LE, "h\x00\xe9\x00"];
        yield 'UTF-32' => [StringEncoding::UTF32, "\x00\x00\x00h\x00\x00\x00\xe9"];
        yield 'UTF-32BE' => [StringEncoding::UTF32BE, "\x00\x00\x00h\x00\x00\x00\xe9"];
        yield 'UTF-32LE' => [StringEncoding::UTF32LE, "h\x00\x00\x00\xe9\x00\x00\x00"];
    }

    #[DataProvider('hInEachEncoding')]
    public function testEncoderWritesUtf8(StringEncoding $encoding, string $php): void
    {
        $this->assertSame('6368c3a9', bin2hex(CborEncoder::encode($php, stringEncoding: $encoding)));
    }

    #[DataProvider('hInEachEncoding')]
    public function testDecoderReturnsTheEncoding(StringEncoding $encoding, string $php): void
    {
        $this->assertSame(bin2hex($php), bin2hex(CborDecoder::decode(hex2bin('6368c3a9'), stringEncoding: $encoding)));
    }

    public function testUtf16ReadsAByteOrderMark(): void
    {
        $this->assertSame('6368c3a9', bin2hex(CborEncoder::encode("\xff\xfeh\x00\xe9\x00", stringEncoding: StringEncoding::UTF16)));
        $this->assertSame('6368c3a9', bin2hex(CborEncoder::encode("\xfe\xff\x00h\x00\xe9", stringEncoding: StringEncoding::UTF16)));
    }

    public function testUtf32ReadsAByteOrderMark(): void
    {
        $this->assertSame('6168', bin2hex(CborEncoder::encode("\xff\xfe\x00\x00h\x00\x00\x00", stringEncoding: StringEncoding::UTF32)));
    }

    public function testTheLengthCountsUtf8Bytes(): void
    {
        // 24 characters are 48 bytes in UTF-16LE, but 24 bytes in UTF-8: header 78 18.
        $php = mb_convert_encoding(str_repeat('a', 24), 'UTF-16LE', 'UTF-8');

        $encoded = CborEncoder::encode($php, stringEncoding: StringEncoding::UTF16LE);

        $this->assertSame('7818' . str_repeat('61', 24), bin2hex($encoded));
    }

    public function testAnEmptyStringStaysEmpty(): void
    {
        $this->assertSame('60', bin2hex(CborEncoder::encode('', stringEncoding: StringEncoding::UTF16LE)));
        $this->assertSame('', CborDecoder::decode(hex2bin('60'), stringEncoding: StringEncoding::UTF16LE));
    }

    // --- round trip ---

    /**
     * @return iterable<string, array{StringEncoding}>
     */
    public static function encodings(): iterable
    {
        foreach (StringEncoding::cases() as $encoding) {
            yield $encoding->value => [$encoding];
        }
    }

    #[DataProvider('encodings')]
    public function testRoundTrip(StringEncoding $encoding): void
    {
        // Includes an emoji, which is a surrogate pair in UTF-16.
        $text = "Grüße, 世界 \u{1F600}";
        $php = mb_convert_encoding($text, $encoding->value, 'UTF-8');

        $encoded = CborEncoder::encode([$php], stringEncoding: $encoding);

        $this->assertSame([$text], CborDecoder::decode($encoded), 'the wire holds UTF-8');
        $this->assertSame([$php], CborDecoder::decode($encoded, stringEncoding: $encoding));
    }

    // --- where it applies ---

    public function testMapKeysAreConverted(): void
    {
        $php = ["k\x00" => "v\x00"];

        $encoded = CborEncoder::encode($php, stringEncoding: StringEncoding::UTF16LE);

        $this->assertSame('a1616b6176', bin2hex($encoded));
        $this->assertSame($php, CborDecoder::decode($encoded, stringEncoding: StringEncoding::UTF16LE));
    }

    public function testIntKeysWrittenAsTextAreNotConverted(): void
    {
        // The "1" comes from the int 1, not from the caller, so it is not UTF-16.
        $map = new CborMap([1 => true], stringKeys: true);

        $this->assertSame('a16131f5', bin2hex(CborEncoder::encode($map, stringEncoding: StringEncoding::UTF16LE)));
    }

    public function testIntKeysStayInts(): void
    {
        $encoded = CborEncoder::encode([1 => "a\x00"], stringEncoding: StringEncoding::UTF16LE);

        $this->assertSame('a1016161', bin2hex($encoded));
    }

    public function testStringsInATaggedValueAreConverted(): void
    {
        $encoded = CborEncoder::encode(new TaggedValue(32, "h\x00\xe9\x00"), stringEncoding: StringEncoding::UTF16LE);

        $this->assertSame('d8206368c3a9', bin2hex($encoded));

        $decoded = CborDecoder::decode($encoded, stringEncoding: StringEncoding::UTF16LE);
        $this->assertSame("h\x00\xe9\x00", $decoded->value);
    }

    public function testByteStringsAreNotConvertedWhenEncoding(): void
    {
        $encoded = CborEncoder::encode(new CborByteString("\xff\x00\x01"), stringEncoding: StringEncoding::UTF16LE);

        $this->assertSame('43ff0001', bin2hex($encoded));
    }

    public function testByteStringsAreNotConvertedWhenDecoding(): void
    {
        $this->assertSame("\xff\x00\x01", CborDecoder::decode(hex2bin('43ff0001'), stringEncoding: StringEncoding::UTF16LE));

        $decoded = CborDecoder::decode(hex2bin('43ff0001'), byteStrings: true, stringEncoding: StringEncoding::UTF16LE);
        $this->assertSame("\xff\x00\x01", $decoded->getByteString());
    }

    // --- invalid input ---

    /**
     * @return iterable<string, array{StringEncoding, string}>
     */
    public static function invalidPhpStrings(): iterable
    {
        yield 'odd length UTF-16LE' => [StringEncoding::UTF16LE, 'a'];
        yield 'lone surrogate UTF-16LE' => [StringEncoding::UTF16LE, "\x00\xd8"];
        yield 'lone surrogate UTF-16BE' => [StringEncoding::UTF16BE, "\xd8\x00"];
        yield 'length not a multiple of 4 UTF-32LE' => [StringEncoding::UTF32LE, "h\x00"];
        yield 'past U+10FFFF UTF-32BE' => [StringEncoding::UTF32BE, "\x00\x11\x00\x00"];
    }

    #[DataProvider('invalidPhpStrings')]
    public function testEncoderRefusesAStringNotInTheEncoding(StringEncoding $encoding, string $php): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage("String is not valid {$encoding->value}");

        CborEncoder::encode(['ok' => $php], stringEncoding: $encoding);
    }

    public function testDecoderRefusesInvalidUtf8WhenItConverts(): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage('Invalid UTF-8 in text string at offset 4');

        // {"a": "\xff\x00"}
        CborDecoder::decode(hex2bin('a1616162ff00'), stringEncoding: StringEncoding::UTF16LE);
    }
}
