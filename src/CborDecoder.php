<?php

namespace Beau\CborReduxPhp;

use Beau\CborReduxPhp\classes\Sequence;
use Beau\CborReduxPhp\classes\SimpleValue;
use Beau\CborReduxPhp\classes\TaggedValue;
use Beau\CborReduxPhp\enums\Tag;
use Beau\CborReduxPhp\exceptions\CborReduxException;
use Beau\CborReduxPhp\utils\DataView;
use Beau\CborReduxPhp\utils\Uint8Array;
use Closure;
use Exception;

class CborDecoder
{
    private DataView $view;
    private array $options;
    private int $offset = 1;
    private Uint8Array $ta;
    private Closure $reviverFunction;

    /**
     * @param array $data
     * @param callable|null $reviver
     * @param array $options
     * @return mixed
     * @throws CborReduxException
     */
    public function decode(
        array     $data,
        ?callable $reviver = null,
        array     $options = []
    ): mixed
    {
        $this->offset = 1;
        $this->view = new DataView($data);
        $this->ta = new Uint8Array($data);

        $this->options = $options;
        $this->reviverFunction = $reviver ?? fn($key, $value) => $value;

        $ret = $this->decodeItem();

        if ($this->offset !== count($data)) {
            // if mode sequence
            // throw new CborReduxException("Remaining bytes");
            //

            $sequence = new Sequence([$ret]);

            while ($this->offset < count($data)) {
                $reviver = $this->reviverFunction;
                $sequence->add(
                    $reviver(null, $this->decodeItem())
                );
            }

            return $sequence->toArray();
        }

        // return $mode === "sequence" ? new Sequence([$ret]) : $ret;
        return $ret;
    }

    private function commitRead(int $length, mixed $value): mixed
    {
        $this->offset += $length;
        return $value;
    }

    private function readArrayBuffer(int $length)
    {
        $intArray = $this->ta->buffer->slice($this->offset, $this->offset + $length);
        return $this->commitRead($length, $intArray);
    }

    private function readFloat16(): float
    {
        return $this->commitRead(2, $this->view->getFloat16($this->offset));
    }

    private function readFloat32(): float
    {
        return $this->commitRead(4, $this->view->getFloat32($this->offset));
    }

    private function readFloat64(): float
    {
        return $this->commitRead(8, $this->view->getFloat64($this->offset));
    }

    private function readUint8(): int
    {
        return $this->commitRead(1, $this->view->getUint8($this->offset));
    }

    private function readUint16(): int
    {
        return $this->commitRead(2, $this->view->getUint16($this->offset));
    }

    private function readUint32(): int
    {
        return $this->commitRead(4, $this->view->getUint32($this->offset));
    }

    private function readUint64(): int
    {
        return $this->commitRead(8, $this->view->getUint64($this->offset));
    }

    private function readBreak(): bool
    {
        if ($this->ta[$this->offset] !== 0xff) {
            return false;
        }

        $this->offset++;
        return true;
    }

    /**
     * @throws CborReduxException
     */
    private function readLength(int $additionalInformation): int
    {
        return match (true) {
            $additionalInformation < 24 => $additionalInformation,
            $additionalInformation === 24 => $this->readUint8(),
            $additionalInformation === 25 => $this->readUint16(),
            $additionalInformation === 26 => $this->readUint32(),
            $additionalInformation === 27 => $this->readUint64(),
            $additionalInformation === 31 => -1,
            default => throw new CborReduxException("Invalid length encoding"),
        };
    }

    /**
     * @throws CborReduxException
     */
    private function readIndefiniteStringLength(int $majorType): int
    {
        $initialByte = $this->readUint8();

        if ($initialByte === 0xff) {
            return -1;
        }

        $length = $this->readLength($initialByte & 0x1f);

        if ($length < 0 || $initialByte >> 5 !== $majorType) {
            throw new CborReduxException("Invalid indefinite length element");
        }

        return $length;
    }

    /**
     * @param array $utf16Data
     * @param int $length
     * @return void
     */
    private function appendUtf16Data(array &$utf16Data, int &$length): void
    {
        for ($i = 0; $i < $length; ++$i) {
            $value = $this->readUint8();
            if ($value & 0x80) {
                if ($value < 0xe0) {
                    $value = (($value & 0x1f) << 6) | ($this->readUint8() & 0x3f);
                    $length--;
                } else if ($value < 0xf0) {
                    $value = (($value & 0x0f) << 12) |
                        (($this->readUint8() & 0x3f) << 6) |
                        ($this->readUint8() & 0x3f);
                    $length -= 2;
                } else {
                    $value = (($value & 0x0f) << 18) |
                        (($this->readUint8() & 0x3f) << 12) |
                        (($this->readUint8() & 0x3f) << 6) |
                        ($this->readUint8() & 0x3f);
                    $length -= 3;
                }
            }

            if ($value < 0x10000) {
                $utf16Data[] = $value;
            } else {
                $value -= 0x10000;
                $utf16Data[] = 0xd800 | ($value >> 10);
                $utf16Data[] = 0xdc00 | ($value & 0x3ff);
            }
        }
    }

