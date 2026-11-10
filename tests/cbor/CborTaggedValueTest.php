<?php

use Beau\CborPHP\Cbor;
use Beau\CborPHP\exceptions\CborException;
use Beau\CborPHP\interfaces\CborTag;
use PHPUnit\Framework\TestCase;

class UUIDClass
{
    public string $uuid = "550e8400-e29b-41d4-a716-446655440000";
}

class UUIDTag implements CborTag
{
    public function tag(): int
    {
        return 7;
    }

    public function class(): string
    {
        return UUIDClass::class;
    }

    public function encode(object $value): string
    {
        return $value->uuid;
    }

    public function decode(mixed $content): UUIDClass
    {
        $uuid = new UUIDClass();
        $uuid->uuid = $content;
        return $uuid;
    }
}

class CborTaggedValueTest extends TestCase
{
    /**
     * @throws CborException
     */
    public function testTaggedValue(): void
    {

        $uuid = new UUIDClass();
        $cbor = new Cbor(tags: [new UUIDTag()]);

        $encode = $cbor->encode($uuid);

        $decode = $cbor->decode($encode);

        $this->assertTrue($decode instanceof UUIDClass);
        $this->assertEquals($uuid->uuid, $decode->uuid);
    }
}