# cbor.php

[![CI](https://github.com/welpie21/cbor.php/actions/workflows/ci.yml/badge.svg)](https://github.com/welpie21/cbor.php/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Use cbor.php to encode PHP values to CBOR and decode CBOR back to PHP values.

CBOR (Concise Binary Object Representation, [RFC 8949](https://www.rfc-editor.org/rfc/rfc8949)) is a binary data format. It has a data model similar to JSON, but the output is smaller and it can hold raw bytes and tagged values. The library has no runtime dependencies.

## Requirements

- PHP 8.1 or later
- A 64-bit PHP build

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

## API

| Method                                                                   | Description                                  |
| ------------------------------------------------------------------------ | -------------------------------------------- |
| `CborEncoder::encode(mixed $value, ?Closure $replacer = null): string`   | Encodes `$value` and returns the CBOR bytes. |
| `CborDecoder::decode(string $data, ?Closure $replacer = null, bool $byteStrings = false, int $maxDepth = 512, bool $strict = false): mixed` | Decodes one CBOR item from `$data`. See [Decoder options](#decoder-options). |

Both methods throw `Beau\CborPHP\exceptions\CborException` when they cannot process the data. For the `$replacer` argument, see [Custom types](#custom-types).

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
| Any other value                          | Sent to the encoder replacer                   |

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
| Tagged value                   | `TaggedValue`, then sent to the decoder replacer |

## Byte strings

PHP uses one `string` type for text and for binary data. By default, the encoder writes every PHP string as a CBOR text string. To write a CBOR byte string, wrap the value in `CborByteString`:

```php
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\utils\CborByteString;

$bytes = CborEncoder::encode(new CborByteString("\x01\x02\x03"));

echo bin2hex($bytes);
// 43010203
```

The decoder returns a CBOR byte string as a plain PHP `string`. To tell byte strings and text strings apart, pass `byteStrings: true`; the decoder then returns each byte string as a `CborByteString`:

```php
use Beau\CborPHP\CborDecoder;

$decoded = CborDecoder::decode(hex2bin('43010203'), byteStrings: true);
$decoded->getByteString(); // "\x01\x02\x03"
```

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

## Decoder options

`CborDecoder::decode()` takes these named arguments:

| Argument      | Default | Effect |
| ------------- | ------- | ------ |
| `byteStrings` | `false` | Return each byte string as a `CborByteString` instead of a plain `string`. |
| `maxDepth`    | `512`   | The deepest nesting of arrays, maps and tags to accept. Deeper input throws a `CborException`, rather than exhaust memory or the stack. |
| `strict`      | `false` | Throw a `CborException` for bytes after the item, and for a map key that is not an `int` or a `string`. Without it, PHP turns a `bool`, `float` or `null` key into an `int` or `""`. |

Use `strict: true` for input you do not trust:

```php
$value = CborDecoder::decode($data, byteStrings: true, strict: true);
```

A map key that cannot be a PHP array key (an array, map, tagged value or `CborByteString`) throws a `CborException` in every mode.

## Tagged values

A CBOR tag is a number that gives a meaning to the value after it. For example, tag `1` marks an epoch timestamp. Use `TaggedValue` to encode a tag:

```php
use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\classes\TaggedValue;

$bytes = CborEncoder::encode(new TaggedValue(1, 1705316400));

$decoded = CborDecoder::decode($bytes);
$decoded->tag;   // 1
$decoded->value; // 1705316400
```

A tag number is an unsigned integer. The encoder throws a `CborException` for a negative tag number.

## Custom types

A replacer is a closure that converts values. It has the signature `function (mixed $key, mixed $value): mixed`. The `$key` argument is always `null`.

- **Encoder replacer:** The encoder calls it for each value that has no CBOR mapping, for example an object. Return a value that the encoder supports. If the return value has no CBOR mapping, the encoder throws a `CborException`. If you return an array or a tagged value, the encoder sends the unsupported values in it to the replacer.
- **Decoder replacer:** The decoder calls it for each tagged value, with a `TaggedValue` as `$value`. The return value replaces the tagged value in the result. The decoder does not call it for other values.

This example stores a `Money` object as tag `20`, and then restores it:

```php
use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\classes\TaggedValue;

final class Money
{
    public function __construct(
        public int $cents,
        public string $currency,
    ) {}
}

$bytes = CborEncoder::encode(new Money(1999, 'EUR'), function ($key, $value) {
    if ($value instanceof Money) {
        return new TaggedValue(20, [$value->cents, $value->currency]);
    }

    return $value;
});

$money = CborDecoder::decode($bytes, function ($key, $value) {
    if ($value instanceof TaggedValue && $value->tag === 20) {
        return new Money(...$value->value);
    }

    return $value;
});
// Money { cents: 1999, currency: 'EUR' }
```

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
- The decoder does not validate UTF-8 in text strings.

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
