<?php

namespace Tests\Unit;

use App\Services\Bank\BankStatementImportService;
use PHPUnit\Framework\TestCase;

class BankStatementBalanceTest extends TestCase
{
    private function parse(string $section): array
    {
        $path = tempnam(sys_get_temp_dir(), 'bank-balance-');
        try {
            file_put_contents($path, "1CClientBankExchange\nСекцияРасчСчет\nРасчСчет=123456\n{$section}\nКонецРасчСчет\nСекцияДокумент=Платежное поручение\nНомер=1\nКонецДокумента\nКонецФайла\n");

            return (new BankStatementImportService)->parse1CClientBankExchangeFile($path);
        } finally {
            unlink($path);
        }
    }

    public function test_closing_balance_preserves_source_values_and_statement_date(): void
    {
        $parsed = $this->parse("ДатаКонца=30.10.2026\nНачальныйОстаток=100\nКонечныйОстаток=-1 234,56");
        self::assertSame('-1234.56', $parsed['closing_balance']['amount']);
        self::assertSame('2026-10-30', $parsed['closing_balance']['balance_on']);
        self::assertSame('-1 234,56', $parsed['closing_balance']['raw_data']['КонечныйОстаток']);
        self::assertCount(1, $parsed['rows']);
    }

    public function test_zero_is_a_valid_balance(): void
    {
        self::assertSame('0.00', $this->parse("ДатаКонца=2026-10-30\nКонечныйОстаток=0.00")['closing_balance']['amount']);
    }

    public function test_missing_or_invalid_balance_does_not_become_zero(): void
    {
        foreach (["ДатаКонца=30.10.2026", "КонечныйОстаток=10", "ДатаКонца=31.02.2026\nКонечныйОстаток=10", "ДатаКонца=30.10.2026\nКонечныйОстаток=unknown"] as $section) {
            $parsed = $this->parse($section);
            self::assertNull($parsed['closing_balance']);
            self::assertCount(1, $parsed['rows']);
        }
    }

    public function test_multiple_accounts_are_rejected_instead_of_attaching_the_wrong_balance(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('multiple accounts');
        $this->parse("ДатаКонца=30.10.2026\nКонечныйОстаток=10\nКонецРасчСчет\nСекцияРасчСчет\nРасчСчет=654321");
    }
}
