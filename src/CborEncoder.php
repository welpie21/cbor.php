<?php

namespace Beau\CborReduxPhp;

use Beau\CborReduxPhp\classes\Sequence;
use Beau\CborReduxPhp\classes\SimpleValue;
use Beau\CborReduxPhp\classes\TaggedValue;
use Beau\CborReduxPhp\enums\Cbor;
use Beau\CborReduxPhp\exceptions\CborReduxException;
use Beau\CborReduxPhp\utils\ArrayBuffer;
use Beau\CborReduxPhp\utils\DataView;
use Beau\CborReduxPhp\utils\Uint8Array;
use Closure;

class CborEncoder
{
    const POW_2_32 = 4294967296;
    const POW_2_53 = 9007199254740992;

    private ArrayBuffer $data;
    private DataView $view;
    private Uint8Array $byteView;
    private int $lastLength;
    private int $offset = 0;
    private Closure $replacer;

    /**
     * @throws CborReduxException
     */
    public function encode(mixed $value, Closure|array|null $replacer = null): array
    {
        // check if replacer is a function
        if (is_callable($replacer)) {
            $this->replacer = $replacer;
        } else if (is_array($replacer)) {
            $exclusive = clone $replacer;
            $this->replacer = function ($key, $value) use ($exclusive) {
                if ($key === Cbor::EMPTY_KEY || in_array($key, $exclusive)) return $value;
                return Cbor::OMIT_VALUE;
            };
        } else {
            $replacer = $this->replacer = fn($key, $value) => $value;
        }

        $this->data = new ArrayBuffer(256);
        $this->view = new DataView($this->data);
        $this->byteView = new Uint8Array($this->data);

        $this->encodeItem($replacer(Cbor::EMPTY_KEY, $value));

        $ret = new ArrayBuffer($this->offset);
        $retView = new DataView($ret);

        for($i = 0; $i < $this->offset; ++$i) {
            $retView->setUint8($i, $this->view->getUint8($i));
        }

        return $ret->toArray();
    }

    private function prepareWrite(int $length): DataView
    {
        $newByteLength = $this->data->count();
        $requiredLength = $this->offset + $length;

        while ($newByteLength < $requiredLength) {
            $newByteLength <<= 2;
        }

        if ($newByteLength !== $this->data->count()) {
            $oldDataView = clone $this->view;
            $this->data = new ArrayBuffer($newByteLength);
            $this->view = new DataView($this->data);
            $this->byteView = new Uint8Array($this->data);

            $uint32Count = ($this->offset + 3) >> 2;

            for ($i = 0; $i < $uint32Count; ++$i) {
                $this->view->setUint32($i << 2, $oldDataView->getUint32($i << 2));
            }
        }

        $this->lastLength = $length;
        return $this->view;
    }

    private function commitWrite(): void
    {
        $this->offset += $this->lastLength;
    }

    private function writeFloat64(int $value): void
    {
        $this->prepareWrite(8)->setFloat64($this->offset, $value);
        $this->commitWrite();
    }

    private function writeUint8(int $value): void
    {
        $this->prepareWrite(1)->setUint8($this->offset, $value);
        $this->commitWrite();
    }

    private function writeUint8Array(array|Uint8Array $value): void
    {
        // can be faulty
        if ($value instanceof Uint8Array) {
            $value = $value->buffer->toArray();
        }

        $this->prepareWrite(count($value));
        $this->commitWrite();
    }

    private function writeUint16(int $value): void
    {
        $this->prepareWrite(2)->setUint16($this->offset, $value);
        $this->commitWrite();
    }

    private function writeUint32(int $value): void
    {
        $this->prepareWrite(4)->setUint32($this->offset, $value);
        $this->commitWrite();
    }

    private function writeUint64(int $value): void
    {
        $low = $value % self::POW_2_32;
        $high = ($value - $low) / self::POW_2_32;

        $view = $this->prepareWrite(8);

        $view->setUint32($this->offset, $high);
        $view->setUint32($this->offset + 4, $low);

        $this->commitWrite();
    }

    private function writeBigUint64(int $value): void
    {
        $this->prepareWrite(8)->setBigUint64($this->offset, $value);
        $this->commitWrite();
    }

    private function writeVarUint(int $value, int $mod): void
    {
        if ($value <= 0xff) {
            if ($value >= 24) {
                $this->writeUint8(0x18 | $mod);
            }
            $this->writeUint8($value);
        } else if ($value <= 0xffff) {
            $this->writeUint8(0x19 | $mod);
            $this->writeUint16($value);
        } else if ($value <= 0xffffffff) {
            $this->writeUint8(0x1a | $mod);
            $this->writeUint32($value);
        } else {
            $this->writeUint64(0x1b | $mod);
            if (is_int($value)) { // <-- this whole if-statement can be faulty
                $this->writeUint64($value);
            } else {
                $this->writeBigUint64($value);
            }
        }
    }

