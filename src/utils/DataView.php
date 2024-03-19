<?php

namespace Beau\CborReduxPhp\utils;

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

    public function getFloat16(int $byteOffset, bool $littleEndian = false): float
    {
        $value = $this->getUint16($byteOffset, $littleEndian);
        $sign = ($value & 0x8000) ? -1 : 1;

        $exponent = ($value & 0x7C00) >> 10;
        $fraction = $value & 0x03FF;

        if ($exponent === 0x1F) {
            if ($fraction === 0) {
                return $sign * INF;
            }
            return NAN;
        }

        if ($exponent === 0) {
            return $sign * 6.103515625E-5 * ($fraction / 0x0400);
        }

        return $sign * pow(2, $exponent - 15) * (1 + $fraction / 0x0400);
    }

    public function getFloat32(int $byteOffset, bool $littleEndian = false): float
    {
        $value = $this->getUint32($byteOffset, $littleEndian);
        $sign = ($value & 0x80000000) ? -1 : 1;

        $exponent = ($value & 0x7F800000) >> 23;
        $fraction = $value & 0x007FFFFF;

        if ($exponent === 0xFF) {
            if ($fraction === 0) {
                return $sign * INF;
            }
            return NAN;
        }

        if ($exponent === 0) {
            return $sign * pow(2, -126) * ($fraction / 0x00800000);
        }

        return $sign * pow(2, $exponent - 127) * (1 + $fraction / 0x00800000);
    }

    public function getFloat64(int $byteOffset, bool $littleEndian = false): float
    {
        $value = $this->getUint64($byteOffset, $littleEndian);
        $sign = ($value & 0x8000000000000000) ? -1 : 1;

        $exponent = ($value & 0x7FF0000000000000) >> 52;
        $fraction = $value & 0x000FFFFFFFFFFFFF;

        if ($exponent === 0x7FF) {
            if ($fraction === 0) {
                return $sign * INF;
            }
            return NAN;
        }

        if ($exponent === 0) {
            return $sign * pow(2, -1022) * ($fraction / 0x0010000000000000);
        }

        return $sign * pow(2, $exponent - 1023) * (1 + $fraction / 0x0010000000000000);
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

    public function setBigUint64(int $byteOffset, int $value, bool $littleEndian = false): void
    {
        // Ensure byte offset is within the range of the data array
        if ($byteOffset < 0 || $byteOffset + 8 > count($this->data)) {
            throw new \OutOfRangeException('Byte offset out of range');
        }

        // Split the 64-bit integer into 8 bytes
        $bytes = [];
        for ($i = 0; $i < 8; $i++) {
            $bytes[] = ($value >> (8 * (7 - $i))) & 0xFF;
        }

        // If little-endian, reverse the byte order
        if ($littleEndian) {
            $bytes = array_reverse($bytes);
        }

        // Set the bytes at the specified byte offset
        for ($i = 0; $i < 8; $i++) {
            $this->data[$byteOffset + $i] = $bytes[$i];
        }
    }
}