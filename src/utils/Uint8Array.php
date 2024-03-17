<?php

namespace Beau\CborReduxPhp\utils;

use SplFixedArray;

class Uint8Array
{
    public ArrayBuffer $buffer;
    private int $offset;

    public function __construct(ArrayBuffer|SplFixedArray|array|int $buffer, ?int $offset = null, ?int $length = null)
    {
        $initialize = function (ArrayBuffer $target) use ($buffer) {
            foreach ($buffer as $key => $value) {
                $target->set($key, $value);
            }
        };

        if (is_array($buffer)) {
            $buffer = new ArrayBuffer(count($buffer));
            $initialize($buffer);
        } else if ($buffer instanceof SplFixedArray) {
            $buffer = new ArrayBuffer($buffer->getSize());
            $initialize($buffer);
        } else if ($buffer instanceof ArrayBuffer) {
            $this->buffer = $buffer;
        }

        if ($offset !== null && $length !== null) {
            $this->buffer = $buffer->slice($offset, $offset + $length);
            $this->offset = $offset;
        }
    }

    public function set(int $index, int $value): void
    {
        $this->buffer[$index] = $value;
    }

    public function byteLength(): int
    {
        return $this->buffer->byteLength();
    }

    public function byteOffset(): int
    {
        return $this->offset;
    }
}