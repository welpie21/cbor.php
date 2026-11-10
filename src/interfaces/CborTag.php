<?php

namespace Beau\CborPHP\interfaces;

use Beau\CborPHP\classes\CborContext;

/**
 * A custom CBOR tag: how one PHP type is written as a tagged value, and read back.
 *
 * Give handlers to `new Cbor(tags: [...])`. The encoder uses a handler for each
 * object of class(). The decoder uses it for each tagged value with the number tag().
 *
 * The tag content is in one form, whatever the options of the Cbor instance: text
 * strings are UTF-8, and byte strings are plain strings. Convert your own strings
 * with the context. See {@see CborContext}.
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
     * content like any other value, but it writes strings as they are: return text
     * in UTF-8, and use $context->toUtf8() for your own strings. Do not return the
     * object itself.
     */
    public function encode(object $value, CborContext $context): mixed;

    /**
     * Turn the tag content into a PHP value. Text strings in the content are UTF-8,
     * byte strings are plain strings, and tagged values in it are already decoded.
     *
     * The content can be any CBOR value, so check its shape before you use it: for
     * untrusted input, it is whatever the sender wrote. Do not spread it into a
     * constructor, because a map would then set named arguments. An exception thrown
     * here reaches the caller as a CborException, with your exception as the previous one.
     */
    public function decode(mixed $content, CborContext $context): mixed;
}