    private function writeTypeAndLength(int $type, int $length): void
    {
        if ($length < 24) {
            $this->writeUint8(($type << 5) | $length);
        } else if ($length < 0x100) {
            $this->writeUint8(($type << 5) | 24);
            $this->writeUint8($length);
        } else if ($length < 0x10000) {
            $this->writeUint8(($type << 5) | 25);
            $this->writeUint16($length);
        } else if ($length < 0x100000000) {
            $this->writeUint8(($type << 5) | 26);
            $this->writeUint32($length);
        } else {
            $this->writeUint8(($type << 5) | 27);
            $this->writeUint64($length);
        }
    }

    private function writeArray(array $value): void
    {
        $startOffset = $this->offset;
        $length = count($value);
        $total = 0;

        $this->writeTypeAndLength(4, $length);

        $typeLengthOffset = $this->offset;
        $replacer = $this->replacer;

        for ($i = 0; $i < $length; $i++) {
            $result = $replacer($i, $value[$i]);
            if ($result === Cbor::OMIT_VALUE) continue;
            $this->encodeItem($result);
            $total++;
        }

        if ($length > $total) {
            $encoded = $this->byteView->buffer->slice($typeLengthOffset, $this->offset);
            $this->offset = $startOffset;
            $this->writeTypeAndLength(4, $total);
            $this->writeUint8Array($encoded->toArray());
        }
    }

    private function writeDictionary(array $value)
    {
        $encodedMap = [];
        $startOffset = $this->offset;

        $typeLengthOffset = $this->offset;
        $keyCount = count($value);
        $keyTotal = 0;

        $replacer = $this->replacer;

        // check if the keys of the value are that of a map
        if ($this->hasKeyValuePairs($value)) {
            $this->writeTypeAndLength(5, $keyCount);
            $typeLengthOffset = $this->offset;

            foreach ($value as $key => $val) {
                $result = $replacer($key, $value);

                if ($result === Cbor::OMIT_VALUE) {
                    continue;
                }

                $cursor = $this->offset;
                $this->encodeItem($key);

                $keyBytes = $this->byteView->buffer->slice($cursor, $this->offset);
                $cursor = $this->offset;
                $this->encodeItem($result);

                $valueBytes = $this->byteView->buffer->slice($cursor, $this->offset);
                $keyTotal++;

                $encodedMap[] = [$keyBytes, $valueBytes];
            }
        } else {
            $keys = array_keys($value);
            $this->writeTypeAndLength(5, $keyCount);
            $typeLengthOffset = $this->offset;

            for ($i = 0; $i < $keyCount; $i++) {
                $key = $keys[$i];
                $result = $replacer($key, $value[$key]);

                if ($result === Cbor::OMIT_VALUE) {
                    continue;
                }

                $cursor = $this->offset;
                $this->encodeItem($key);

                $keyBytes = $this->byteView->buffer->slice($cursor, $this->offset);
                $cursor = $this->offset;

                $this->encodeItem($result);
                $valueBytes = $this->byteView->buffer->slice($cursor, $this->offset);

                $keyTotal++;
                $encodedMap[] = [$keyBytes, $valueBytes];
            }
        }

        $encodedMapLength = count($encodedMap);
        if ($keyCount > $keyTotal) {
            if ($encodedMapLength > 1) {
                $this->sortEncodedKeys(
                    $encodedMap,
                    $startOffset,
                    $keyTotal,
                    $encodedMapLength
                );
            } else {
                $encoded = $this->byteView->buffer->slice($typeLengthOffset, $this->offset);
                $this->offset = $startOffset;
                $this->writeTypeAndLength(5, $keyTotal);
                $this->writeUint8Array($encoded->toArray());
            }
        } else {
            if ($encodedMapLength > 1) {
                $this->sortEncodedKeys(
                    $encodedMap,
                    $startOffset,
                    $keyTotal,
                    $encodedMapLength
                );
            }
        }
    }

    private function sortEncodedKeys(
        array &$encodedMap,
        int   $startOffset,
        int   $keyTotal,
        int   $length
    ): void
    {
        $this->offset = $startOffset;
        $this->writeTypeAndLength(5, $keyTotal);

        // sort the encoded keys
        $encodedMap = sort($encodedMap, function ($a, $b) {
            return $this->lexicographicalCompare($a, $b);
        });

        for ($i = 0; $i < $length; $i++) {
            [$encodedKey, $encodedValue] = $encodedMap[$i];
            $this->writeUint8Array($encodedKey);
            $this->writeUint8Array($encodedValue);
        }
    }

