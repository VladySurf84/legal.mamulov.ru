<?php

namespace Tests\Unit;

use App\Services\ExchangeRates\BakaiExchangeRateParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BakaiExchangeRateParserTest extends TestCase
{
    public function test_preserves_separate_rate_types_and_excludes_metals(): void
    {
        $quotes = (new BakaiExchangeRateParser)->parse(file_get_contents(__DIR__ . '/../Fixtures/exchange-rates/bakai.json'));
        $usd = array_column(array_filter($quotes, fn ($row) => $row['currency'] === 'USD'), null, 'type');
        self::assertEqualsCanonicalizing(['cash', 'non_cash', 'transfer', 'official'], array_keys($usd));
        self::assertSame(87.3, $usd['cash']['buy']);
        self::assertSame(87.0, $usd['non_cash']['buy']);
        self::assertSame(87.4355, $usd['official']['official']);
        self::assertNull($usd['official']['buy']);
        self::assertArrayHasKey('latest_update_time', $usd['cash']['raw']);
        self::assertNotContains('XAU', array_column($quotes, 'currency'));
        self::assertNotContains('XAG', array_column($quotes, 'currency'));
        self::assertNotContains('XPT', array_column($quotes, 'currency'));
    }

    public function test_empty_result_fails_instead_of_closing_intervals(): void
    {
        $this->expectException(RuntimeException::class);
        (new BakaiExchangeRateParser)->parse('{"result":[]}');
    }

    public function test_invalid_rate_fails_instead_of_silently_dropping_quote(): void
    {
        $this->expectException(RuntimeException::class);
        (new BakaiExchangeRateParser)->parse('{"result":[{"USD":{"cash":{"buy":"invalid","sell":87.8}}}]}');
    }
}
