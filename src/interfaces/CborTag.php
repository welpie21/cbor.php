<?php

namespace Beau\CborPHP\interfaces;

/**
 * A custom CBOR tag: how one PHP type is written as a tagged value, and read back.
 *
 * Give handlers to `new Cbor(tags: [...])`. The encoder uses a handler for each
 * object of class(). The decoder uses it for each tagged value with the number tag().
 */
interface CborTag
{
    /**
     * The tag number. It must not be negative (RFC 8949, section 3.4).
     */
    public function tag(): int;

    /**
     * The class or interface whose objects this handler encodes, or null if the
     * handler only decodes. The encoder tries an exact class match first, then
     * `instanceof`, in the order the handlers were given.
     *
     * @return class-string|null
     */
    public function class(): ?string;

    /**
     * Turn an object of class() into the tag content. The encoder encodes the
     * content like any other value, so it can hold strings, arrays and other
     * tagged objects. Do not return the object itself.
     */
    public function encode(object $value): mixed;

    /**
     * Turn the tag content into a PHP value. Tagged values inside the content
     * are already decoded.
     */
    public function decode(mixed $content): mixed;
}
