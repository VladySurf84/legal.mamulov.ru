<?php

namespace App\Services\Bank;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TinkoffBankSyncService
{
    private const BANK_ID_TINKOFF = '044525974';
    private const RECONCILIATION_TYPE_BANK_TRANSACTION = 1;

    public function __construct(
        private readonly TinkoffBusinessClient $client = new TinkoffBusinessClient(),
    ) {}

    public function sync(int $days): array
    {
        $tokens = $this->tokens();
        $from = now()->subDays($days)->toDateString();
        $till = now()->toDateString();
        $summary = [
            'sync_run_id' => null,
            'legals' => count($tokens),
            'accounts' => 0,
            'operations' => 0,
            'from' => $from,
            'till' => $till,
        ];
        $syncRunId = $this->startRun($from, $till);
        $summary['sync_run_id'] = $syncRunId;

        try {
            foreach ($tokens as $legalId => $token) {
                $accounts = $this->client->accounts($token, $syncRunId);
                $summary['accounts'] += count($accounts);

                DB::transaction(function () use ($accounts, $legalId): void {
                    foreach ($accounts as $account) {
                        $this->upsertAccount($account, (int) $legalId);
                    }
                });

                foreach ($accounts as $account) {
                    $accountNumber = (string) data_get($account, 'accountNumber');

                    if ($accountNumber === '') {
                        continue;
                    }

                    $statement = $this->client->statement($token, $syncRunId, $accountNumber, $from, $till);
                    $operations = $this->operationsFromStatement($statement);

                    DB::transaction(function () use ($operations, $accountNumber): void {
                        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['legal.bank_transaction_1c']);

                        foreach ($operations as $operation) {
                            $this->upsertOperation($operation, $accountNumber);
                        }
                    });

                    $summary['operations'] += count($operations);
                }
            }

            $this->finishRun($syncRunId, 'success', $summary);

            return $summary;
        } catch (Throwable $exception) {
            $this->finishRun($syncRunId, 'failed', $summary, $exception);

            throw $exception;
        }
    }

    private function tokens(): array
    {
        $tokens = config('bank.tinkoff.tokens');

        if (! is_array($tokens) || $tokens === []) {
            throw new RuntimeException('TINKOFF_BUSINESS_TOKENS is empty. Expected JSON like {"1":"token"}');
        }

        return array_filter($tokens, fn (mixed $token): bool => is_string($token) && $token !== '');
    }

    private function startRun(string $from, string $till): int
    {
        $now = now();
        $row = DB::selectOne('
            INSERT INTO legal.api_sync_runs (
                provider,
                type,
                status,
                period_from,
                period_till,
                started_at,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING api_sync_run_id
        ', [
            'tinkoff',
            'bank_sync',
            'started',
            $from,
            $till,
            $now,
            $now,
            $now,
        ]);

        return (int) $row->api_sync_run_id;
    }

    private function finishRun(int $syncRunId, string $status, array $summary, ?Throwable $exception = null): void
    {
        DB::table('legal.api_sync_runs')
            ->where('api_sync_run_id', $syncRunId)
            ->update([
                'status' => $status,
                'accounts_count' => $summary['accounts'],
                'operations_count' => $summary['operations'],
                'requests_count' => DB::table('legal.api_sync_requests')
                    ->where('api_sync_run_id', $syncRunId)
                    ->count(),
                'error' => $exception?->getMessage(),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function upsertAccount(array $account, int $legalId): void
    {
        $bankId = (string) data_get($account, 'bankBik', self::BANK_ID_TINKOFF);
        $accountNumber = (string) data_get($account, 'accountNumber');

        if ($accountNumber === '') {
            return;
        }

        DB::table('legal.bank')->upsert([[
            'bank_id' => $bankId,
            'bank_name' => $bankId === self::BANK_ID_TINKOFF ? 'T-Bank' : 'Bank '.$bankId,
            'api_provider_id' => $bankId === self::BANK_ID_TINKOFF ? 6 : null,
        ]], ['bank_id'], ['bank_name', 'api_provider_id']);

        DB::table('legal.bank_account')->upsert([[
            'account_number' => $accountNumber,
            'bank_id' => $bankId,
            'legal_id' => $legalId,
            'name' => (string) data_get($account, 'name', $accountNumber),
            'currency' => (string) data_get($account, 'currency', 'RUB'),
            'account_type' => (string) data_get($account, 'accountType', 'unknown'),
            'activation_date' => data_get($account, 'activationDate'),
            'balance_otb' => data_get($account, 'balance.otb'),
            'balance_authorized' => data_get($account, 'balance.authorized'),
            'balance_pending_payments' => data_get($account, 'balance.pendingPayments'),
            'balance_pending_requisitions' => data_get($account, 'balance.pendingRequisitions'),
        ]], ['account_number', 'bank_id'], [
            'legal_id',
            'name',
            'currency',
            'account_type',
            'activation_date',
            'balance_otb',
            'balance_authorized',
            'balance_pending_payments',
            'balance_pending_requisitions',
        ]);
    }

    private function operationsFromStatement(array $statement): array
    {
        $operations = data_get($statement, 'operation', []);

        return is_array($operations) ? $operations : [];
    }

    private function upsertOperation(array $operation, string $accountNumber): void
    {
        $operationId = (string) data_get($operation, 'operationId');

        if ($operationId === '') {
            return;
        }

        $bankId = self::BANK_ID_TINKOFF;
        $existing = DB::table('legal.bank_transaction_1c')
            ->where('1c_bank_id', $bankId)
            ->where('1c_account_number', $accountNumber)
            ->where('operation_id', $operationId)
            ->first();

        $account = DB::table('legal.bank_account')
            ->where('bank_id', $bankId)
            ->where('account_number', $accountNumber)
            ->first();

        if ($account === null) {
            throw new RuntimeException("Bank account {$accountNumber} was not found for Tinkoff");
        }

        $signedAmount = $this->signedAmount($operation, $accountNumber);

        if ($existing === null) {
            $bankTransactionId = $this->insertParents($operation, (int) $account->legal_id, $bankId, $accountNumber, $signedAmount);
        } else {
            $bankTransactionId = (int) $existing->bank_transaction_id;
            $this->updateParents($bankTransactionId, $operation, (int) $account->legal_id, $bankId, $accountNumber, $signedAmount);
        }

        $this->upsertBankTransaction1c($operation, $bankTransactionId, $bankId, $accountNumber);
    }

    private function insertParents(array $operation, int $legalId, string $bankId, string $accountNumber, float $signedAmount): int
    {
        $reconciliationId = $this->nextReconciliationId();

        DB::table('legal.legal_reconciliation')->insert([
            'reconciliation_id' => $reconciliationId,
            'reconciliation_type_id' => self::RECONCILIATION_TYPE_BANK_TRANSACTION,
            'legal_id' => $legalId,
            'date' => $this->date(data_get($operation, 'date')),
            'amount' => $signedAmount,
            'contractor_inn' => $this->contractorInn($operation, $accountNumber),
        ]);

        $row = DB::selectOne('
            INSERT INTO legal.bank_transaction (
                reconciliation_id,
                reconciliation_type_id,
                order_intraday,
                bank_id,
                account_number,
                date0,
                amount0,
                contractor_name,
                contractor_bank_account,
                contractor_inn0,
                payment_purpose
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING bank_transaction_id
        ', [
            $reconciliationId,
            self::RECONCILIATION_TYPE_BANK_TRANSACTION,
            (string) data_get($operation, 'operationId'),
            $bankId,
            $accountNumber,
            $this->date(data_get($operation, 'date')),
            $signedAmount,
            $this->contractorName($operation, $accountNumber),
            $this->contractorBankAccount($operation, $accountNumber),
            $this->contractorInn($operation, $accountNumber),
            data_get($operation, 'paymentPurpose'),
        ]);

        return (int) $row->bank_transaction_id;
    }

    private function updateParents(int $bankTransactionId, array $operation, int $legalId, string $bankId, string $accountNumber, float $signedAmount): void
    {
        $bankTransaction = DB::table('legal.bank_transaction')
            ->where('bank_transaction_id', $bankTransactionId)
            ->first();

        if ($bankTransaction === null) {
            throw new RuntimeException("Bank transaction {$bankTransactionId} was not found");
        }

        DB::table('legal.legal_reconciliation')
            ->where('reconciliation_id', $bankTransaction->reconciliation_id)
            ->update([
                'reconciliation_type_id' => self::RECONCILIATION_TYPE_BANK_TRANSACTION,
                'legal_id' => $legalId,
                'date' => $this->date(data_get($operation, 'date')),
                'amount' => $signedAmount,
                'contractor_inn' => $this->contractorInn($operation, $accountNumber),
            ]);

        DB::table('legal.bank_transaction')
            ->where('bank_transaction_id', $bankTransactionId)
            ->update([
                'reconciliation_type_id' => self::RECONCILIATION_TYPE_BANK_TRANSACTION,
                'order_intraday' => (string) data_get($operation, 'operationId'),
                'bank_id' => $bankId,
                'account_number' => $accountNumber,
                'date0' => $this->date(data_get($operation, 'date')),
                'amount0' => $signedAmount,
                'contractor_name' => $this->contractorName($operation, $accountNumber),
                'contractor_bank_account' => $this->contractorBankAccount($operation, $accountNumber),
                'contractor_inn0' => $this->contractorInn($operation, $accountNumber),
                'payment_purpose' => data_get($operation, 'paymentPurpose'),
            ]);
    }

    private function upsertBankTransaction1c(array $operation, int $bankTransactionId, string $bankId, string $accountNumber): void
    {
        DB::table('legal.bank_transaction_1c')->upsert([[
            'transaction_hash' => md5($bankId.'-'.$accountNumber.'-'.data_get($operation, 'operationId')),
            'bank_transaction_id' => $bankTransactionId,
            '1c_bank_id' => $bankId,
            '1c_account_number' => $accountNumber,
            'operation_id' => (string) data_get($operation, 'operationId'),
            'id' => data_get($operation, 'id'),
            '1c_date' => $this->date(data_get($operation, 'date')),
            '1c_amount' => data_get($operation, 'amount'),
            'draw_date' => $this->date(data_get($operation, 'drawDate')),
            'payer_name' => data_get($operation, 'payerName'),
            'payer_inn' => data_get($operation, 'payerInn'),
            'payer_account' => data_get($operation, 'payerAccount'),
            'payer_bic' => data_get($operation, 'payerBic'),
            'payer_bank' => data_get($operation, 'payerBank'),
            'charge_cate' => $this->date(data_get($operation, 'chargeDate')),
            'recipient' => data_get($operation, 'recipient'),
            'recipient_inn' => data_get($operation, 'recipientInn'),
            'recipient_account' => data_get($operation, 'recipientAccount'),
            'recipient_bic' => data_get($operation, 'recipientBic'),
            'recipient_bank' => data_get($operation, 'recipientBank'),
            'recipient_corr_account' => data_get($operation, 'recipientCorrAccount'),
            'payment_type' => data_get($operation, 'paymentType'),
            'operation_type' => data_get($operation, 'operationType'),
            'uin' => data_get($operation, 'uin'),
            '1c_payment_purpose' => data_get($operation, 'paymentPurpose'),
            'creator_status' => data_get($operation, 'creatorStatus'),
            'payer_kpp' => data_get($operation, 'payerKpp'),
            'recipient_kpp' => data_get($operation, 'recipientKpp'),
            'kbk' => data_get($operation, 'kbk'),
            'oktmo' => data_get($operation, 'oktmo'),
            'tax_evidence' => data_get($operation, 'taxEvidence'),
            'tax_period' => data_get($operation, 'taxPeriod'),
            'tax_doc_number' => data_get($operation, 'taxDocNumber'),
            'tax_doc_date' => data_get($operation, 'taxDocDate'),
            'tax_type' => data_get($operation, 'taxType'),
            'execution_order' => data_get($operation, 'executionOrder'),
        ]], ['transaction_hash'], [
            'bank_transaction_id',
            '1c_bank_id',
            '1c_account_number',
            'operation_id',
            'id',
            '1c_date',
            '1c_amount',
            'draw_date',
            'payer_name',
            'payer_inn',
            'payer_account',
            'payer_bic',
            'payer_bank',
            'charge_cate',
            'recipient',
            'recipient_inn',
            'recipient_account',
            'recipient_bic',
            'recipient_bank',
            'recipient_corr_account',
            'payment_type',
            'operation_type',
            'uin',
            '1c_payment_purpose',
            'creator_status',
            'payer_kpp',
            'recipient_kpp',
            'kbk',
            'oktmo',
            'tax_evidence',
            'tax_period',
            'tax_doc_number',
            'tax_doc_date',
            'tax_type',
            'execution_order',
        ]);
    }

    private function nextReconciliationId(): int
    {
        return (int) DB::table('legal.legal_reconciliation')->max('reconciliation_id') + 1;
    }

    private function signedAmount(array $operation, string $accountNumber): float
    {
        $amount = (float) data_get($operation, 'amount', 0);

        return $accountNumber === data_get($operation, 'recipientAccount') ? -$amount : $amount;
    }

    private function contractorName(array $operation, string $accountNumber): ?string
    {
        return $accountNumber === data_get($operation, 'recipientAccount')
            ? data_get($operation, 'payerName')
            : data_get($operation, 'recipient');
    }

    private function contractorInn(array $operation, string $accountNumber): ?string
    {
        return $accountNumber === data_get($operation, 'recipientAccount')
            ? data_get($operation, 'payerInn')
            : data_get($operation, 'recipientInn');
    }

    private function contractorBankAccount(array $operation, string $accountNumber): string
    {
        return (string) ($accountNumber === data_get($operation, 'recipientAccount')
            ? data_get($operation, 'payerAccount', '')
            : data_get($operation, 'recipientAccount', ''));
    }

    private function date(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return Carbon::parse((string) $date)->toDateString();
    }
}
