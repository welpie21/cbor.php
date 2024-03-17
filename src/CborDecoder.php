<?php

namespace Beau\CborReduxPhp;

use Beau\CborReduxPhp\classes\Sequence;
use Beau\CborReduxPhp\classes\SimpleValue;
use Beau\CborReduxPhp\exceptions\CborReduxException;
use Beau\CborReduxPhp\utils\ArrayBuffer;
use Beau\CborReduxPhp\utils\DataView;
use Beau\CborReduxPhp\utils\Uint8Array;
use Closure;
use Exception;

class CborDecoder
{
    const POW_2_53 = 9007199254740992;

    private DataView $view;
    private array $options;
    private int $offset = 1;
    private array $ta = [];
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
        $intArray = new Uint8Array($this->ta, $this->offset, $length);
        return $this->commitRead($length, $intArray);
    }

    private function readFloat16(): float
    {
        $tempArrayBuffer = new ArrayBuffer(4);
        $tempDataView = new DataView($tempArrayBuffer);

        $value = $this->readUint16();

        $sign = $value & 0x8000;
        $exponent = $value & 0x7c00;
        $fraction = $value & 0x03ff;

        if ($exponent === 0x7c00) {
            $exponent = 0xff << 10;
        } else if ($exponent !== 0) {
            $exponent += (127 - 15) << 10;
        } else if ($fraction !== 0) {
            return ($sign ? -1 : 1) * $fraction * pow(2, 24);
        }

        $tempDataView->setUint32(
            0,
            ($sign << 16) | ($exponent << 13) | ($fraction << 13)
        );

        return $tempDataView->getFloat32(0);
    }

    private function readFloat32(): float
    {
        $offset = $this->offset;
        return $this->commitRead(4, $this->view->getFloat32($offset));
    }

    private function readFloat64(): float
    {
        $offset = $this->offset;
        return $this->commitRead(8, $this->view->getFloat64($offset));
    }

    private function readUint8(): int
    {
        $offset = $this->offset;
        return $this->commitRead(1, $this->view->getUint8($offset));
    }

    private function readUint16(): int
    {
        $offset = $this->offset;
        return $this->commitRead(2, $this->view->getUint16($offset));
    }

    private function readUint32(): int
    {
        $offset = $this->offset;
        return $this->commitRead(4, $this->view->getUint32($offset));
    }

    private function readUint64(): int
    {
        $offset = $this->offset;
        return $this->commitRead(8, $this->view->getUint64($offset));
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
                // if($this->options["dictionary"] === "map") {
                // TODO: implement?
                // }

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

                if ($value instanceof Uint8Array) {
                    $_offset = $value->byteOffset();
                    $buffer = $value->buffer->slice(
                        $_offset,
                        $_offset + $value->byteLength()
                    );

                    // TODO: implement all the tags to support the different types
                }
            case 7:
                return match ($length) {
                    20 => false,
                    21 => true,
                    22 => null,
                    23 => $reviverFunction(null, null),
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
        $binary = unpack("C*", $data);

        if ($binary === false) {
            throw new CborReduxException("Failed to unpack binary data");
        }

        return $decoder->decode($binary, $reviver, $options);
    }
}