<?php

use Beau\CborPHP\Cbor;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\classes\TaggedValue;
use Beau\CborPHP\enums\StringEncoding;
use Beau\CborPHP\exceptions\CborException;
use Beau\CborPHP\interfaces\CborTag;
use Beau\CborPHP\utils\CborByteString;
use Beau\CborPHP\utils\CborMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TagTestMoney
{
    public function __construct(public int $cents, public string $currency) {}
}

/** The way to write a custom tag: a class that implements CborTag. */
final class TagTestMoneyTag implements CborTag
{
    public function tag(): int
    {
        return 20;
    }

    public function class(): string
    {
        return TagTestMoney::class;
    }

    public function encode(object $value): array
    {
        return [$value->cents, $value->currency];
    }

    public function decode(mixed $content): TagTestMoney
    {
        return new TagTestMoney(...$content);
    }
}

/** A tag handler made of closures, for one-off handlers in these tests. */
final class TagTestHandler implements CborTag
{
    public function __construct(
        private readonly int $tag,
        private readonly ?string $class,
        private readonly ?Closure $encode = null,
        private readonly ?Closure $decode = null,
    ) {}

    public function tag(): int
    {
        return $this->tag;
    }

    public function class(): ?string
    {
        return $this->class;
    }

    public function encode(object $value): mixed
    {
        return ($this->encode)($value);
    }

    public function decode(mixed $content): mixed
    {
        return $this->decode === null ? $content : ($this->decode)($content);
    }
}

/**
 * Tests custom tags written with CborTag handlers.
 */
class CborTagTest extends TestCase
{
    private static function epochTag(): TagTestHandler
    {
        // Tag 1: epoch-based date/time, for any DateTimeInterface.
        return new TagTestHandler(
            1,
            DateTimeInterface::class,
            fn(DateTimeInterface $value) => $value->getTimestamp(),
            fn(int $content) => new DateTimeImmutable("@$content"),
        );
    }

    // --- encode and decode ---

    public function testEncodesAnObjectWithItsTag(): void
    {
        $cbor = new Cbor(tags: [new TagTestMoneyTag()]);

        // d4 = tag 20, then [1999, "EUR"].
        $this->assertSame('d4821907cf63455552', bin2hex($cbor->encode(new TagTestMoney(1999, 'EUR'))));
    }

    public function testDecodesATagWithItsHandler(): void
    {
        $cbor = new Cbor(tags: [new TagTestMoneyTag()]);

        $this->assertEquals(new TagTestMoney(1999, 'EUR'), $cbor->decode(hex2bin('d4821907cf63455552')));
    }

    public function testRoundTripInsideArraysAndMaps(): void
    {
        $cbor = new Cbor(tags: [new TagTestMoneyTag()]);
        $value = [
            'prices' => [new TagTestMoney(1, 'EUR'), new TagTestMoney(2, 'USD')],
            'total' => new TagTestMoney(3, 'EUR'),
        ];

        $this->assertEquals($value, $cbor->decode($cbor->encode($value)));
    }

