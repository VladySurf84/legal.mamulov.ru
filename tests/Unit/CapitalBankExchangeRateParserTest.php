<?php

namespace Tests\Unit;

use App\Services\ExchangeRates\CapitalBankExchangeRateParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CapitalBankExchangeRateParserTest extends TestCase
{
    private function fixture(): string
    {
        return file_get_contents(__DIR__ . '/../Fixtures/exchange-rates/capitalbank.html');
    }

    public function test_parses_all_three_tables_without_confusing_separator_cells_with_rates(): void
    {
        $quotes = (new CapitalBankExchangeRateParser)->parse($this->fixture());
        self::assertCount(14, $quotes);
        $usd = array_column(array_filter($quotes, fn ($row) => $row['currency'] === 'USD'), null, 'type');
        self::assertSame('87.30', $usd['cash']['buy']);
        self::assertSame('87.80', $usd['cash']['sell']);
        self::assertSame('87.00', $usd['non_cash']['buy']);
        self::assertSame('87.4355', $usd['official']['official']);
        self::assertNull($usd['official']['buy']);
        self::assertStringContainsString('87.30', $usd['cash']['raw']['html']);
        self::assertCount(6, array_filter($quotes, fn ($row) => $row['type'] === 'non_cash'));
    }

    public function test_missing_section_fails_instead_of_closing_existing_intervals(): void
    {
        $this->expectException(RuntimeException::class);
        (new CapitalBankExchangeRateParser)->parse(str_replace('id="div2"', 'id="missing"', $this->fixture()));
    }

    public function test_invalid_rate_is_not_silently_ignored(): void
    {
        $this->expectException(RuntimeException::class);
        (new CapitalBankExchangeRateParser)->parse(str_replace('87.30', 'unavailable', $this->fixture()));
    }
}
