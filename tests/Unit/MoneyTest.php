<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('formatted')]
    public function test_it_formats_cents_as_shillings(int $cents, string $expected): void
    {
        $this->assertSame($expected, Money::format($cents));
    }

    public static function formatted(): array
    {
        return [
            'whole shillings' => [4_500_000, 'KSh 45,000'],
            'with cents' => [4_500_050, 'KSh 45,000.50'],
            'single digit cents' => [105, 'KSh 1.05'],
            'zero' => [0, 'KSh 0'],
            'negative' => [-250_000, '-KSh 2,500'],
        ];
    }

    #[DataProvider('typed')]
    public function test_it_parses_what_a_person_typed(string|int|float|null $typed, ?int $expected): void
    {
        $this->assertSame($expected, Money::parse($typed));
    }

    public static function typed(): array
    {
        return [
            'plain' => ['45000', 4_500_000],
            'with commas' => ['45,000', 4_500_000],
            'with cents' => ['45000.5', 4_500_050],
            'with a currency' => ['KSh 1,250.75', 125_075],
            'an integer' => [300, 30_000],
            'extra decimals are cut, not rounded' => ['10.999', 1_099],
            'empty' => ['', null],
            'null' => [null, null],
            'not a number' => ['abc', null],
        ];
    }

    public function test_it_converts_shillings_to_cents(): void
    {
        $this->assertSame(4_500_000, Money::fromShillings(45_000));
    }
}
