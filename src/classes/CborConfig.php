<?php

namespace Beau\CborPHP\classes;

use Beau\CborPHP\interfaces\CborTag;
use Closure;

/**
 * The checked options of one Cbor instance, in the form the encoder and decoder use.
 *
 * @internal Use the options of {@see \Beau\CborPHP\Cbor}.
 */
final class CborConfig
{
    /**
     * @param ?string $stringEncoding the mbstring name of the string encoding, or null for
     *                                UTF-8 (no conversion)
     * @param array<int, CborTag> $tagsByNumber
     */
    public function __construct(
        public readonly ?string $stringEncoding,
        public readonly ?TagRegistry $tags,
        public readonly array $tagsByNumber,
        public readonly ?Closure $replacer,
        public readonly ?Closure $reviver,
        public readonly bool $byteStrings,
        public readonly int $maxDepth,
        public readonly bool $strict,
    ) {
    }
}
