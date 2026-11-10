# cbor.php

[![CI](https://github.com/welpie21/cbor.php/actions/workflows/ci.yml/badge.svg)](https://github.com/welpie21/cbor.php/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Use cbor.php to encode PHP values to CBOR and decode CBOR back to PHP values.

CBOR (Concise Binary Object Representation, [RFC 8949](https://www.rfc-editor.org/rfc/rfc8949)) is a binary data format. It has a data model similar to JSON, but the output is smaller and it can hold raw bytes and tagged values. The library has no runtime dependencies.

## Requirements

- PHP 8.1 or later
- A 64-bit PHP build
- The `mbstring` extension, only if you set a [text string encoding](#text-string-encoding) other than UTF-8

## Installation

Install the package with Composer:

```bash
composer require welpie21/cbor.php
```

## Quick start

Encode a value with `CborEncoder::encode()`. Decode it with `CborDecoder::decode()`.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;

$bytes = CborEncoder::encode([
    'name' => 'Alice',
    'age'  => 30,
    'tags' => ['a', 'b'],
]);

echo bin2hex($bytes);
// a3646e616d6565416c69636563616765181e64746167738261616162

$value = CborDecoder::decode($bytes);
// ['name' => 'Alice', 'age' => 30, 'tags' => ['a', 'b']]
```

The encoder returns a binary string. Use `bin2hex()` or `base64_encode()` if you must show or send the result as text.

These two methods use the default options. To set options or add custom tags, create a `Cbor` instance. See [Configuration](#configuration).

## Configuration

Create one `Cbor` instance with your options, then use it to encode and to decode. The same options apply in both directions, so your values come back as they went in.

```php
use Beau\CborPHP\Cbor;
use Beau\CborPHP\enums\StringEncoding;

$cbor = new Cbor(
    tags: [new MoneyTag()],
    stringEncoding: StringEncoding::UTF16LE,
    strict: true,
);

$bytes = $cbor->encode($value);
$value = $cbor->decode($bytes);
```

Give the options by name. All of them are optional:

| Option           | Default                | Effect |
| ---------------- | ---------------------- | ------ |
| `tags`           | `[]`                   | Handlers that write your classes as tagged values, and read them back. See [Custom tags](#custom-tags). |
| `stringEncoding` | `StringEncoding::UTF8` | The encoding of your PHP strings. See [Text string encoding](#text-string-encoding). |
| `replacer`       | `null`                 | A closure that the encoder calls for a value with no CBOR mapping and no tag handler. See [Replacer and reviver](#replacer-and-reviver). |
| `reviver`        | `null`                 | A closure that the decoder calls for a tagged value with no tag handler. See [Replacer and reviver](#replacer-and-reviver). |
| `byteStrings`    | `false`                | Decode each byte string as a `CborByteString` instead of a plain `string`. See [Byte strings](#byte-strings). |
| `maxDepth`       | `512`                  | The deepest nesting of arrays, maps and tags that the decoder accepts. Deeper input throws a `CborException`, rather than exhaust memory or the stack. |
| `strict`         | `false`                | Make the decoder throw a `CborException` for bytes after the item, and for a map key that is not an `int` or a `string`. Without it, PHP turns a `bool`, `float` or `null` key into an `int` or `""`. |

The constructor checks the options once, so `encode()` and `decode()` do not check them again. It throws a `CborException` for an invalid tag handler, or for a string encoding other than UTF-8 when the `mbstring` extension is missing.

Use `strict: true` and a lower `maxDepth` for input you do not trust:

```php
$cbor = new Cbor(byteStrings: true, maxDepth: 64, strict: true);
```

A map key that cannot be a PHP array key (an array, map, tagged value or `CborByteString`) throws a `CborException` in every mode.

### Change the options

A `Cbor` instance does not change. To get different options, make a copy:

- `with()` returns a copy with the options that you give by name. The other options keep their values.
- `withTags()` returns a copy with more tag handlers, after the ones that the instance has.

```php
$untrusted = $cbor->with(strict: true, maxDepth: 64);
$withMoney = $cbor->withTags(new MoneyTag());
```

A library can share its configured instance this way, and your application can add its own options to it. To remove a replacer or reviver, give `null`, for example `with(replacer: null)`.

## API

| Method | Description |
| ------ | ----------- |
| `CborEncoder::encode(mixed $value): string` | Encodes `$value` with the default options, and returns the CBOR bytes. |
| `CborDecoder::decode(string $data): mixed` | Decodes one CBOR item from `$data` with the default options. |
| `new Cbor(...)` | Creates an encoder and a decoder with the options that you give. See [Configuration](#configuration). |
| `Cbor::encode(mixed $value): string` | Encodes `$value` with the options of the instance. |
| `Cbor::decode(string $data): mixed` | Decodes one CBOR item from `$data` with the options of the instance. |
| `Cbor::with(mixed ...$options): Cbor` | Returns a copy with the options that you give by name. |
| `Cbor::withTags(CborTag ...$tags): Cbor` | Returns a copy with more tag handlers. |

The default options are the ones in the [Configuration](#configuration) table. All methods throw `Beau\CborPHP\exceptions\CborException` when they cannot process the data.

## Type mapping

### Encoding

| PHP value                                | CBOR output                                    |
| ---------------------------------------- | ---------------------------------------------- |
| `int`                                    | Unsigned integer (major type 0) or negative integer (major type 1) |
| `float`                                  | 64-bit float. `NAN` encodes as a 16-bit float. |
| `string`                                 | Text string (major type 3)                     |
| `CborByteString`                         | Byte string (major type 2)                     |
| `CborMap`                                | Map (major type 5), whatever its keys          |
| List array (keys `0, 1, 2, …`), and `[]` | Array (major type 4)                           |
| Any other array                          | Map (major type 5)                             |
| `true`, `false`, `null`                  | Simple values `true`, `false`, `null`          |
| `TaggedValue` (or a subclass of `AbstractTaggedValue`) | Tagged value (major type 6)      |
| An object with a tag handler             | Tagged value, with the content from the handler |
| Any other value                          | Sent to the replacer. Without a replacer, the encoder throws a `CborException`. |

### Decoding

| CBOR input                     | PHP value                                       |
| ------------------------------ | ----------------------------------------------- |
| Unsigned or negative integer   | `int`                                           |
| 16-, 32-, or 64-bit float      | `float`                                         |
| Text string                    | `string`                                        |
| Byte string                    | `string` (raw bytes), or `CborByteString` with `byteStrings: true` |
| Array                          | List array                                      |
| Map                            | Associative array                               |
| `true`, `false`                | `bool`                                          |
| `null`, `undefined`            | `null`                                          |
| Tagged value with a tag handler | The value from the handler                     |
| Any other tagged value         | `TaggedValue`, or the value from the reviver    |

## Byte strings

PHP uses one `string` type for text and for binary data. By default, the encoder writes every PHP string as a CBOR text string. To write a CBOR byte string, wrap the value in `CborByteString`:

```php
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\utils\CborByteString;

$bytes = CborEncoder::encode(new CborByteString("\x01\x02\x03"));

echo bin2hex($bytes);
// 43010203
```

The decoder returns a CBOR byte string as a plain PHP `string`. To tell byte strings and text strings apart, set `byteStrings: true`. The decoder then returns each byte string as a `CborByteString`:

```php
use Beau\CborPHP\Cbor;

$cbor = new Cbor(byteStrings: true);

$decoded = $cbor->decode(hex2bin('43010203'));
$decoded->getByteString(); // "\x01\x02\x03"
```

## Text string encoding

A CBOR text string is always UTF-8. By default, the encoder writes each PHP string as it is, and the decoder returns each text string as it is. Your strings must then be UTF-8.

If your strings use a different encoding, set `stringEncoding`. The encoder converts each PHP string to UTF-8, and the decoder converts each text string to the encoding that you set. The CBOR bytes stay UTF-8, so other CBOR decoders can read them.

```php
use Beau\CborPHP\Cbor;
use Beau\CborPHP\enums\StringEncoding;

$cbor = new Cbor(stringEncoding: StringEncoding::UTF16LE);

$text = "h\x00\xe9\x00"; // "hé" in UTF-16LE

$bytes = $cbor->encode($text);

echo bin2hex($bytes);
// 6368c3a9 ("hé" in UTF-8)

$cbor->decode($bytes);
// "h\x00\xe9\x00"
```

One instance encodes and decodes, so the decoder always returns strings in the same encoding that the encoder reads.

| `StringEncoding` case | Encoding of your PHP strings |
| --------------------- | ---------------------------- |
| `UTF8`                | UTF-8. This is the default, and `null` means the same. The library does not convert or validate strings. |
| `UTF16`               | UTF-16. The encoder reads a byte order mark (BOM) if there is one, else big-endian. The decoder returns big-endian with no BOM. |
| `UTF16BE`             | UTF-16, big-endian. |
| `UTF16LE`             | UTF-16, little-endian. |
| `UTF32`               | UTF-32, with the same BOM rules as `UTF16`. |
| `UTF32BE`             | UTF-32, big-endian. |
| `UTF32LE`             | UTF-32, little-endian. |

The conversion applies to all text strings: values, map keys, and the strings in tagged values and in tag content. It does not apply to byte strings.

Before it converts a string, the library makes sure that the string is valid:

- The encoder throws a `CborException` for a PHP string that is not valid in `stringEncoding`.
- The decoder throws a `CborException` for a text string that is not valid UTF-8.

> [!NOTE]
> An encoding other than `UTF8` requires the `mbstring` extension. Without it, `new Cbor()` throws a `CborException`.

## Maps

The encoder writes a list array as a CBOR array, and any other array as a map. To write a value as a map whatever its keys, wrap it in `CborMap`. This is how to write an empty map, or a map with the keys `0, 1, 2`.

PHP turns a numeric string key such as `"1"` into the int `1`. When a map's keys must all be text strings, pass `stringKeys: true`, and the encoder writes int keys as text strings:

```php
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\utils\CborMap;

bin2hex(CborEncoder::encode([]));                                  // 80 (empty array)
bin2hex(CborEncoder::encode(new CborMap()));                       // a0 (empty map)
bin2hex(CborEncoder::encode(new CborMap(['1' => true], stringKeys: true))); // a16131f5 ({"1": true})
```

## Custom tags

A CBOR tag is a number that gives a meaning to the value after it. For example, tag `1` marks an epoch timestamp. To write objects of your own class as a tagged value, and read them back, write a tag handler: a class that implements `CborTag`. Then give an instance to the `tags` option.

This example writes a `Money` object as tag `20`:

```php
use Beau\CborPHP\Cbor;
use Beau\CborPHP\interfaces\CborTag;

final class Money
{
    public function __construct(
        public int $cents,
        public string $currency,
    ) {}
}

final class MoneyTag implements CborTag
{
    public function tag(): int
    {
        return 20;
    }

    public function class(): ?string
    {
        return Money::class;
    }

    public function encode(object $value): mixed
    {
        return [$value->cents, $value->currency];
    }

    public function decode(mixed $content): mixed
    {
        return new Money(...$content);
    }
}

$cbor = new Cbor(tags: [new MoneyTag()]);

$bytes = $cbor->encode(new Money(1999, 'EUR'));

echo bin2hex($bytes);
// d4821907cf63455552 (tag 20, then [1999, "EUR"])

$money = $cbor->decode($bytes);
// Money { cents: 1999, currency: 'EUR' }
```

A tag handler has these methods:

| Method | Returns |
| ------ | ------- |
| `tag(): int` | The tag number. It must not be negative. |
| `class(): ?string` | The class or interface whose objects the handler encodes, or `null` if the handler only decodes. |
| `encode(object $value): mixed` | The tag content for an object of `class()`. The encoder encodes the content like any other value, so it can hold strings, arrays and other tagged objects. Do not return the object itself. |
| `decode(mixed $content): mixed` | The PHP value for the tag content. Any type is possible, also a plain `string` or `int`. Tagged values in the content are already decoded. |

The encoder gives an object to a handler as follows:

1. Built-in values come first. A string, array, `TaggedValue`, `CborByteString` or `CborMap` never goes to a handler.
2. The encoder uses the handler whose `class()` is the class of the object.
3. If there is none, it uses the first handler whose `class()` the object is an instance of. For example, a handler for `DateTimeInterface` also writes `DateTime` and `DateTimeImmutable`.
4. If no handler matches, the object goes to the replacer.

### Decode-only handlers

Two tags can decode to the same class. For example, tag `0` (a date/time string) and tag `1` (an epoch timestamp) can both become a `DateTimeImmutable`. Only one handler can encode a class. In the other handlers, return `null` from `class()`. The decoder uses them, but the encoder does not.

### Handler errors

`new Cbor()` throws a `CborException` when:

- an item in `tags` does not implement `CborTag`,
- a tag number is negative,
- two handlers have the same tag number, or the same class,
- `class()` returns a class that does not exist, or a class that the encoder writes itself (`TaggedValue`, `CborByteString` or `CborMap`).

The encoder throws a `CborException` when `encode()` returns the object that it got.

## Tagged values

For a single tagged value, you do not need a handler. Use `TaggedValue`:

```php
use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\classes\TaggedValue;

$bytes = CborEncoder::encode(new TaggedValue(1, 1705316400));

$decoded = CborDecoder::decode($bytes);
$decoded->tag;   // 1
$decoded->value; // 1705316400
```

A tag number is an unsigned integer. The encoder throws a `CborException` for a negative tag number. A tagged value that has no handler decodes as a `TaggedValue`.

## Replacer and reviver

A tag handler writes a class as a tagged value. For other conversions, use a replacer and a reviver. Both are closures that take one value:

- **Replacer** `function (mixed $value): mixed`: The encoder calls it for each value that has no CBOR mapping and no tag handler, for example an object. Return a value that the encoder supports. If the return value has no CBOR mapping, the encoder throws a `CborException`. If you return an array, the encoder sends the unsupported values in it to the replacer.
- **Reviver** `function (TaggedValue $value): mixed`: The decoder calls it for each tagged value that has no tag handler. The return value replaces the tagged value in the result. The decoder does not call it for other values.

This example writes each date/time as a plain timestamp, without a tag, and reads tag `32` (a URI) as a plain string:

```php
use Beau\CborPHP\Cbor;
use Beau\CborPHP\classes\TaggedValue;

$cbor = new Cbor(
    replacer: fn(mixed $value) => $value instanceof DateTimeInterface ? $value->getTimestamp() : $value,
    reviver: fn(TaggedValue $value) => $value->tag === 32 ? $value->value : $value,
);

bin2hex($cbor->encode(['at' => new DateTimeImmutable('@0')]));
// a162617400 ({"at": 0})

$cbor->decode(hex2bin('d8207368747470733a2f2f6578616d706c652e636f6d'));
// "https://example.com"
```

The replacer and the reviver run as normal closures. `$this` in them is your own object, and static closures work.

## Errors

The library throws `Beau\CborPHP\exceptions\CborException` for data it cannot process. For example, the decoder throws it for truncated input, and the encoder throws it for a value that has no CBOR mapping. The message starts with `Cbor Exception:`.

```php
use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\exceptions\CborException;

try {
    CborDecoder::decode(hex2bin('e0'));
} catch (CborException $e) {
    echo $e->getMessage();
    // Cbor Exception: Unsupported simple value: 0
}
```

## Limitations

- The encoder writes every float as a 64-bit float, except `NAN`.
- The library does not support indefinite-length arrays, maps, or strings. The decoder throws a `CborException` for them.
- Integers, lengths, and tag numbers must fit in the PHP signed 64-bit range. The decoder throws a `CborException` for a larger value.
- The decoder reads one CBOR item. It ignores bytes after that item, unless `strict` is set.
- The decoder accepts nesting up to `maxDepth` levels (512 by default).
- The library does not validate UTF-8 in text strings when `stringEncoding` is UTF-8 (the default).

## Upgrading from 2.x

Version 3 moves all options to `Cbor`. Without options, `CborEncoder::encode()` and `CborDecoder::decode()` work as before.

| 2.x | 3.x |
| --- | --- |
| `CborEncoder::encode($value, $replacer)` | `(new Cbor(replacer: $replacer))->encode($value)` |
| `CborDecoder::decode($data, $replacer)` | `(new Cbor(reviver: $reviver))->decode($data)` |
| `CborDecoder::decode($data, byteStrings: true, maxDepth: 64, strict: true)` | `(new Cbor(byteStrings: true, maxDepth: 64, strict: true))->decode($data)` |
| A replacer `function ($key, $value)`, where `$key` is always `null` | A replacer `function ($value)`, and a reviver `function (TaggedValue $value)` |
| A custom tag: the encoder replacer returns a `TaggedValue`, and the decoder replacer restores the object | A `CborTag` handler in `tags`. The replacer and reviver way still works. |
| The decoder replacer gets each tagged value | The reviver gets each tagged value that has no tag handler |
| The replacer runs bound to the encoder or decoder, so static closures fail | The replacer and reviver run as normal closures |

In 3.x, `CborEncoder::encode()` and `CborDecoder::decode()` throw a `CborException` if you give them more than one argument. A call written for 2.x then fails, instead of losing its options without a warning.

The constructor of `CborEncoder` is private, and `CborDecoder::decodeItem()` is removed. Use the static methods or a `Cbor` instance.

## Development

Clone the repository, and then install the development dependencies:

```bash
composer install
```

Run the unit tests:

```bash
composer test-coverage
```

Run the benchmarks:

```bash
composer benchmark
```

The release workflow stores the benchmark results for each version in [`.github/benchmark`](.github/benchmark).

## License

cbor.php is released under the [MIT License](LICENSE).
