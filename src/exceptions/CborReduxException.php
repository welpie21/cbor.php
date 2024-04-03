<?php

namespace Beau\CborPHP\exceptions;

use Exception;

class CborReduxException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct("CborRedux Exception: " . $message);
    }
}