    /**
     * @throws CborReduxException
     */
    private function decodeItem(): mixed
    {
        $initialByte = $this->readUint8();
        $majorType = $initialByte >> 5;
        $additionalInformation = $initialByte & 0x1f;

        if ($majorType === 7) {
            switch ($additionalInformation) {
                case 25:
                    return $this->readFloat16();
                case 26:
                    return $this->readFloat32();
                case 27:
                    return $this->readFloat64();
            }
        }

        $length = $this->readLength($additionalInformation);

        if ($length < 0 && ($majorType < 2 || 6 < $majorType)) {
            throw new CborReduxException("Invalid length");
        }

        $reviverFunction = $this->reviverFunction;

        switch ($majorType) {
            case 0:
                return $reviverFunction(null, $length);
            case 1:
                return $reviverFunction(null, -1 - $length);
            case 2:
                if ($length < 0) {
                    $elements = [];
                    $fullArrayLength = 0;

                    while (($length = $this->readIndefiniteStringLength($majorType)) >= 0) {
                        $fullArrayLength += $length;
                        $elements[] = $this->readArrayBuffer($length);
                    }

                    $fullArray = new Uint8Array($fullArrayLength);
                    $fullArrayOffset = 0;

                    for ($i = 0; $i < count($elements); ++$i) {
                        $fullArray->set($fullArrayOffset, $elements[$i]);
                        $fullArrayOffset += $elements[$i]->byteLength();
                    }

                    return $reviverFunction(null, $fullArray);
                }

                return $reviverFunction(null, $this->readArrayBuffer($length));
            case 3:
                $utf16Data = [];
                if ($length < 0) {
                    while (($length = $this->readIndefiniteStringLength($majorType)) >= 0) {
                        $this->appendUtf16Data($utf16Data, $length);
                    }
                } else {
                    $this->appendUtf16Data($utf16Data, $length);
                }

                $string = "";

                for ($i = 0; $i < count($utf16Data); $i++) {
                    $string .= chr($utf16Data[$i] & 0xff) . chr($utf16Data[$i] >> 8);
                }

                return $reviverFunction(null, $string);
            case 4:
                $retArray = [];
                if ($length < 0) {
                    $index = 0;
                    while (!$this->readBreak()) {
                        $retArray[] = $reviverFunction(
                            $index++,
                            $this->decodeItem()
                        );
                    }
                } else {
                    for ($i = 0; $i < $length; ++$i) {
                        $retArray[$i] = $reviverFunction(
                            $i,
                            $this->decodeItem()
                        );
                    }
                }

                return $reviverFunction(null, $retArray);
            case 5:
                $retObject = [];

                for ($i = 0; $i < $length || ($length < 0 && !$this->readBreak()); ++$i) {
                    $key = $this->decodeItem();

                    // check if the key exists inside the array
                    if (array_key_exists($key, $retObject)) {
                        throw new CborReduxException("Duplicate key");
                    }

                    $retObject[$key] = $reviverFunction(
                        $key,
                        $this->decodeItem()
                    );
                }

                return $reviverFunction(null, $retObject);
            case 6:
                $value = $this->decodeItem();
                $tag = $length;

                if (is_array($value)) {
                    $value = new Uint8Array($value);
                    $_offset = $this->offset;

                    $buffer = $value->buffer->slice(
                        $_offset,
                        $_offset + $value->byteLength()
                    );

                    // since php doesn't have native binary data type we can safely decode this into an array.
                    // We can let people decide what to do with the data themselves.
                    switch ($tag) {
                        case Tag::TagUint8:
                        case Tag::TagUint16:
                        case Tag::TagUint32:
                        case Tag::TagInt8:
                        case Tag::TagInt16:
                        case Tag::TagInt32:
                        case Tag::TagFloat32:
                        case Tag::TagFloat64:
                            return $reviverFunction(null, new TaggedValue($tag, $buffer->toArray()));
                    }
                }

                return $reviverFunction($tag, new TaggedValue($tag, $value));
            case 7:
                return match ($length) {
                    20 => $reviverFunction(null, false),
                    21 => $reviverFunction(null, true),
                    22, 23 => $reviverFunction(null, null),
                    default => $reviverFunction(null, new SimpleValue($length)),
                };
        }

        throw new CborReduxException("Invalid major type");
    }

    /**
     * @throws Exception
     */
    public static function parse(string $data, ?callable $reviver = null, array $options = []): mixed
    {
        $decoder = new CborDecoder();
        $binary = unpack("C*", hex2bin($data));

        if ($binary === false) {
            throw new CborReduxException("Failed to unpack binary data");
        }

        return $decoder->decode($binary, $reviver, $options);
    }
}