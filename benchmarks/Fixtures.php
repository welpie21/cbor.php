<?php

// Shared by the benchmarks. phpbench runs only the files that end in Bench.php.

use Beau\CborPHP\classes\CborContext;
use Beau\CborPHP\classes\TaggedValue;
use Beau\CborPHP\interfaces\CborTag;

final class BenchMoney
{
    public function __construct(public int $cents, public string $currency) {}
}

final class BenchMoneyTag implements CborTag
{
    public function tag(): int
    {
        return 20;
    }

    public function class(): string
    {
        return BenchMoney::class;
    }

    public function encode(object $value, CborContext $context): array
    {
        return [$value->cents, $value->currency];
    }

    public function decode(mixed $content, CborContext $context): BenchMoney
    {
        return new BenchMoney($content[0], $content[1]);
    }
}

final class BenchFixtures
{
    /**
     * Ten tagged objects.
     *
     * @return list<BenchMoney>
     */
    public static function money(): array
    {
        return array_map(fn(int $i) => new BenchMoney(1000 + $i, 'EUR'), range(1, 10));
    }

    /** The same tag as BenchMoneyTag, written with a replacer. */
    public static function moneyReplacer(): Closure
    {
        return static fn(mixed $value) => $value instanceof BenchMoney
            ? new TaggedValue(20, [$value->cents, $value->currency])
            : $value;
    }

    /** The same tag as BenchMoneyTag, read with a reviver. */
    public static function moneyReviver(): Closure
    {
        return static fn(TaggedValue $value) => $value->tag === 20
            ? new BenchMoney($value->value[0], $value->value[1])
            : $value;
    }
}
