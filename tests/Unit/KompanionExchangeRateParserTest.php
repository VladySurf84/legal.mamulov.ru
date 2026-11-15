<?php

namespace Tests\Unit;

use App\Services\ExchangeRates\KompanionExchangeRateParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class KompanionExchangeRateParserTest extends TestCase
{
    public function test_parses_cash_and_non_cash_api_responses(): void
    {
        $parser = new KompanionExchangeRateParser;
        $cash = $parser->parse(file_get_contents(__DIR__ . '/../Fixtures/exchange-rates/kompanion-cash.json'), 1);
        $nonCash = $parser->parse(file_get_contents(__DIR__ . '/../Fixtures/exchange-rates/kompanion-non-cash.json'), 2);

        self::assertCount(4, $cash);
        self::assertCount(6, $nonCash);
        $cashUsd = array_values(array_filter($cash, fn ($row) => $row['currencySymbol'] === 'USD'))[0];
        self::assertSame(87.4, $cashUsd['buyRate']);
        self::assertSame(87.8, $cashUsd['sellRate']);
        self::assertContains('CNY', array_column($nonCash, 'currencySymbol'));
        self::assertIsArray($cashUsd['updatedAt']);
    }

    public function test_rejects_empty_payload_instead_of_closing_existing_intervals(): void
    {
        $this->expectException(RuntimeException::class);
        (new KompanionExchangeRateParser)->parse('[]', 1);
    }

    public function test_rejects_wrong_rate_type(): void
    {
        $this->expectException(RuntimeException::class);
        (new KompanionExchangeRateParser)->parse(file_get_contents(__DIR__ . '/../Fixtures/exchange-rates/kompanion-cash.json'), 2);
    }

    public function test_rejects_cross_rates_instead_of_treating_them_as_kgs(): void
    {
        $rows = json_decode(file_get_contents(__DIR__ . '/../Fixtures/exchange-rates/kompanion-cash.json'), true);
        $rows[0]['crossCurrencySymbol'] = 'USD';
        $this->expectException(RuntimeException::class);
        (new KompanionExchangeRateParser)->parse(json_encode($rows), 1);
    }
}
