<?php

namespace App\Services\Bank;

use Illuminate\Support\Facades\DB;

class BankAccountBalanceService
{
    public function record(int $accountId, string $source, int $sourceId, string $amount, string $balanceOn, string $observedAt, array $rawData): void
    {
        DB::table('legal.bank_account_balance_snapshots')->upsert([[
            'bank_account_id' => $accountId,
            'source' => $source,
            'source_id' => $sourceId,
            'amount' => $amount,
            'balance_on' => $balanceOn,
            'observed_at' => $observedAt,
            'raw_data' => json_encode($rawData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]], ['bank_account_id', 'source', 'source_id'], ['amount', 'balance_on', 'observed_at', 'raw_data']);
    }

    public function recordStatement(array $parsed, string $bankId, int $uploadedFileId, string $observedAt): bool
    {
        $balance = $parsed['closing_balance'] ?? null;
        if ($balance === null) {
            return false;
        }

        $accountId = DB::table('legal.bank_account')
            ->where('account_number', $parsed['account_number'])
            ->where('bank_id', $bankId)
            ->value('bank_account_id');

        if ($accountId === null) {
            return false;
        }

        $this->record((int) $accountId, 'bank_statement_file', $uploadedFileId, $balance['amount'], $balance['balance_on'], $observedAt, $balance['raw_data']);

        return true;
    }
}
