<?php

namespace Beau\CborPHP;

use Beau\CborPHP\classes\FloatHelper;
use Beau\CborPHP\classes\IntHelper;
use Closure;
use Exception;

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
     * @throws Exception
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
     * @throws Exception
     */
    private function decodeInt(int $additional, bool $signed = false): int
    {
        if ($additional <= 23) {
            return $additional;
        }

        $byteLength = self::$byteLength[$additional];

        $value = match ($byteLength) {
            1 => IntHelper::getUint8($this->buffer, $this->offset),
            2 => IntHelper::getUint16($this->buffer, $this->offset),
            4 => IntHelper::getUint32($this->buffer, $this->offset),
            8 => IntHelper::getUint64($this->buffer, $this->offset),
            default => throw new Exception("Invalid byte length: " . $byteLength)
        };

        $value = $signed ? (-1 - $value) : $value;
        $this->offset += $byteLength;

        return $value;
    }

    /**
     * @throws Exception
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
     * @throws Exception
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
     * @throws Exception
     */
    private function decodeByteString(int $additional): string
    {
        $length = $this->decodeInt($additional);
        $byteString = "";

        for ($i = 0; $i < $length; $i++) {
            var_dump($this->buffer[$this->offset++]);
        }

        return $byteString;
    }

    private function decodeTag(int $additional): mixed
    {
        return $additional;
    }

    /**
     * @throws Exception
     */
    private function decodeSimple(int $additional): bool|null|float
    {
        return match ($additional) {
            20 => false,
            21 => true,
            22, 23 => null,
            25 => FloatHelper::getFloat16($this->buffer, $this->offset),
            26 => FloatHelper::getFloat32($this->buffer, $this->offset),
            27 => FloatHelper::getFloat64($this->buffer, $this->offset),
            default => throw new Exception("Unsupported simple value: $additional")
        };
    }

    /**
     * @throws Exception
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
            default => throw new Exception("Unsupported major tag: $majorTag")
        };
    }

    /**
     * @throws Exception
     */
    public function decodeItem(): mixed
    {
        return $this->decodeNext();
    }

    /**
     * @throws Exception
     */
    public static function decode(string $data, ?Closure $replacer = null): mixed
    {
        $binary = hex2bin($data);

        if ($binary === false) {
            throw new Exception("Invalid hex string");
        }

        $decoder = new CborDecoder(unpack("C*", $binary), $replacer);
        return $decoder->decodeItem();
    }
}