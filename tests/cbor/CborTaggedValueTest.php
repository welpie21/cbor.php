<?php

use Beau\CborPHP\classes\TaggedValue;
use PHPUnit\Framework\TestCase;

class CborTaggedValueTest extends TestCase
{
    /**
     * @throws \Beau\CborPHP\exceptions\CborException
     */
    public function testTaggedValue(): void
    {
        $data = new TaggedValue(0, "apple");

        $encode = \Beau\CborPHP\CborEncoder::encode($data, function ($key, $value) {

            var_dump($value);

            if($value instanceof TaggedValue) {
                return match ($value->tag) {
                    0 => $value->value . "!",
                };
            }

            return $value;
        });

        $decode = \Beau\CborPHP\CborDecoder::decode($encode, function ($key, $value) {

            if($value instanceof TaggedValue) {
                return match ($value->tag) {
                    0 => $value->value,
                };
            }

            return $value;
        });

        $this->assertEquals($data, $decode);
    }
}