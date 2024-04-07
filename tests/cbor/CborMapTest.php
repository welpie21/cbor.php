<?php

class CborMapTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @throws \Beau\CborPHP\exceptions\CborException
     */
    public function testMapOfNumbers(): void
    {
        $data = [
            "a" => 1,
            "b" => 10,
            "c" => 100
        ];

        $encode = \Beau\CborPHP\CborEncoder::encode($data);
        $decode = \Beau\CborPHP\CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    public function testMapOfStrings(): void
    {
        $data = [
            "a" => "apple",
            "b" => "banana",
            "c" => "cherry"
        ];

        $encode = \Beau\CborPHP\CborEncoder::encode($data);
        $decode = \Beau\CborPHP\CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    public function testMapOfArrays(): void
    {
        $data = [
            "a" => [1, 2, 3],
            "b" => [4, 5, 6],
            "c" => [7, 8, 9]
        ];

        $encode = \Beau\CborPHP\CborEncoder::encode($data);
        $decode = \Beau\CborPHP\CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    public function testMapOfMaps(): void
    {
        $data = [
            "a" => ["a" => 1, "b" => 2, "c" => 3],
            "b" => ["a" => 4, "b" => 5, "c" => 6],
            "c" => ["a" => 7, "b" => 8, "c" => 9]
        ];

        $encode = \Beau\CborPHP\CborEncoder::encode($data);
        $decode = \Beau\CborPHP\CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    public function testMapOfMixed(): void
    {
        $data = [
            "a" => 1,
            "b" => "banana",
            "c" => [7, 8, 9]
        ];

        $encode = \Beau\CborPHP\CborEncoder::encode($data);
        $decode = \Beau\CborPHP\CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    public function testMapOfEmpty(): void
    {
        $data = [];

        $encode = \Beau\CborPHP\CborEncoder::encode($data);
        $decode = \Beau\CborPHP\CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    public function testMapOfNull(): void
    {
        $data = [
            "a" => null,
            "b" => null,
            "c" => null
        ];

        $encode = \Beau\CborPHP\CborEncoder::encode($data);
        $decode = \Beau\CborPHP\CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    public function testMapOfBooleans(): void
    {
        $data = [
            "a" => true,
            "b" => false,
            "c" => true
        ];

        $encode = \Beau\CborPHP\CborEncoder::encode($data);
        $decode = \Beau\CborPHP\CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }

    public function testNestedMaps(): void
    {
        $data = [
            "a" => [
                "a" => [
                    "a" => [
                        "a" => 1,
                        "b" => 2,
                        "c" => 3
                    ],
                    "b" => [
                        "a" => 4,
                        "b" => 5,
                        "c" => 6
                    ],
                    "c" => [
                        "a" => 7,
                        "b" => 8,
                        "c" => 9
                    ]
                ],
                "b" => [
                    "a" => [
                        "a" => 1,
                        "b" => 2,
                        "c" => 3
                    ],
                    "b" => [
                        "a" => 4,
                        "b" => 5,
                        "c" => 6
                    ],
                    "c" => [
                        "a" => 7,
                        "b" => 8,
                        "c" => 9
                    ]
                ],
                "c" => [
                    "a" => [
                        "a" => 1,
                        "b" => 2,
                        "c" => 3
                    ],
                    "b" => [
                        "a" => 4,
                        "b" => 5,
                        "c" => 6
                    ],
                    "c" => [
                        "a" => 7,
                        "b" => 8,
                        "c" => 9
                    ]
                ]
            ],
            "b" => [
                "a" => [
                    "a" => [
                        "a" => 1,
                        "b" => 2,
                        "c" => 3
                    ],
                    "b" => [
                        "a" => 4,
                        "b" => 5,
                        "c" => 6
                    ],
                    "c" => [
                        "a" => 7,
                        "b" => 8,
                        "c" => 9
                    ]
                ],
                "b" => [
                    "a" => [
                        "a" => 1,
                        "b" => 2,
                        "c" => 3
                    ],
                    "b" => [
                        "a" => 4,
                        "b" => 5,
                        "c" => 6
                    ]
                ]
            ]
        ];

        $encode = \Beau\CborPHP\CborEncoder::encode($data);
        $decode = \Beau\CborPHP\CborDecoder::decode($encode);

        $this->assertEquals($data, $decode);
    }
}