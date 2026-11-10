<?php

namespace Beau\CborPHP\exceptions;

use Exception;
use Throwable;

class CborException extends Exception
{
    /**
     * @param ?Throwable $previous the error that caused this one, for example an
     *                             exception from a tag handler while decoding
     */
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct("Cbor Exception: " . $message, 0, $previous);
    }
}
