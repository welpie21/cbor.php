<?php

namespace Beau\CborPHP;

use Beau\CborPHP\exceptions\CborException;
use Beau\CborPHP\abstracts\AbstractTaggedValue;
use Beau\CborPHP\utils\CborByteString;
use Closure;

class CborEncoder
{
    private string $buffer = "";
    private Closure $replacer;

    private static array $lengthPackType = [
        24 => "C",
        25 => "n",
        26 => "N"
    ];

    /**
     * @throws CborException
     */
    public function __construct(mixed $value, ?Closure $replacer = null)
    {
        $this->replacer = $replacer ?? fn($key, $value) => $value;
        $this->encodeItem($value);
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
        $this->buffer .= pack("J", $value);
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
     * @throws CborException
     */
    private function encodeArray(array $array): void
    {
        $arrayLength = count($array);
        $isMap = $this->isAssoc($array, $arrayLength);

        // 0b10100000 = map
        // 0b10000000 = array
        $majorType = $isMap ? 0b10100000 : 0b10000000;

        $this->packNumber($majorType, $arrayLength);

        if ($isMap) {
            foreach ($array as $key => $value) {
                $this->encodeItem($key);
                $this->encodeItem($value);
            }
        } else {
            foreach ($array as $value) {
                $this->encodeItem($value);
            }
        }
    }

    private function packInt(int $value): void
    {
        if ($value < 0) {
            $this->packNumber(1 << 5, abs($value) - 1);
        } else {
            $this->packNumber(0, $value);
        }
    }

    private function packDouble(float $value): void
    {
        if (is_nan($value)) {
            $this->packNaN();
            return;
        }

        $this->packInitialByte(7 << 5, 27);
        $this->buffer .= strrev(pack("d", $value));
    }

    private function isAssoc(array $array, int $length): bool
    {
        return !array_is_list($array);
    }

    private function packString(string $value, bool $byte = false): void
    {
        $length = strlen($value);

        if ($byte) {
            $this->packNumber(2 << 5, $length);
            $this->buffer .= $value;
        } else {
            $this->packNumber(3 << 5, $length);
            $this->buffer .= $value;
        }
    }

    private function packBoolean(bool $value): void
    {
        $this->packInitialByte(7 << 5, $value ? 21 : 20);
    }

    private function packNull(): void
    {
        $this->packInitialByte(7 << 5, 22);
    }

    private function packNaN(): void
    {
        $this->buffer .= hex2bin("f97e00"); // War crime has been committed here.
    }

    /**
     * @return string
     */
    private function getResult(): string
    {
        return $this->buffer;
    }

    /**
     * @throws CborException
     */
    private function encodeItem(mixed $value): void
    {
        switch (true) {
            case is_int($value):
                $this->packInt($value);
                break;
            case is_double($value):
                $this->packDouble($value);
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
                $this->packInitialByte(6 << 5, $value->tag);
                $this->encodeItem($value->value);
                break;
            case $value instanceof CborByteString:
                $this->packString($value->getByteString(), true);
                break;
            default:
                $value = $this->replacer->call($this, null, $value);
                $this->encodeItem($value);
        }
    }

    /**
     * @throws CborException
     */
    public static function encode(mixed $value, ?Closure $replacer = null): string
    {
        $encoder = new self($value, $replacer);
        return $encoder->getResult();
    }
}