    public function testEncodesTheSameBytesAsATaggedValue(): void
    {
        $cbor = new Cbor(tags: [new TagTestMoneyTag()]);

        $this->assertSame(
            CborEncoder::encode(new TaggedValue(20, [1999, 'EUR'])),
            $cbor->encode(new TagTestMoney(1999, 'EUR')),
        );
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function tagNumbers(): iterable
    {
        yield '23, in the initial byte' => [23, 'd701'];
        yield '24, one byte' => [24, 'd81801'];
        yield '1000, two bytes' => [1000, 'd903e801'];
        yield '65536, four bytes' => [65536, 'da0001000001'];
    }

    #[DataProvider('tagNumbers')]
    public function testTagNumbersOfEachSize(int $tag, string $hex): void
    {
        $cbor = new Cbor(tags: [new TagTestHandler($tag, stdClass::class, fn() => 1, fn(int $content) => (object) ['n' => $content])]);

        $this->assertSame($hex, bin2hex($cbor->encode(new stdClass())));
        $this->assertEquals((object) ['n' => 1], $cbor->decode(hex2bin($hex)));
    }

    public function testTheContentCanHoldOtherTaggedObjects(): void
    {
        // Tag 21 holds a list of money, each with tag 20.
        $cbor = new Cbor(tags: [
            new TagTestMoneyTag(),
            new TagTestHandler(21, ArrayObject::class, fn(ArrayObject $value) => $value->getArrayCopy(), fn(array $content) => new ArrayObject($content)),
        ]);
        $wallet = new ArrayObject([new TagTestMoney(1, 'EUR')]);

        $encoded = $cbor->encode($wallet);

        $this->assertSame('d581d48201634555' . '52', bin2hex($encoded));
        $this->assertEquals($wallet, $cbor->decode($encoded));
    }

    public function testStringsInTheContentFollowTheStringEncoding(): void
    {
        $cbor = new Cbor(tags: [new TagTestMoneyTag()], stringEncoding: StringEncoding::UTF16LE);
        $money = new TagTestMoney(1999, "E\x00U\x00R\x00");

        $encoded = $cbor->encode($money);

        // The currency is UTF-8 on the wire.
        $this->assertSame('d4821907cf63455552', bin2hex($encoded));
        $this->assertEquals($money, $cbor->decode($encoded));
    }

    public function testAHandlerCanEncodeWithAnotherCbor(): void
    {
        // Tag 24 holds an encoded CBOR item as a byte string (RFC 8949, section 3.4.5.1).
        $cbor = new Cbor(tags: [
            new TagTestHandler(24, ArrayObject::class, fn(ArrayObject $value) => new CborByteString(CborEncoder::encode($value->getArrayCopy()))),
        ]);

        $this->assertSame('d8184382' . '0102', bin2hex($cbor->encode(new ArrayObject([1, 2]))));
    }

    public function testADecodedValueCanBeAMapKey(): void
    {
        // Tag 99 around the key "k": the handler returns a plain string.
        $cbor = new Cbor(tags: [new TagTestHandler(99, null, decode: fn(string $content) => strtoupper($content))]);

        $this->assertSame(['K' => 1], $cbor->decode(hex2bin('a1d863616b01')));
    }

    // --- which handler ---

    public function testAnInterfaceHandlerTakesItsImplementations(): void
    {
        $cbor = new Cbor(tags: [self::epochTag()]);

        $this->assertSame('c11a6553f100', bin2hex($cbor->encode(new DateTimeImmutable('@1700000000'))));
        $this->assertSame('c11a6553f100', bin2hex($cbor->encode(new DateTime('@1700000000'))));
        $this->assertEquals(new DateTimeImmutable('@1700000000'), $cbor->decode(hex2bin('c11a6553f100')));
    }

    public function testAnExactClassWinsOverAnInterface(): void
    {
        $cbor = new Cbor(tags: [
            self::epochTag(),
            // Tag 0: date/time string, only for DateTimeImmutable.
            new TagTestHandler(0, DateTimeImmutable::class, fn(DateTimeImmutable $value) => $value->format('Y-m-d\TH:i:s\Z')),
        ]);

        $this->assertSame('c074323032332d31312d31345432323a31333a32305a', bin2hex($cbor->encode(new DateTimeImmutable('@1700000000'))));
        $this->assertSame('c11a6553f100', bin2hex($cbor->encode(new DateTime('@1700000000'))));
    }

    public function testTheFirstMatchingHandlerWins(): void
    {
        $cbor = new Cbor(tags: [
            new TagTestHandler(2, Countable::class, fn() => 'countable'),
            new TagTestHandler(3, IteratorAggregate::class, fn() => 'iterable'),
        ]);

        // ArrayObject is both Countable and IteratorAggregate: the first handler given wins.
        $this->assertSame('c2' . '69636f756e7461626c65', bin2hex($cbor->encode(new ArrayObject())));
    }

    public function testAParentClassHandlerTakesItsSubclasses(): void
    {
        // RuntimeException and LogicException both extend Exception.
        $cbor = new Cbor(tags: [new TagTestHandler(30, Exception::class, fn(Exception $value) => $value->getMessage())]);

        $this->assertSame('d81e64626f6f6d', bin2hex($cbor->encode(new RuntimeException('boom'))));
        $this->assertSame('d81e64626f6f6d', bin2hex($cbor->encode(new LogicException('boom'))));
    }

    public function testTheFirstHandlerWinsBetweenAParentClassAndAnInterface(): void
    {
        $throwable = new TagTestHandler(31, Throwable::class, fn() => 'throwable');
        $exception = new TagTestHandler(30, Exception::class, fn() => 'exception');

        // RuntimeException extends Exception and implements Throwable.
        $this->assertSame('d81f' . '697468726f7761626c65', bin2hex((new Cbor(tags: [$throwable, $exception]))->encode(new RuntimeException())));
        $this->assertSame('d81e' . '69657863657074696f6e', bin2hex((new Cbor(tags: [$exception, $throwable]))->encode(new RuntimeException())));
    }

    public function testEveryObjectOfAClassGetsTheSameAnswer(): void
    {
        $replaced = 0;
        $cbor = new Cbor(tags: [self::epochTag()], replacer: function () use (&$replaced) {
            $replaced++;
            return 'replaced';
        });
        $value = [new DateTime('@1700000000'), new DateTime('@1700000000'), new stdClass(), new stdClass()];
        $hex = '84' . str_repeat('c11a6553f100', 2) . str_repeat('687265706c61636564', 2);

        // The encoder keeps the answer for each class, also "no handler", across calls.
        $this->assertSame($hex, bin2hex($cbor->encode($value)));
        $this->assertSame($hex, bin2hex($cbor->encode($value)));
        $this->assertSame(4, $replaced);
    }

    public function testADecodeOnlyHandlerDecodes(): void
    {
        // Tag 0 is read as a date/time, but DateTimeImmutable is written with tag 1.
        $cbor = new Cbor(tags: [
            self::epochTag(),
            new TagTestHandler(0, null, decode: fn(string $content) => new DateTimeImmutable($content)),
        ]);

        $this->assertEquals(new DateTimeImmutable('2024-01-15T12:00:00Z'), $cbor->decode(hex2bin('c074323032342d30312d31355431323a30303a30305a')));
        $this->assertSame('c11a6553f100', bin2hex($cbor->encode(new DateTimeImmutable('@1700000000'))));
    }

    public function testADecodeOnlyHandlerDoesNotEncode(): void
    {
        $cbor = new Cbor(tags: [new TagTestHandler(0, null, decode: fn($content) => $content)]);

        $this->expectException(CborException::class);
        $this->expectExceptionMessage('Unsupported type: DateTimeImmutable');

        $cbor->encode(new DateTimeImmutable());
    }

    public function testBuiltInValuesDoNotGoToHandlers(): void
    {
        $cbor = new Cbor(tags: [new TagTestMoneyTag()]);

        $this->assertSame('d40a', bin2hex($cbor->encode(new TaggedValue(20, 10))));
        $this->assertSame('4101', bin2hex($cbor->encode(new CborByteString("\x01"))));
        $this->assertSame('a0', bin2hex($cbor->encode(new CborMap())));
    }

    // --- with the replacer and the reviver ---

    public function testATagWithoutAHandlerStillDecodesAsTaggedValue(): void
    {
        $cbor = new Cbor(tags: [new TagTestMoneyTag()]);

        $this->assertEquals(new TaggedValue(99, 1), $cbor->decode(hex2bin('d86301')));
    }

    public function testTheReviverGetsOnlyTagsWithoutAHandler(): void
    {
        $revived = [];
        $cbor = new Cbor(tags: [new TagTestMoneyTag()], reviver: function (TaggedValue $value) use (&$revived) {
            $revived[] = $value->tag;
            return $value->value;
        });

        $decoded = $cbor->decode(hex2bin('82' . 'd4821907cf63455552' . 'd86301'));

        $this->assertSame([99], $revived);
        $this->assertEquals([new TagTestMoney(1999, 'EUR'), 1], $decoded);
    }

    public function testTheReplacerGetsOnlyObjectsWithoutAHandler(): void
    {
        $replaced = [];
        $cbor = new Cbor(tags: [new TagTestMoneyTag()], replacer: function (object $value) use (&$replaced) {
            $replaced[] = get_debug_type($value);
            return 'replaced';
        });

        $encoded = $cbor->encode([new TagTestMoney(1999, 'EUR'), new stdClass()]);

        $this->assertSame(['stdClass'], $replaced);
        $this->assertSame('82' . 'd4821907cf63455552' . '687265706c61636564', bin2hex($encoded));
    }

    public function testAReplacedValueCanUseATagHandler(): void
    {
        $cbor = new Cbor(
            tags: [new TagTestMoneyTag()],
            replacer: fn(stdClass $value) => new TagTestMoney($value->cents, 'EUR'),
        );

        $this->assertSame('d4821907cf63455552', bin2hex($cbor->encode((object) ['cents' => 1999])));
    }

    // --- invalid handlers ---

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function invalidTags(): iterable
    {
        yield 'not a handler' => [[new stdClass()], 'A tag handler must implement Beau\CborPHP\interfaces\CborTag, got stdClass'];
        yield 'negative tag' => [[new TagTestHandler(-1, null)], 'Tag number must not be negative: -1, from TagTestHandler'];
        yield 'two handlers for a tag' => [[new TagTestMoneyTag(), new TagTestHandler(20, null)], 'Tag 20 has two handlers: TagTestMoneyTag and TagTestHandler'];
        yield 'two handlers for a class' => [[new TagTestMoneyTag(), new TagTestHandler(21, TagTestMoney::class)], 'TagTestMoney has two tag handlers: for tag 20 and tag 21'];
        yield 'unknown class' => [[new TagTestHandler(20, 'NoSuchClass')], 'TagTestHandler::class() returned an unknown class: NoSuchClass'];
        yield 'TaggedValue' => [[new TagTestHandler(20, TaggedValue::class)], 'Beau\CborPHP\classes\TaggedValue cannot have a tag handler: the encoder writes it itself'];
        yield 'CborByteString' => [[new TagTestHandler(20, CborByteString::class)], 'Beau\CborPHP\utils\CborByteString cannot have a tag handler: the encoder writes it itself'];
        yield 'CborMap' => [[new TagTestHandler(20, CborMap::class)], 'Beau\CborPHP\utils\CborMap cannot have a tag handler: the encoder writes it itself'];
    }

    /**
     * @param array<mixed> $tags
     */
    #[DataProvider('invalidTags')]
    public function testInvalidHandlersAreRefusedWhenCreated(array $tags, string $message): void
    {
        $this->expectException(CborException::class);
        $this->expectExceptionMessage($message);

        new Cbor(tags: $tags);
    }

    public function testAClassNameIsMatchedWhateverItsCase(): void
    {
        $cbor = new Cbor(tags: [new TagTestHandler(20, 'tagtestmoney', fn(TagTestMoney $value) => $value->cents)]);

        $this->assertSame('d41907cf', bin2hex($cbor->encode(new TagTestMoney(1999, 'EUR'))));
    }

    public function testAHandlerThatReturnsTheObjectIsRefused(): void
    {
        $cbor = new Cbor(tags: [new TagTestHandler(20, TagTestMoney::class, fn(TagTestMoney $value) => $value)]);

        $this->expectException(CborException::class);
        $this->expectExceptionMessage('TagTestHandler::encode() returned the object itself. Return the tag content instead');

        $cbor->encode(new TagTestMoney(1999, 'EUR'));
    }
}
