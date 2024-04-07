<?php

namespace Beau\CborPHP;

use Beau\CborPHP\classes\FloatHelper;
use Beau\CborPHP\classes\IntHelper;
use Beau\CborPHP\classes\TaggedValue;
use Beau\CborPHP\exceptions\CborException;
use Closure;

class CborDecoder
{
    private array $buffer;
    private int $offset;
    private Closure $replacer;

    private static array $byteLength = [
        24 => 1,
        25 => 2,
        26 => 4,
        27 => 8
    ];

    private function __construct(array $buffer, ?Closure $replacer)
    {
        $this->offset = 1;
        $this->buffer = $buffer;

        $this->replacer = $replacer ?? fn($key, $value) => $value;
    }

    /**
     * @throws CborException
     */
    private function decodeArray(int $additional): array
    {
        $length = $this->decodeInt($additional);
        $array = [];

        for ($i = 0; $i < $length; $i++) {
            $array[] = $this->decodeNext();
        }

        return $array;
    }

    /**
     * @throws CborException
     */
    private function decodeInt(int $additional, bool $signed = false): int
    {
        if ($additional <= 23) {
            return $signed ? -($additional + 1) : $additional;
        }

        $byteLength = self::$byteLength[$additional];

        $value = match ($byteLength) {
            1 => IntHelper::getUint8($this->buffer, $this->offset),
            2 => IntHelper::getUint16($this->buffer, $this->offset),
            4 => IntHelper::getUint32($this->buffer, $this->offset),
            8 => IntHelper::getUint64($this->buffer, $this->offset),
            default => throw new CborException("Invalid byte length: " . $byteLength . " for additional: " . $additional)
        };

        $value = $signed ? -($value + 1) : $value;
        $this->offset += $byteLength;

        return $value;
    }

    /**
     * @throws CborException
     */
    private function decodeMap(int $additional): array
    {
        $length = $this->decodeInt($additional);
        $map = [];

        for ($i = 0; $i < $length; $i++) {
            $key = $this->decodeNext();
            $value = $this->decodeNext();
            $map[$key] = $value;
        }

        return $map;
    }

    /**
     * @throws CborException
     */
    private function decodeString(int $additional): string
    {
        $string = "";
        $length = $this->decodeInt($additional);

        for ($i = 0; $i < $length; $i++) {
            $string .= chr($this->buffer[$this->offset++]);
        }

        return $string;
    }

    /**
     * @throws CborException
     */
    private function decodeByteString(int $additional): string
    {
        $string = "";
        $length = $this->decodeInt($additional);

        for ($i = 0; $i < $length; $i++) {
            $string .= chr($this->buffer[$this->offset++]);
        }

        return $string;
    }

    /**
     * @throws CborException
     */
    private function decodeTag(int $additional): mixed
    {
        $tag = $this->decodeInt($additional);
        $value = $this->decodeNext();

        return $this->replacer->call($this, null, new TaggedValue($tag, $value));
    }

    /**
     * @throws CborException
     */
    private function decodeFloat(int $additional): float
    {
        $byteLength = self::$byteLength[$additional];
        $value = match ($byteLength) {
            2 => FloatHelper::getFloat16($this->buffer, $this->offset),
            4 => FloatHelper::getFloat32($this->buffer, $this->offset),
            8 => FloatHelper::getFloat64($this->buffer, $this->offset),
            default => throw new CborException("Invalid byte length: " . $byteLength . " for additional: " . $additional)
        };

        $this->offset += $byteLength;
        return $value;
    }

    /**
     * @throws CborException
     */
    private function decodeSimple(int $additional): bool|null|float
    {
        return match ($additional) {
            20 => false,
            21 => true,
            22, 23 => null,
            25, 26, 27 => $this->decodeFloat($additional),
            default => throw new CborException("Unsupported simple value: $additional")
        };
    }

    /**
     * @throws CborException
     */
    private function decodeNext(): mixed
    {
        $byte = $this->buffer[$this->offset++];
        $majorTag = $byte >> 5;
        $additionalInfo = $byte & 0x1F;

        return match ($majorTag) {
            0 => $this->decodeInt($additionalInfo),
            1 => $this->decodeInt($additionalInfo, true),
            2 => $this->decodeByteString($additionalInfo),
            3 => $this->decodeString($additionalInfo),
            4 => $this->decodeArray($additionalInfo),
            5 => $this->decodeMap($additionalInfo),
            6 => $this->decodeTag($additionalInfo),
            7 => $this->decodeSimple($additionalInfo),
            default => throw new CborException("Unsupported major tag: $majorTag")
        };
    }

    /**
     * @throws CborException
     */
    public function decodeItem(): mixed
    {
        return $this->decodeNext();
    }

    /**
     * @throws CborException
     */
    public static function decode(string $data, ?Closure $replacer = null): mixed
    {
        $binary = hex2bin($data);

        if ($binary === false) {
            throw new CborException("Invalid hex string");
        }

        $decoder = new CborDecoder(unpack("C*", $binary), $replacer);

        return $decoder->decodeItem();
    }
}