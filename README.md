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
| `CborDecoder::decode(string $data, ?Closure $replacer = null): mixed`    | Decodes one CBOR item from `$data`.          |

Both methods throw `Beau\CborPHP\exceptions\CborException` when they cannot process the data. For the `$replacer` argument, see [Custom types](#custom-types).

## Type mapping

### Encoding

| PHP value                                | CBOR output                                    |
| ---------------------------------------- | ---------------------------------------------- |
| `int`                                    | Unsigned integer (major type 0) or negative integer (major type 1) |
| `float`                                  | 64-bit float. `NAN` encodes as a 16-bit float. |
| `string`                                 | Text string (major type 3)                     |
| `CborByteString`                         | Byte string (major type 2)                     |
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
| Byte string                    | `string` (raw bytes)                            |
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

The decoder returns a CBOR byte string as a plain PHP `string`.

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
- Integers must fit in the PHP signed 64-bit range.
- The decoder reads one CBOR item. It ignores bytes after that item.
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
