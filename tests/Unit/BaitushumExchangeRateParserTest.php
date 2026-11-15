<?php

namespace Tests\Unit;

use App\Services\ExchangeRates\BaitushumExchangeRateParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BaitushumExchangeRateParserTest extends TestCase
{
    private function fixture(): string
    {
        return file_get_contents(__DIR__ . '/../Fixtures/exchange-rates/baitushum.html');
    }

    public function test_parses_four_rate_types_and_uses_currency_text_instead_of_css_class(): void
    {
        $quotes = (new BaitushumExchangeRateParser)->parse($this->fixture());
        self::assertCount(20, $quotes);
        $usd = array_column(array_filter($quotes, fn ($row) => $row['currency'] === 'USD'), null, 'type');
        self::assertSame('87.300', $usd['cash']['buy']);
        self::assertSame('87.800', $usd['cash']['sell']);
        self::assertSame('87.500', $usd['non_cash']['sell']);
        self::assertSame('87.800', $usd['mobile']['sell']);
        self::assertSame('87.4355', $usd['official']['official']);
        self::assertNull($usd['official']['buy']);
        self::assertSame('15.11.2026', $usd['cash']['raw']['date']);
        $official = array_column(array_filter($quotes, fn ($row) => $row['type'] === 'official'), null, 'currency');
        self::assertSame('13.1072', $official['CNY']['official']);
        self::assertSame('0.1972', $official['KZT']['official']);
    }

    public function test_missing_section_fails(): void
    {
        $this->expectException(RuntimeException::class);
        (new BaitushumExchangeRateParser)->parse(str_replace('id="cashless"', 'id="missing"', $this->fixture()));
    }

    public function test_invalid_rate_fails(): void
    {
        $this->expectException(RuntimeException::class);
        (new BaitushumExchangeRateParser)->parse(str_replace('87.300', 'unavailable', $this->fixture()));
    }
}
