<?php

use Beau\CborPHP\Cbor;
use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\classes\TaggedValue;
use Beau\CborPHP\enums\StringEncoding;
use Beau\CborPHP\exceptions\CborException;
use Beau\CborPHP\interfaces\CborTag;
use Beau\CborPHP\utils\CborByteString;
use Beau\CborPHP\utils\CborMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Writes an ArrayObject or ArrayIterator as a tagged array. */
final class ConfigTestArrayTag implements CborTag
{
    public function __construct(private readonly int $tag, private readonly string $class) {}

    public function tag(): int
    {
        return $this->tag;
    }

    public function class(): string
    {
        return $this->class;
    }

    public function encode(object $value): array
    {
        return $value->getArrayCopy();
    }

    public function decode(mixed $content): object
    {
        return new $this->class($content);
    }
}

/**
 * Tests the Cbor configuration object: its defaults, with() and withTags().
 */
class CborTest extends TestCase
{
    private string $marker = 'from the test case';

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function values(): iterable
    {
        yield 'int' => [-1000];
        yield 'float' => [1.5];
        yield 'floats' => [[0.1, -2.5, INF]];
        yield 'string' => ['héllo'];
        yield 'list' => [[1, 'a', null, true, false]];
        yield 'map' => [['a' => 1, 2 => ['b' => []]]];
        yield 'CborMap' => [new CborMap([1 => 'a'], stringKeys: true)];
        yield 'byte string' => [new CborByteString("\x00\xff")];
        yield 'tagged value' => [new TaggedValue(32, 'https://example.com')];
    }

    #[DataProvider('values')]
    public function testTheDefaultsMatchTheStaticShortcuts(mixed $value): void
    {
        $cbor = new Cbor();
        $encoded = CborEncoder::encode($value);

        $this->assertSame($encoded, $cbor->encode($value));
        $this->assertEquals(CborDecoder::decode($encoded), $cbor->decode($encoded));
    }

    public function testOneInstanceConfiguresBothDirections(): void
    {
        $cbor = new Cbor(stringEncoding: StringEncoding::UTF16LE);
        $value = ["k\x00" => "h\x00\xe9\x00"];

        $encoded = $cbor->encode($value);

        // {"k": "hé"} in UTF-8.
        $this->assertSame('a1616b6368c3a9', bin2hex($encoded));
        $this->assertSame($value, $cbor->decode($encoded));
    }

    public function testAnInstanceCanBeUsedManyTimes(): void
    {
        $cbor = new Cbor(strict: true);

        for ($i = 0; $i < 3; $i++) {
            $this->assertSame([$i], $cbor->decode($cbor->encode([$i])));
        }
    }

    // --- with() ---

    public function testWithChangesOnlyTheGivenOptions(): void
    {
        $cbor = new Cbor(maxDepth: 2, strict: true);

        $copy = $cbor->with(maxDepth: 1);

        // The copy keeps strict mode, with the new depth limit.
        $this->assertSame([1], $copy->decode(hex2bin('8101')));
        $this->assertThrows(fn() => $copy->decode(hex2bin('818101')), 'Nesting deeper than 1 levels');
        $this->assertThrows(fn() => $copy->decode(hex2bin('0102')), 'Unexpected data after the item');

        // The original does not change.
        $this->assertSame([[1]], $cbor->decode(hex2bin('818101')));
    }

    public function testWithKeepsTheTags(): void
    {
        $cbor = (new Cbor(tags: [new ConfigTestArrayTag(21, ArrayObject::class)]))->with(strict: true);

        $this->assertSame('d58101', bin2hex($cbor->encode(new ArrayObject([1]))));
    }

    public function testWithCanRemoveACallback(): void
    {
        $cbor = (new Cbor(replacer: fn($value) => 'replaced'))->with(replacer: null);

        $this->expectException(CborException::class);
        $this->expectExceptionMessage('Unsupported type: stdClass');

        $cbor->encode(new stdClass());
    }

    public function testWithNullStringEncodingMeansUtf8(): void
    {
        $cbor = (new Cbor(stringEncoding: StringEncoding::UTF16LE))->with(stringEncoding: null);

        $this->assertSame('61ff', bin2hex($cbor->encode("\xff")));
    }

    public function testWithRefusesAnUnknownOption(): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage('Unknown option: strcit. The options are: tags, stringEncoding, replacer, reviver, byteStrings, maxDepth, strict');

        (new Cbor())->with(strcit: true);
    }

    public function testWithRefusesAnOptionByPosition(): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage('Give the options of with() by name, for example with(strict: true)');

        (new Cbor())->with(true);
    }

    // --- withTags() ---

    public function testWithTagsAddsHandlers(): void
    {
        $base = new Cbor(tags: [new ConfigTestArrayTag(21, ArrayObject::class)]);

        $cbor = $base->withTags(new ConfigTestArrayTag(22, ArrayIterator::class));

        $this->assertSame('d58101', bin2hex($cbor->encode(new ArrayObject([1]))));
        $this->assertSame('d68101', bin2hex($cbor->encode(new ArrayIterator([1]))));
        $this->assertEquals(new ArrayIterator([1]), $cbor->decode(hex2bin('d68101')));

        // The base instance does not change.
        $this->assertThrows(fn() => $base->encode(new ArrayIterator([1])), 'Unsupported type: ArrayIterator');
    }

    public function testWithTagsRefusesASecondHandlerForATag(): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage('Tag 21 has two handlers: ConfigTestArrayTag and ConfigTestArrayTag');

        (new Cbor(tags: [new ConfigTestArrayTag(21, ArrayObject::class)]))->withTags(new ConfigTestArrayTag(21, ArrayIterator::class));
    }

    // --- callbacks ---

    public function testCallbacksCanBeStaticClosures(): void
    {
        $cbor = new Cbor(
            replacer: static fn(stdClass $value) => 'replaced',
            reviver: static fn(TaggedValue $value) => 'revived',
        );

        $this->assertSame('687265706c61636564', bin2hex($cbor->encode(new stdClass())));
        $this->assertSame('revived', $cbor->decode(hex2bin('d86301')));
    }

    public function testCallbacksKeepTheirOwnThis(): void
    {
        $cbor = new Cbor(
            replacer: fn(stdClass $value) => $this->marker,
            reviver: fn(TaggedValue $value) => $this->marker,
        );

        $this->assertSame($this->marker, CborDecoder::decode($cbor->encode(new stdClass())));
        $this->assertSame($this->marker, $cbor->decode(hex2bin('d86301')));
    }

    // --- the static shortcuts ---

    public function testTheEncoderShortcutRefusesOptions(): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage('CborEncoder::encode() takes only the value. Give options to new Cbor(...) instead');

        CborEncoder::encode(new stdClass(), fn($key, $value) => 1);
    }

    public function testTheDecoderShortcutRefusesOptions(): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage('CborDecoder::decode() takes only the data. Give options to new Cbor(...) instead');

        CborDecoder::decode(hex2bin('d86301'), fn($key, $value) => $value);
    }

    private function assertThrows(Closure $run, string $message): void
    {
        try {
            $run();
        } catch (CborException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
            return;
        }

        $this->fail("Expected a CborException with: $message");
    }
}
