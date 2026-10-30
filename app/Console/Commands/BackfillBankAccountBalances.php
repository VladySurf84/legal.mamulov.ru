<?php

namespace App\Console\Commands;

use App\Services\Bank\BankAccountBalanceService;
use App\Services\Bank\BankStatementImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackfillBankAccountBalances extends Command
{
    protected $signature = 'bank:backfill-balances';

    protected $description = 'Restore balance snapshots from successfully imported bank statement source files without reimporting operations.';

    public function handle(BankStatementImportService $importer, BankAccountBalanceService $balances): int
    {
        $restored = $skipped = $failed = 0;
        $files = DB::table('legal.uploaded_files as f')
            ->join('legal.import_runs as r', 'r.import_run_id', '=', 'f.import_run_id')
            ->where('f.file_role', 'bank_statement_source')
            ->where('r.status', 'success')
            ->select(['f.uploaded_file_id', 'f.stored_path', 'f.uploaded_at', 'r.metadata'])
            ->orderBy('f.uploaded_file_id')->get();

        foreach ($files as $file) {
            try {
                $metadata = json_decode($file->metadata, true, 512, JSON_THROW_ON_ERROR);
                $bankId = $metadata['bank_id'] ?? null;
                if (! $bankId || ! Storage::disk('local')->exists($file->stored_path)) {
                    $skipped++;
                    continue;
                }
                $parsed = $importer->parse1CClientBankExchangeFile(Storage::disk('local')->path($file->stored_path));
                if ($balances->recordStatement($parsed, $bankId, (int) $file->uploaded_file_id, $file->uploaded_at)) {
                    $restored++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable) {
                $failed++;
                $this->warn("Could not restore balance from uploaded file #{$file->uploaded_file_id}.");
            }
        }

        $this->info("Balance snapshots: restored {$restored}, skipped {$skipped}, failed {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
