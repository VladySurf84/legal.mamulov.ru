<?php

namespace App\Console\Commands;

use App\Services\Bank\BankStatementImportService;
use Illuminate\Console\Command;

class ImportBankStatement extends Command
{
    protected $signature = 'bank-statement:import-1c
        {file : Path to 1CClientBankExchange statement file}
        {--bank-id= : Bank BIC. By default it is resolved from legal.bank_account}
        {--rebuild-money-layer : Rebuild money interpretation layer after import}
        {--auto-create-bank-account : Create missing bank and bank account from statement data}';

    protected $description = 'Import bank statement from a 1CClientBankExchange file.';

    public function handle(BankStatementImportService $service): int
    {
        $file = (string) $this->argument('file');
        $summary = $service->importFile(
            $file,
            $this->bankIdOption(),
            (bool) $this->option('rebuild-money-layer'),
            basename($file),
            null,
            (bool) $this->option('auto-create-bank-account'),
        );

        $this->info(sprintf(
            'Bank statement imported: run #%d, file #%d, bank %s, account %s, %d row(s), %d operation(s).',
            $summary['import_run_id'],
            $summary['uploaded_file_id'],
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
