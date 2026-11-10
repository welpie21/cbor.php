<?php

use Beau\CborPHP\Cbor;
use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\utils\CborMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CborMapEncodingTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function maps(): iterable
    {
        // An empty array is an empty CBOR array; CborMap says it is a map.
        yield 'empty array' => [[], '80'];
        yield 'empty map' => [new CborMap(), 'a0'];
        yield 'list as a map' => [new CborMap(['a', 'b']), 'a2006161016162'];
        yield 'int keys' => [new CborMap([1 => 'a']), 'a1016161'];
        yield 'int keys as text' => [new CborMap([1 => 'a', 'b' => 2], stringKeys: true), 'a2613161616162' . '02'];
        // PHP has already turned "1" into 1: stringKeys writes it back as text.
        yield 'numeric string key' => [new CborMap(['1' => true], stringKeys: true), 'a16131f5'];
        yield 'negative int key as text' => [new CborMap([-1 => null], stringKeys: true), 'a1622d31f6'];
        yield 'nested' => [new CborMap(['m' => new CborMap()]), 'a1616da0'];
        // An associative array is still a map with its keys as they are.
        yield 'associative array' => [[1 => 'a', 'b' => 2], 'a2016161616202'];
    }

    #[DataProvider('maps')]
    public function testEncodesMaps(mixed $value, string $hex): void
    {
        $this->assertSame($hex, bin2hex(CborEncoder::encode($value)));
    }

    public function testTextKeysReadBackAsPhpArrayKeys(): void
    {
        $encoded = CborEncoder::encode(new CborMap([1 => 'a', 'b' => 2], stringKeys: true));

        // PHP turns the "1" key back into an int, as it does for any array.
        $this->assertSame([1 => 'a', 'b' => 2], CborDecoder::decode($encoded));
    }

    public function testMapValuesGoThroughTheReplacer(): void
    {
        $encoded = (new Cbor(replacer: function ($value) {
            return $value instanceof DateTimeInterface ? $value->getTimestamp() : $value;
        }))->encode(new CborMap(['when' => new DateTimeImmutable('@0')]));

        $this->assertSame('a1647768656e00', bin2hex($encoded));
    }
}
