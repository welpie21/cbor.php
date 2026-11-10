<?php

use Beau\CborPHP\Cbor;
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
        $decoded = (new Cbor(byteStrings: true))->decode(hex2bin('4200ff'));

        $this->assertInstanceOf(CborByteString::class, $decoded);
        $this->assertSame("\x00\xff", $decoded->getByteString());
    }

    public function testTextStringsStayStringsWithByteStrings(): void
    {
        $this->assertSame('hi', (new Cbor(byteStrings: true))->decode(hex2bin('626869')));
    }

    public function testNestedByteStringsRoundTrip(): void
    {
        $value = ['a' => new CborByteString("\x01\x02"), 'b' => [new TaggedValue(37, new CborByteString(str_repeat("\x00", 16)))]];

        $decoded = (new Cbor(byteStrings: true))->decode(CborEncoder::encode($value));

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
        $this->assertSame([[1]], (new Cbor(maxDepth: 2))->decode(hex2bin('818101')));

        $this->expectException(CborException::class);
        (new Cbor(maxDepth: 1))->decode(hex2bin('818101'));
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

        (new Cbor(strict: true))->decode(hex2bin('0102'));
    }

    public function testStrictAcceptsExactlyOneItem(): void
    {
        $this->assertSame(['a' => 1, 2 => 'b'], (new Cbor(strict: true))->decode(hex2bin('a2616101026162')));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function duplicateKeys(): iterable
    {
        // {"role": "user", "role": "admin"}: the second key starts at offset 11.
        yield 'the same text key' => ['a264726f6c656475736572' . '64726f6c656561646d696e', 11];
        // {1: "a", "1": "b"}: PHP stores both as the key 1.
        yield 'keys that PHP makes equal' => ['a2016161' . '61316162', 4];
    }

    #[DataProvider('duplicateKeys')]
    public function testStrictRefusesDuplicateKeys(string $hex, int $offset): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage("Duplicate map key at offset $offset");

        (new Cbor(strict: true))->decode(hex2bin($hex));
    }

    public function testDuplicateKeysKeepTheLastValueByDefault(): void
    {
        $this->assertSame(['role' => 'admin'], CborDecoder::decode(hex2bin('a264726f6c656475736572' . '64726f6c656561646d696e')));
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

        (new Cbor(strict: true))->decode(hex2bin($hex));
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
        (new Cbor(byteStrings: true))->decode(hex2bin('a1416b01'));
    }
}
