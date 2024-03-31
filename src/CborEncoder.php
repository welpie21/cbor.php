<?php

namespace Beau\CborReduxPhp;

use Beau\CborReduxPhp\abstracts\AbstractTaggedValue;
use Beau\CborReduxPhp\classes\SimpleValue;
use Beau\CborReduxPhp\classes\TaggedValue;
use Beau\CborReduxPhp\exceptions\CborReduxException;
use Closure;

class CborEncoder
{
    private string $buffer;
    private Closure $replacer;
    private static array $lengthPackType = [
        24 => "C",
        25 => "n",
        26 => "N"
    ];

    private static array $floatPackType = [
        26 => "f",
        27 => "d"
    ];

    public function __construct(?Closure $replacer = null)
    {
        $this->replacer = $replacer ?? fn($key, $value) => $value;
        $this->buffer = "";
    }

    private function packInitialByte(int $majorType, int $additionalInfo): void
    {
        $this->buffer .= pack("c", $majorType | $additionalInfo);
    }

    private function packNumber(int $majorType, int $value): void
    {
        if ($value <= 23) {
            $this->packInitialByte($majorType, $value);
            return;
        }

        $length = $this->getLength($value);

        if ($length === null) {
            $this->packInitialByte($majorType, 27);
            $this->packBigInt($value);
        } else {
            $this->packInitialByte($majorType, $length);
            $this->buffer .= pack(self::$lengthPackType[$length], $value);
        }
    }

    private function packBigInt(int $value): void
    {
        $this->buffer .= pack(
            "NN",
            ($value & 0xffffffff00000000) >> 32,
            ($value & 0x00000000ffffffff)
        );
    }

    private function getLength(int $value): ?int
    {
        return match (true) {
            $value < 256 => 24,
            $value < 65536 => 25,
            $value < 4294967296 => 26,
            default => null
        };
    }

    /**
     * @throws CborReduxException
     */
    private function encodeArray(array $array): void
    {
        $arrayLength = count($array);
        $isMap = $this->isAssoc($array, $arrayLength);

        // 0b10100000 = map
        // 0b10000000 = array
        $majorType = $isMap ? 0b10100000 : 0b10000000;

        $this->packNumber($majorType, $arrayLength);

        foreach ($array as $key => $value) {
            $encodeList = $isMap ? [$key, $value] : [$value];
            foreach ($encodeList as $item) {
                $this->encode($item);
            }
        }
    }

    private function packInt(int $value): void
    {
        if ($value < 0) {
            $this->packNumber(32, abs($value) - 1);
        } else {
            $this->packNumber(0, $value);
        }
    }

    private function packDouble(float $value): void
    {
        $this->packInitialByte(7 << 5, 27);
        $this->buffer .= strrev(pack("d", $value));
    }

    private function packFloat(float $value): void
    {
        $this->packInitialByte(7 << 5, 26);
        $this->buffer .= strrev(pack("f", $value));
    }

    private function isAssoc(array $array, int $length): bool
    {
        return array_keys($array) !== range(0, $length - 1);
    }

    private function packString(string $value): void
    {
        $this->packNumber(3 << 5, strlen($value));
        $this->buffer .= $value;
    }

    private function packBoolean(bool $value): void
    {
        $this->packInitialByte(7 << 5, $value ? 21 : 20);
    }

    private function packNull(): void
    {
        $this->packInitialByte(7 << 5, 22);
    }

    private function packUndefined(): void
    {
        $this->packInitialByte(7 << 5, 23);
    }

    /**
     * @return string
     */
    private function getResult(): string
    {
        return $this->buffer;
    }

    /**
     * @throws CborReduxException
     */
    public function encode(mixed $value): string
    {
        switch (true) {
            case is_int($value):
                $this->packInt($value);
                break;
            case is_double($value):
                $this->packDouble($value);
                break;
            case is_float($value):
                $this->packFloat($value);
                break;
            case is_string($value):
                $this->packString($value);
                break;
            case is_array($value):
                $this->encodeArray($value);
                break;
            case is_bool($value):
                $this->packBoolean($value);
                break;
            case is_null($value):
                $this->packNull();
                break;
            case $value instanceof AbstractTaggedValue:
                $this->packNumber(6 << 5, $value->tag);
                $this->encode($value->value);
                break;
            case is_nan($value):
                $this->packUndefined();
                break;
        }

        return $this->getResult();
    }
}