    /**
     * @throws CborReduxException
     */
    private function writeBigInteger(int $value): void
    {
        $type = 0;

        if (0 <= $value && $value <= PHP_INT_MAX) {
            $type = 0;
        } else if (-PHP_INT_MAX <= $value && $value < 0) {
            $type = 1;
            $value = -($value - 1);
        } else {
            throw new CborReduxException("Encountered unsafe integer outside of valid CBOR range");
        }

        if ($value < 0x100000000) {
            $this->writeTypeAndLength($type, $value);
        } else {
            $this->writeUint8(($type << 5) | 27);
            $this->writeUint64($value);
        }
    }

    /**
     * @throws CborReduxException
     */
    private function encodeItem(mixed $value): void
    {
        $replacer = $this->replacer;

        switch (true) {
            case $value === Cbor::OMIT_VALUE:
                return;
            case $value === false:
                $this->writeUint8(0xf4);
                break;
            case $value === true:
                $this->writeUint8(0xf5);
                break;
            case $value === null:
                $this->writeUint8(0xf6);
                break;
            case $this->objectIs($value, -0):
                $this->writeUint8Array([0xf9, 0x80, 0x00]); // <-- unsure
                break;
            case is_string($value):
                $utf8Data = [];
                $strLength = strlen($value);

                for ($i = 0; $i < $strLength; ++$i) {
                    $charCode = ord($value[$i]);
                    if ($charCode < 0x80) {
                        $utf8Data[] = $charCode;
                    } else if ($charCode < 0x800) {
                        $utf8Data[] = 0xc0 | ($charCode >> 6);
                        $utf8Data[] = 0x80 | ($charCode & 0x3f);
                    } else if ($charCode < 0xd800 || $charCode >= 0xe000) {
                        $utf8Data[] = 0xe0 | ($charCode >> 12);
                        $utf8Data[] = 0x80 | (($charCode >> 6) & 0x3f);
                        $utf8Data[] = 0x80 | ($charCode & 0x3f);
                    } else {
                        $charCode = ($charCode & 0x3ff) << 10;
                        $charCode != ord($value[++$i]) & 0x3ff;
                        $charCode += 0x10000;

                        $utf8Data[] = 0xf0 | ($charCode >> 18);
                        $utf8Data[] = 0x80 | (($charCode >> 12) & 0x3f);
                        $utf8Data[] = 0x80 | (($charCode >> 6) & 0x3f);
                        $utf8Data[] = 0x80 | ($charCode & 0x3f);
                    }
                }

                $this->writeTypeAndLength(3, count($utf8Data));
                $this->writeUint8Array($utf8Data);
                break;
            case is_int($value):
                if ((int)floor($value) === $value) {
                    if (0 <= $value && $value <= self::POW_2_53) {
                        $this->writeTypeAndLength(0, $value);
                        break;
                    } else if (-self::POW_2_53 <= $value && $value < 0) {
                        $this->writeTypeAndLength(1, -($value + 1));
                        break;
                    }
                }

                $this->writeUint8(0xfb);
                $this->writeFloat64($value);
                break;
            default:
                if (is_array($value)) {
                    $this->writeArray($replacer(Cbor::EMPTY_KEY, $value));
                } else if ($value instanceof TaggedValue) {
                    $this->writeVarUint($value->tag, 0b11000000);
                    $this->encodeItem($value->value);
                } else if ($value instanceof SimpleValue) {
                    $this->writeTypeAndLength(7, $value->value);
                } else if ($value instanceof Sequence) {
                    if ($this->offset !== 0) throw new CborReduxException("A Cbor Sequence may not be nested.");
                    $length = $value->size();
                    for ($i = 0; $i < $length; $i++) $this->encodeItem($value->get($i));
                } else {
                    $this->writeDictionary($value);
                }
                break;
        }
    }

    private function objectIs(mixed $x, mixed $y): bool
    {
        // ...

        if ($x === $y) {
            return $x !== 0 || 1 / $x === 1 / $y;
        }

        return $x !== $x && $y !== $y;
    }

    private function hasKeyValuePairs(array $value): bool
    {
        $i = 0;
        foreach ($value as $key => $val) {
            if ($key !== $i++) return true;
        }
        return false;
    }

    private function lexicographicalCompare(Uint8Array $left, Uint8Array $right) {
        $minLength = min($left->byteLength(), $right->byteLength());

        for($i = 0; $i < $minLength; $i++) {
            $result = $left->buffer->offsetGet($i) - $right->buffer->offsetGet($i);
            if($result !== 0) return $result;
        }

        return $left->byteLength() - $right->byteLength();
    }
}