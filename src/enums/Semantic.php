<?php

namespace Beau\CborReduxPhp\enums;

enum Semantic: string
{
    case RESERVED = "reserved";
    case UNASSIGNED = "unassigned";
    case FALSE = "false";
    case TRUE = "true";
    case NULL = "null";
    case UNDEFINED = "undefined";
}