<?php

use Beau\CborPHP\CborDecoder;
use Beau\CborPHP\CborEncoder;
use Beau\CborPHP\exceptions\CborException;
use PHPUnit\Framework\TestCase;

/**
 * Tests deeply nested and complex data structures combining maps and arrays.
 */
class CborDeepNestingTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Pure deep nesting
    // -------------------------------------------------------------------------

    /**
     * @throws CborException
     */
    public function testDeeplyNestedMaps(): void
    {
        // Build a chain: {"a": {"a": {"a": ... 20 levels deep ... }}}
        $data = ["value" => 42];
        for ($i = 0; $i < 20; $i++) {
            $data = ["nested" => $data];
        }

        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testDeeplyNestedArrays(): void
    {
        // Build a chain: [[[... 20 levels deep ... [42]]]]
        $data = [42];
        for ($i = 0; $i < 20; $i++) {
            $data = [$data];
        }

        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testAlternatingMapAndArrayNesting(): void
    {
        // {"items": [{"items": [{"items": [...]}]}]} — 10 alternating levels
        $data = "leaf";
        for ($i = 0; $i < 10; $i++) {
            if ($i % 2 === 0) {
                $data = [$data, $i]; // array level
            } else {
                $data = ["value" => $data, "level" => $i]; // map level
            }
        }

        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    // -------------------------------------------------------------------------
    // Wide structures at each level
    // -------------------------------------------------------------------------

    /**
     * @throws CborException
     */
    public function testWideMap(): void
    {
        // A single map with 100 string keys, each with an integer value
        $data = [];
        for ($i = 0; $i < 100; $i++) {
            $data["field_$i"] = $i;
        }

        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testWideArray(): void
    {
        // A single array with 500 elements of mixed types
        $data = [];
        for ($i = 0; $i < 500; $i++) {
            $data[] = match ($i % 5) {
                0 => $i,
                1 => (float)$i * 1.5,
                2 => "string_$i",
                3 => ($i % 2 === 0),
                4 => null,
            };
        }

        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testWideMapWithNestedArrayValues(): void
    {
        // 50 keys, each holding an array of 50 integers
        $data = [];
        for ($i = 0; $i < 50; $i++) {
            $data["row_$i"] = range($i * 50, $i * 50 + 49);
        }

        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    // -------------------------------------------------------------------------
    // Real-world-shaped structures
    // -------------------------------------------------------------------------

    /**
     * @throws CborException
     */
    public function testUserProfileDocument(): void
    {
        $data = [
            "id"       => 1001,
            "username" => "alice",
            "active"   => true,
            "score"    => 98.6,
            "tags"     => ["admin", "editor", "moderator"],
            "address"  => [
                "street"  => "123 Main St",
                "city"    => "Springfield",
                "country" => "US",
                "coords"  => ["lat" => 44.9778, "lng" => -93.2650],
            ],
            "history"  => [
                ["action" => "login",  "ts" => 1700000000],
                ["action" => "edit",   "ts" => 1700000100],
                ["action" => "logout", "ts" => 1700000200],
            ],
            "metadata" => null,
        ];

        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testArrayOfUserDocuments(): void
    {
        $makeUser = fn(int $id) => [
            "id"     => $id,
            "name"   => "User $id",
            "active" => ($id % 2 === 0),
            "score"  => round($id * 0.7, 2),
            "roles"  => ["user", $id < 10 ? "admin" : "viewer"],
        ];

        $data = array_map($makeUser, range(1, 50));

        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testTreeStructure(): void
    {
        // A balanced 3-ary tree, depth 4 (1 + 3 + 9 + 27 + 81 = 121 nodes)
        $makeNode = function (int $depth, int $id) use (&$makeNode): array {
            $node = ["id" => $id, "depth" => $depth];
            if ($depth < 4) {
                $node["children"] = [
                    $makeNode($depth + 1, $id * 10 + 1),
                    $makeNode($depth + 1, $id * 10 + 2),
                    $makeNode($depth + 1, $id * 10 + 3),
                ];
            }
            return $node;
        };

        $data = $makeNode(0, 1);

        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testMatrix(): void
    {
        // 30×30 matrix of floats
        $data = [];
        for ($row = 0; $row < 30; $row++) {
            $rowData = [];
            for ($col = 0; $col < 30; $col++) {
                $rowData[] = round(sin($row) * cos($col), 8);
            }
            $data[] = $rowData;
        }

        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testEventLog(): void
    {
        // Array of log entries, each with nested context map
        $data = [];
        for ($i = 0; $i < 100; $i++) {
            $data[] = [
                "timestamp" => 1700000000 + $i,
                "level"     => ["debug", "info", "warn", "error"][$i % 4],
                "message"   => "Event number $i occurred",
                "context"   => [
                    "request_id" => "req-$i",
                    "user_id"    => $i * 7,
                    "duration"   => round($i * 0.123, 3),
                    "tags"       => ["tag_" . ($i % 3), "tag_" . ($i % 5)],
                    "extra"      => $i % 2 === 0 ? ["key" => "value_$i"] : null,
                ],
            ];
        }

        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    // -------------------------------------------------------------------------
    // Mixed-type deep structures
    // -------------------------------------------------------------------------

    /**
     * @throws CborException
     */
    public function testDeepMixedTypesAtEveryLevel(): void
    {
        $data = [
            "int"    => PHP_INT_MAX,
            "negint" => PHP_INT_MIN + 1, // avoid -PHP_INT_MIN overflow
            "float"  => M_PI,
            "str"    => str_repeat("abc", 50),
            "bool_t" => true,
            "bool_f" => false,
            "null"   => null,
            "array"  => [
                1, -1, 0, 1.5, -1.5, true, false, null,
                [2, -2, 2.5, true, null, ["deep" => ["deeper" => ["deepest" => 42]]]],
            ],
            "map" => [
                "a" => ["b" => ["c" => ["d" => ["e" => "leaf"]]]],
                "x" => [100, 200, ["y" => [300, 400]]],
                "z" => null,
            ],
        ];

        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testMapContainingArraysOfMapsOfArrays(): void
    {
        // map → array of maps → each map has arrays as values
        $data = [];
        for ($g = 0; $g < 5; $g++) {
            $group = [];
            for ($i = 0; $i < 10; $i++) {
                $group[] = [
                    "id"     => $g * 10 + $i,
                    "values" => range($i, $i + 4),
                    "flags"  => [true, false, $i % 2 === 0],
                    "meta"   => ["group" => $g, "index" => $i],
                ];
            }
            $data["group_$g"] = $group;
        }

        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    /**
     * @throws CborException
     */
    public function testPagedResultSet(): void
    {
        // Simulate a paginated API response shape
        $data = [
            "page"       => 1,
            "per_page"   => 25,
            "total"      => 250,
            "has_more"   => true,
            "items"      => array_map(fn($i) => [
                "id"          => $i,
                "title"       => "Item $i",
                "description" => str_repeat("x", 40),
                "price"       => round($i * 9.99, 2),
                "in_stock"    => ($i % 3 !== 0),
                "categories"  => ["cat_" . ($i % 4), "cat_" . ($i % 7)],
                "attributes"  => [
                    "weight" => round($i * 0.25, 2),
                    "color"  => ["red", "green", "blue"][$i % 3],
                    "sizes"  => ["S", "M", "L", "XL"],
                ],
            ], range(1, 25)),
            "facets" => [
                "categories" => [
                    ["name" => "cat_0", "count" => 7],
                    ["name" => "cat_1", "count" => 6],
                ],
                "price_range" => ["min" => 9.99, "max" => 249.75],
            ],
        ];

        $encoded = CborEncoder::encode($data);
        $decoded = CborDecoder::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    // -------------------------------------------------------------------------
    // Encode size sanity checks
    // -------------------------------------------------------------------------

    /**
     * @throws CborException
     */
    public function testEncodedOutputIsNonEmpty(): void
    {
        $structures = [
            [],
            [[]],
            ["a" => []],
            [[[["deep" => [1, 2, 3]]]]],
        ];

        foreach ($structures as $data) {
            $encoded = CborEncoder::encode($data);
            $this->assertNotEmpty($encoded);
            $decoded = CborDecoder::decode($encoded);
            $this->assertEquals($data, $decoded);
        }
    }
}
