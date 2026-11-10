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
     * A map with one key and the value 1, where the key is a 64-bit float.
     */
    private static function floatKey(float $key): string
    {
        return "\xa1\xfb" . pack('E', $key) . "\x01";
    }

    /**
     * @return iterable<string, array{string, int|string}>
     */
    public static function keysPhpConverts(): iterable
    {
        yield 'a fraction' => [self::floatKey(1.5), 1];
        yield 'a negative fraction' => [self::floatKey(-1.5), -1];
        yield 'a half-precision fraction' => [hex2bin('a1f93e0001'), 1];
        yield 'NAN' => [self::floatKey(NAN), 0];
        yield 'INF' => [self::floatKey(INF), 0];
        yield '-INF' => [self::floatKey(-INF), 0];
        // Outside the int range, PHP wraps around modulo 2^64.
        yield '2^63' => [self::floatKey(9.2233720368547758E18), PHP_INT_MIN];
        yield '1e19' => [self::floatKey(1e19), -8446744073709551616];
        yield '-1e19' => [self::floatKey(-1e19), 8446744073709551616];
        yield 'null' => [hex2bin('a1f601'), ''];
        yield 'undefined' => [hex2bin('a1f701'), ''];
        yield 'false' => [hex2bin('a1f401'), 0];
    }

    #[DataProvider('keysPhpConverts')]
    public function testKeysPhpWouldChangeConvertWithoutNotices(string $data, int|string $key): void
    {
        // PHP deprecates or warns for some of these keys. A framework error handler would
        // turn that into an exception from decode().
        $notices = [];
        set_error_handler(function (int $level, string $message) use (&$notices): bool {
            $notices[] = $message;
            return true;
        });

        try {
            $decoded = CborDecoder::decode($data);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $notices);
        $this->assertSame([$key => 1], $decoded);
    }

    public function testFloatKeysGetTheKeyPhpWouldGive(): void
    {
        mt_srand(8949);
        $floats = [
            9.2233720368547758E18, -9.2233720368547758E18, 1.8446744073709552E19, -1.8446744073709552E19,
            2.7670116110564327E19, -2.7670116110564327E19, 1e300, -1e300, 0.5, -0.5, -0.0, 4.5E15,
        ];
        // Random bit patterns: any exponent, also NAN, INF and subnormal numbers.
        for ($i = 0; $i < 3000; $i++) {
            $floats[] = unpack('E', pack('n4', mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF)))[1];
        }

        foreach ($floats as $float) {
            // PHP's own conversion, with its notices silenced.
            set_error_handler(fn() => true);
            $array = [];
            $array[$float] = 1;
            restore_error_handler();

            $decoded = CborDecoder::decode(self::floatKey($float));

            $this->assertSame(array_key_first($array), array_key_first($decoded), sprintf('key %.17g', $float));
        }
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
