<?php

namespace Beau\CborReduxPhp\utils;


use SplFixedArray;

class DataView
{
    private array $data;

    public function __construct(array|ArrayBuffer $buffer, ?int $byteOffset = null, ?int $byteLength = null)
    {
        if ($buffer instanceof ArrayBuffer) {
            if ($byteOffset !== null && $byteLength !== null) {
                $this->data = $buffer->slice($byteOffset, $byteOffset + $byteLength)->toArray();
            } else {
                $this->data = $buffer->toArray();
            }
        } else {
            if ($byteOffset !== null && $byteLength !== null) {
                $this->data = array_slice($buffer, $byteOffset, $byteLength);
            } else {
                $this->data = $buffer;
            }
        }
    }

    public function getUint8(int $byteOffset): int
    {
        return $this->data[$byteOffset];
    }

    public function getUint16(int $byteOffset, bool $littleEndian = false): int
    {
        $value = $this->data[$byteOffset] << 8 | $this->data[$byteOffset + 1];
        if ($littleEndian) {
            $value = ($value & 0xFF00) >> 8 | ($value & 0x00FF) << 8;
        }
        return $value;
    }

    public function getUint32(int $byteOffset, bool $littleEndian = false): int
    {
        $value = $this->data[$byteOffset] << 24 |
            $this->data[$byteOffset + 1] << 16 |
            $this->data[$byteOffset + 2] << 8 |
            $this->data[$byteOffset + 3];

        if ($littleEndian) {
            $value =
                ($value & 0xFF000000) >> 24 |
                ($value & 0x00FF0000) >> 8 |
                ($value & 0x0000FF00) << 8 |
                ($value & 0x000000FF) << 24;
        }

        return $value;
    }

    public function getUint64(int $byteOffset, bool $littleEndian = false): int
    {
        $value = $this->data[$byteOffset] << 56 |
            $this->data[$byteOffset + 1] << 48 |
            $this->data[$byteOffset + 2] << 40 |
            $this->data[$byteOffset + 3] << 32 |
            $this->data[$byteOffset + 4] << 24 |
            $this->data[$byteOffset + 5] << 16 |
            $this->data[$byteOffset + 6] << 8 |
            $this->data[$byteOffset + 7];

        if ($littleEndian) {
            $value = ($value & 0xFF00000000000000) >> 56 |
                ($value & 0x00FF000000000000) >> 40 |
                ($value & 0x0000FF0000000000) >> 24 |
                ($value & 0x000000FF00000000) >> 8 |
                ($value & 0x00000000FF000000) << 8 |
                ($value & 0x0000000000FF0000) << 24 |
                ($value & 0x000000000000FF00) << 40 |
                ($value & 0x00000000000000FF) << 56;
        }

        return $value;
    }

    public function getFloat32(int $byteOffset, bool $littleEndian = false): float
    {
        return $this->getUint32($byteOffset, $littleEndian);
    }

    public function getFloat64(int $byteOffset, bool $littleEndian = false): float
    {
        return $this->getUint64($byteOffset, $littleEndian);
    }

    public function setUint8(int $byteOffset, int $value): void
    {
        $this->data[$byteOffset] = $value;
    }

    public function setUint16(int $byteOffset, int $value, bool $littleEndian = false): void
    {
        if ($littleEndian) {
            $this->data[$byteOffset] = $value & 0x00FF;
            $this->data[$byteOffset + 1] = ($value & 0xFF00) >> 8;
        } else {
            $this->data[$byteOffset] = ($value & 0xFF00) >> 8;
            $this->data[$byteOffset + 1] = $value & 0x00FF;
        }
    }

    public function setUint32(int $byteOffset, int $value, bool $littleEndian = false): void
    {
        if ($littleEndian) {
            $this->data[$byteOffset] = $value & 0x000000FF;
            $this->data[$byteOffset + 1] = ($value & 0x0000FF00) >> 8;
            $this->data[$byteOffset + 2] = ($value & 0x00FF0000) >> 16;
            $this->data[$byteOffset + 3] = ($value & 0xFF000000) >> 24;
        } else {
            $this->data[$byteOffset] = ($value & 0xFF000000) >> 24;
            $this->data[$byteOffset + 1] = ($value & 0x00FF0000) >> 16;
            $this->data[$byteOffset + 2] = ($value & 0x0000FF00) >> 8;
            $this->data[$byteOffset + 3] = $value & 0x000000FF;
        }
    }

    public function setUint64(int $byteOffset, int $value, bool $littleEndian = false): void
    {
        if ($littleEndian) {
            $this->data[$byteOffset] = $value & 0x00000000000000FF;
            $this->data[$byteOffset + 1] = ($value & 0x000000000000FF00) >> 8;
            $this->data[$byteOffset + 2] = ($value & 0x0000000000FF0000) >> 16;
            $this->data[$byteOffset + 3] = ($value & 0x00000000FF000000) >> 24;
            $this->data[$byteOffset + 4] = ($value & 0x000000FF00000000) >> 32;
            $this->data[$byteOffset + 5] = ($value & 0x0000FF0000000000) >> 40;
            $this->data[$byteOffset + 6] = ($value & 0x00FF000000000000) >> 48;
            $this->data[$byteOffset + 7] = ($value & 0xFF00000000000000) >> 56;
        } else {
            $this->data[$byteOffset] = ($value & 0xFF00000000000000) >> 56;
            $this->data[$byteOffset + 1] = ($value & 0x00FF000000000000) >> 40;
            $this->data[$byteOffset + 2] = ($value & 0x0000FF0000000000) >> 24;
            $this->data[$byteOffset + 3] = ($value & 0x000000FF00000000) >> 8;
            $this->data[$byteOffset + 4] = $value & 0x00000000FF000000;
            $this->data[$byteOffset + 5] = $value & 0x0000000000FF0000;
            $this->data[$byteOffset + 6] = $value & 0x000000000000FF00;
            $this->data[$byteOffset + 7] = $value & 0x00000000000000FF;
        }
    }

    public function setFloat32(int $byteOffset, float $value, bool $littleEndian = false): void
    {
        $this->setUint32($byteOffset, $value, $littleEndian);
    }

    public function setFloat64(int $byteOffset, float $value, bool $littleEndian = false): void
    {
        $this->setUint64($byteOffset, $value, $littleEndian);
    }
}