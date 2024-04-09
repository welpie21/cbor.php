<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\classes\TaggedValue;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\TestCase;

class UUIDClass
{
    public string $uuid = "550e8400-e29b-41d4-a716-446655440000";
}

class CborTaggedValueTest extends TestCase
{
    /**
     * @throws CborException
     */
    public function testTaggedValue(): void
    {

        $uuid = new UUIDClass();

        $encode = CborEncoder::encode($uuid, function ($key, $value) {

            if ($value instanceof UUIDClass) {
                return new TaggedValue(7, $value->uuid);
            }

            return $value;
        });

        $decode = CborDecoder::decode($encode, function ($key, $value) {

            if($value instanceof TaggedValue && $value->tag === 7) {
                $uuid = new UUIDClass();
                $uuid->uuid = $value->value;
                return $uuid;
            }

            return $value;
        });

        $this->assertTrue($decode instanceof UUIDClass);
        $this->assertEquals($uuid->uuid, $decode->uuid);
    }
}