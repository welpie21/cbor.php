<?php

use Beau\CborReduxPhp\CborEncoder;
use Beau\CborReduxPhp\classes\TaggedValue;

require_once "vendor/autoload.php";

//$ts2 = microtime(true);
//$y = json_encode([344, 245, 2.0, -100]);
//$te2 = microtime(true);
//var_dump($te2 - $ts2);

class MyTaggedValue extends \Beau\CborReduxPhp\abstracts\AbstractTaggedValue
{
    public function __construct(string $data)
    {
        parent::__construct(0, $data . "Hello World!");
    }
}

$tagged = new TaggedValue(0, "hello world");

$ts1 = microtime(true);
$cbor = new CborEncoder(function ($tag, $value) {

    var_dump($tag);

    if (get_parent_class($value) === "Beau\CborReduxPhp\abstracts\AbstractTaggedValue") {
        return $value->value . " and Hello beau";
    }

    return $value;
});

$y = $cbor->encode([
    344,
    245,
    2.0,
    -2.0,
    -100,
    true,
    false,
    null,
    [
        "a" => 1,
        "b" => 2,
        "c" => 3,
        "d" => 4
    ],
    // string repeated 256 times
//    str_repeat("a", 500),
    $tagged
]);

$te1 = microtime(true);
var_dump($te1 - $ts1);
var_dump(bin2hex($y));