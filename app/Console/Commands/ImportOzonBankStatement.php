<?php

namespace App\Console\Commands;

use App\Services\Bank\OzonBankStatementImportService;
use Illuminate\Console\Command;

class ImportOzonBankStatement extends Command
{
    protected $signature = 'ozon-bank:import-statement
        {file : Path to 1CClientBankExchange statement file}
        {--bank-id= : Bank BIC. By default it is resolved from legal.bank_account}
        {--rebuild-money-layer : Rebuild money interpretation layer after import}';

    protected $description = 'Import Ozon bank statement from a 1CClientBankExchange file.';

    public function handle(OzonBankStatementImportService $service): int
    {
        $summary = $service->importFile(
            (string) $this->argument('file'),
            $this->bankIdOption(),
            (bool) $this->option('rebuild-money-layer'),
        );

        $this->info(sprintf(
            'Ozon bank statement imported: bank %s, account %s, %d row(s), %d operation(s).',
            $summary['bank_id'],
            $summary['account_number'],
            $summary['rows'],
            $summary['operations'],
        ));

        return self::SUCCESS;
    }

    private function bankIdOption(): ?string
    {
        $bankId = trim((string) $this->option('bank-id'));

        return $bankId === '' ? null : $bankId;
    }
}
