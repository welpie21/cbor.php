<?php

namespace Beau\CborPHP\enums;

enum Tag: int
{
    case Tag = 6;
    case TagUint8 = 64;
    case TagUint16 = 69;
    case TagUint32 = 70;
    case TagInt8 = 72;
    case TagInt16 = 77;
    case TagInt32 = 78;
    case TagFloat32 = 85;
    case TagFloat64 = 86;
}