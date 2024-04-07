<?php

namespace Beau\CborPHP\exceptions;

use Exception;

class CborException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct("Cbor Exception: " . $message);
    }
}