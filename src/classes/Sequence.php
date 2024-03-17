<?php

namespace Beau\CborReduxPhp\classes;

use OutOfRangeException;
use SplFixedArray;

class Sequence
{
    private array $data;
    private int $size;

    public function __construct(array|null $data = null)
    {
        $this->data = $data ?? [];
        $this->size = $data ? count($this->data) : 0;
    }

    public function add(mixed $value): int
    {
        $this->data[] = $value;
        return ++$this->size;
    }

    public function remove(int $index): mixed
    {
        if ($index < 0 || $index >= $this->size) {
            throw new OutOfRangeException("Index out of range");
        }

        $value = $this->data[$index];
        array_splice($this->data, $index, 1);
        $this->size--;

        return $value;
    }

    public function get(int $index): mixed
    {
        if ($index < 0 || $index >= $this->size) {
            throw new OutOfRangeException("Index out of range");
        }

        return $this->data[$index];
    }

    public function size(): int
    {
        return $this->size;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}