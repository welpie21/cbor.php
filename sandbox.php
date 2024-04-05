<?php

use Beau\CborPHP\CborDecoder;

require_once __DIR__ . '/vendor/autoload.php';

$time_start = microtime(true);
$x = CborDecoder::decode("85FBC0590147AE147AE13826186418276474657374");
$time_end = microtime(true);
$execution_time = ($time_end - $time_start);
echo "Execution time: " . $execution_time . "s\n";
var_dump($x);