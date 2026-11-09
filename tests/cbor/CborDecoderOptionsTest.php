<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\classes\TaggedValue;
use Beau\CborPHP\exceptions\CborException;
use Beau\CborPHP\utils\CborByteString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CborDecoderOptionsTest extends TestCase
{
    // --- byteStrings ---

    public function testByteStringsDecodeAsPlainStringsByDefault(): void
    {
        $this->assertSame("\x00\xff", CborDecoder::decode(hex2bin('4200ff')));
    }

    public function testByteStringsCanDecodeAsCborByteString(): void
    {
        $decoded = CborDecoder::decode(hex2bin('4200ff'), byteStrings: true);

        $this->assertInstanceOf(CborByteString::class, $decoded);
        $this->assertSame("\x00\xff", $decoded->getByteString());
    }

    public function testTextStringsStayStringsWithByteStrings(): void
    {
        $this->assertSame('hi', CborDecoder::decode(hex2bin('626869'), byteStrings: true));
    }

    public function testNestedByteStringsRoundTrip(): void
    {
        $value = ['a' => new CborByteString("\x01\x02"), 'b' => [new TaggedValue(37, new CborByteString(str_repeat("\x00", 16)))]];

        $decoded = CborDecoder::decode(CborEncoder::encode($value), byteStrings: true);

        $this->assertEquals($value, $decoded);
    }

    // --- maxDepth ---

    public function testNestingUpToTheLimitDecodes(): void
    {
        // 512 nested arrays around 0.
        $data = str_repeat("\x81", CborDecoder::DEFAULT_MAX_DEPTH) . "\x00";

        $value = CborDecoder::decode($data);

        for ($i = 0; $i < CborDecoder::DEFAULT_MAX_DEPTH; $i++) {
            $value = $value[0];
        }

        $this->assertSame(0, $value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function tooDeep(): iterable
    {
        yield 'arrays' => [str_repeat("\x81", CborDecoder::DEFAULT_MAX_DEPTH + 1) . "\x00"];
        yield 'maps' => [str_repeat("\xa1\x00", CborDecoder::DEFAULT_MAX_DEPTH + 1) . "\x00"];
        yield 'tags' => [str_repeat("\xc1", CborDecoder::DEFAULT_MAX_DEPTH + 1) . "\x00"];
        // Never closed: what a hostile peer sends to exhaust memory or the stack.
        yield 'a few hundred kilobytes of openings' => [str_repeat("\x81", 200_000)];
    }

    #[DataProvider('tooDeep')]
    public function testNestingPastTheLimitIsRefused(string $data): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage('Nesting deeper than 512 levels');

        CborDecoder::decode($data);
    }

    public function testTheLimitCanBeChanged(): void
    {
        $this->assertSame([[1]], CborDecoder::decode(hex2bin('818101'), maxDepth: 2));

        $this->expectException(CborException::class);
        CborDecoder::decode(hex2bin('818101'), maxDepth: 1);
    }

    // --- strict ---

    public function testTrailingBytesAreIgnoredByDefault(): void
    {
        $this->assertSame(1, CborDecoder::decode(hex2bin('0102')));
    }

    public function testStrictRefusesTrailingBytes(): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage('Unexpected data after the item at offset 1');

        CborDecoder::decode(hex2bin('0102'), strict: true);
    }

    public function testStrictAcceptsExactlyOneItem(): void
    {
        $this->assertSame(['a' => 1, 2 => 'b'], CborDecoder::decode(hex2bin('a2616101026162'), strict: true));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function scalarKeysPhpWouldChange(): iterable
    {
        yield 'bool' => ['a1f501'];
        yield 'null' => ['a1f601'];
        yield 'float' => ['a1fb3ff800000000000001'];
    }

    #[DataProvider('scalarKeysPhpWouldChange')]
    public function testStrictRefusesKeysPhpWouldChange(string $hex): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage('Unsupported map key');

        CborDecoder::decode(hex2bin($hex), strict: true);
    }

    public function testBoolKeysStillBecomeIntsByDefault(): void
    {
        $this->assertSame([1 => 1], CborDecoder::decode(hex2bin('a1f501')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function keysThatCannotBeArrayKeys(): iterable
    {
        yield 'array' => ['a18001'];
        yield 'map' => ['a1a001'];
        yield 'tag' => ['a1c10001'];
    }

    #[DataProvider('keysThatCannotBeArrayKeys')]
    public function testKeysThatCannotBePhpArrayKeysThrowACborException(string $hex): void
    {
        // These were a TypeError ("Illegal offset type") before.
        $this->expectException(CborException::class);
        $this->expectExceptionMessage('Unsupported map key');

        CborDecoder::decode(hex2bin($hex));
    }

    public function testAByteStringKeyIsRefusedWhenByteStringsAreObjects(): void
    {
        $this->assertSame(['k' => 1], CborDecoder::decode(hex2bin('a1416b01')));

        $this->expectException(CborException::class);
        CborDecoder::decode(hex2bin('a1416b01'), byteStrings: true);
    }
}
