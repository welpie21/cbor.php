<?php

namespace Beau\CborReduxPhp;

use Beau\CborReduxPhp\enums\Cbor;
use Beau\CborReduxPhp\exceptions\CborReduxException;
use Beau\CborReduxPhp\utils\ArrayBuffer;
use Beau\CborReduxPhp\utils\DataView;
use Beau\CborReduxPhp\utils\Uint8Array;
use Closure;

class CborEncoder
{
    const POW_2_32 = 4294967296;

    private ArrayBuffer $data;
    private DataView $view;
    private Uint8Array $byteView;
    private int $lastLength;
    private int $offset = 0;
    private Closure $replacer;

    private function encode(mixed $value, Closure|array|null $replacer)
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
            $this->replacer = fn($key, $value) => $value;
        }

        $this->data = new ArrayBuffer(256);
        $this->view = new DataView($this->data);
        $this->byteView = new Uint8Array($this->data);
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

    private function writeArray(array $value)
    {

    }

//    private function writeDictionary(array $value)
//    {
//
//    }

    private function sortEncodedKeys(int $length)
    {

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

    private function encodeItem(mixed $value)
    {
//        if ($value === Encode::OMIT_VALUE) {
//            return;
//        } else if ($value === false) {
//            return $this->writeUint8(0xf4);
//        }

        switch (true) {
            case $value === Encode::OMIT_VALUE:
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
        }
    }

    private function objectIs(mixed $x, mixed $y)
    {
        // ...

        if($x === $y) {
            return $x !== 0 || 1 / $x === 1 / $y;
        }

        return $x !== $x && $y !== $y;
    }

    private static function encode(mixed $value, Closure $replacer = null)
    {

    }
}