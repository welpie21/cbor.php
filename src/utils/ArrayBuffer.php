<?php

namespace Beau\CborReduxPhp\utils;

use ArrayAccess;
use Countable;
use Iterator;
use SplFixedArray;


class ArrayBuffer implements Iterator, ArrayAccess, Countable
{
    private array $buffer;
    private int $index = 0;

    public function __construct(array|int|SplFixedArray $value)
    {
        if (is_int($value)) {
            $this->buffer = array_fill(0, $value, 0);
        } else if ($value instanceof SplFixedArray) {
            $this->buffer = $value->toArray();
        } else if (is_array($value)) {
            $this->buffer = $value;
        }
    }

    public static function isView(mixed $value): bool
    {
        return $value instanceof ArrayBuffer;
    }

    public function slice(int $start, int $end): ArrayBuffer
    {
        $buffer = clone $this->buffer;
        $sliced = array_slice($buffer, $start, $end - $start);
        return new ArrayBuffer($sliced);
    }

    public function set(int $index, mixed $value): void
    {
        $this->buffer[$index] = $value;
    }

    public function current(): mixed
    {
        return $this->buffer->offsetGet($this->index);
    }

    public function next(): void
    {
        $this->index++;
    }

    public function key(): string|int
    {
        return array_keys($this->buffer)[$this->index];
    }

    public function valid(): bool
    {
        return $this->index < $this->buffer->getSize();
    }

    public function rewind(): void
    {
        $this->index = 0;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->buffer->offsetExists($offset);
    }
    public function offsetGet(mixed $offset): mixed
    {
        return $this->buffer->offsetGet($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->buffer->offsetSet($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->buffer->offsetUnset($offset);
    }

    public function count(): int
    {
        return $this->buffer->getSize();
    }

    public function toArray(): array
    {
        return $this->buffer;
    }
}