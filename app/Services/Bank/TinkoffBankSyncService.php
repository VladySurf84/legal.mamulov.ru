<?php

namespace App\Services\Bank;

use App\Models\ApiCredential;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TinkoffBankSyncService
{
    private const BANK_ID_TINKOFF = '044525974';

    private const RECONCILIATION_TYPE_BANK_TRANSACTION = 1;

    private const DOCUMENT_TYPE_BANK_OPERATION = 'bank_operation';

    private const DOCUMENT_SOURCE_TINKOFF_BANK = 'tinkoff_bank';

    private ?int $bankOperationDocumentTypeId = null;

    /** @var array<string, int> */
    private array $documentPartyRoleIds = [];

    public function __construct(
        private readonly TinkoffBusinessClient $client = new TinkoffBusinessClient,
    ) {}

    public function sync(int $days, ?string $accountNumber = null, int $chunkDays = 30): array
    {
        $from = now()->subDays($days)->toDateString();
        $till = now()->toDateString();

        return $this->syncPeriod($from, $till, $accountNumber, $chunkDays);
    }

    public function syncPeriod(string $from, string $till, ?string $accountNumber = null, int $chunkDays = 30): array
    {
        $fromDate = Carbon::parse($from)->startOfDay();
        $tillDate = Carbon::parse($till)->startOfDay();

        if ($fromDate->greaterThan($tillDate)) {
            throw new RuntimeException('The sync period start date must be less than or equal to the end date.');
        }

        if ($chunkDays < 1) {
            throw new RuntimeException('The chunk size must be greater than zero.');
        }

        $credentials = $this->credentials($accountNumber);
        $from = $fromDate->toDateString();
        $till = $tillDate->toDateString();
        $summary = [
            'sync_run_id' => null,
            'legals' => count(array_unique(array_column($credentials, 'legal_id'))),
            'credentials' => count($credentials),
            'accounts' => 0,
            'operations' => 0,
            'from' => $from,
            'till' => $till,
        ];
        $syncRunId = $this->startRun($from, $till);
        $summary['sync_run_id'] = $syncRunId;

        try {
            foreach ($credentials as $credential) {
                $accounts = $this->client->accounts($credential['token'], $syncRunId);
                $summary['accounts'] += count($accounts);

                DB::transaction(function () use ($accounts, $credential): void {
                    foreach ($accounts as $account) {
                        $this->upsertAccount($account, (int) $credential['legal_id']);
                    }
                });

                foreach ($this->periodChunks($fromDate, $tillDate, $chunkDays) as [$chunkFrom, $chunkTill]) {
                    $statement = $this->client->statement(
                        $credential['token'],
                        $syncRunId,
                        $credential['account_number'],
                        $chunkFrom,
                        $chunkTill
                    );
                    $operations = $this->operationsFromStatement($statement);

                    DB::transaction(function () use ($operations, $credential): void {
                        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['legal.bank_transaction_1c']);

                        foreach ($operations as $operation) {
                            $this->upsertOperation(
                                $operation,
                                $credential['account_number'],
                                self::BANK_ID_TINKOFF,
                                self::DOCUMENT_SOURCE_TINKOFF_BANK
                            );
                        }
                    });

                    $summary['operations'] += count($operations);
                }

                $this->markCredentialUsed((int) $credential['api_credential_id']);
            }

            $this->finishRun($syncRunId, 'success', $summary);

            return $summary;
        } catch (Throwable $exception) {
            $this->finishRun($syncRunId, 'failed', $summary, $exception);

            throw $exception;
        }
    }

    /**
     * @return array<int, array{api_credential_id: int, legal_id: int, account_number: string, token: string}>
     */
    private function credentials(?string $accountNumber = null): array
    {
        $credentials = ApiCredential::query()
            ->from('legal.api_credentials as c')
            ->join('legal.bank_account as ba', 'ba.bank_account_id', '=', 'c.owner_id')
            ->where('c.provider', 'tinkoff')
            ->where('c.credential_type', 'bank_api_token')
            ->where('c.owner_type', 'bank_account')
            ->where('c.status', 'active')
            ->where('ba.bank_id', self::BANK_ID_TINKOFF)
            ->when($accountNumber !== null, fn ($query) => $query->where('ba.account_number', $accountNumber))
            ->orderBy('ba.legal_id')
            ->orderBy('ba.account_number')
            ->get([
                'c.api_credential_id',
                'c.encrypted_secret',
                'ba.legal_id',
                'ba.account_number',
            ]);

        if ($credentials->isEmpty()) {
            throw new RuntimeException($accountNumber === null
                ? 'No active Tinkoff bank account API credentials found in legal.api_credentials.'
                : "No active Tinkoff API credential found for bank account {$accountNumber}.");
        }

        return $credentials
            ->map(fn (ApiCredential $credential): array => [
                'api_credential_id' => (int) $credential->api_credential_id,
                'legal_id' => (int) $credential->legal_id,
                'account_number' => (string) $credential->account_number,
                'token' => $credential->secret(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function periodChunks(Carbon $from, Carbon $till, int $chunkDays): array
    {
        $chunks = [];
        $cursor = $from->copy();

        while ($cursor->lessThanOrEqualTo($till)) {
            $chunkTill = $cursor->copy()->addDays($chunkDays - 1);

            if ($chunkTill->greaterThan($till)) {
                $chunkTill = $till->copy();
            }

            $chunks[] = [$cursor->toDateString(), $chunkTill->toDateString()];
            $cursor = $chunkTill->copy()->addDay();
        }

        return $chunks;
    }

    private function markCredentialUsed(int $credentialId): void
    {
        DB::table('legal.api_credentials')
            ->where('api_credential_id', $credentialId)
            ->update([
                'last_used_at' => now(),
                'updated_at' => now(),
            ]);
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

    public function upsertImportedOperations(
        array $operations,
        string $bankId,
        string $accountNumber,
        string $sourceSystem,
    ): int {
        if ($operations === []) {
            return 0;
        }

        return DB::transaction(function () use ($operations, $bankId, $accountNumber, $sourceSystem): int {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['legal.bank_transaction_1c']);

            $count = 0;

            foreach ($operations as $operation) {
                $this->upsertOperation($operation, $accountNumber, $bankId, $sourceSystem);
                $count++;
            }

            return $count;
        });
    }

    private function upsertOperation(
        array $operation,
        string $accountNumber,
        string $bankId,
        string $sourceSystem,
    ): void
    {
        $operationId = (string) data_get($operation, 'operationId');

        if ($operationId === '') {
            return;
        }

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
            throw new RuntimeException("Bank account {$accountNumber} was not found for bank {$bankId}");
        }

        $signedAmount = $this->signedAmount($operation, $accountNumber);

        if ($existing === null) {
            $bankTransactionId = $this->insertParents($operation, (int) $account->legal_id, $bankId, $accountNumber, $signedAmount);
        } else {
            $bankTransactionId = (int) $existing->bank_transaction_id;
            $this->updateParents($bankTransactionId, $operation, (int) $account->legal_id, $bankId, $accountNumber, $signedAmount);
        }

        $this->upsertBankTransaction1c($operation, $bankTransactionId, $bankId, $accountNumber);
        $this->upsertDocumentBankTransaction(
            $operation,
            $bankTransactionId,
            $account,
            $bankId,
            $accountNumber,
            $signedAmount,
            $sourceSystem
        );
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

    private function upsertDocumentBankTransaction(
        array $operation,
        int $bankTransactionId,
        object $account,
        string $bankId,
        string $accountNumber,
        float $signedAmount,
        string $sourceSystem,
    ): void {
        $operationId = (string) data_get($operation, 'operationId');
        $externalId = $this->documentExternalId($bankId, $accountNumber, $operationId);
        $operationDate = $this->date(data_get($operation, 'date'));
        $now = now();

        $document = DB::selectOne('
            INSERT INTO legal.documents (
                document_type_id,
                document_date,
                document_number,
                title,
                amount,
                currency,
                status,
                source_system,
                external_id,
                external_hash,
                metadata,
                imported_at,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?, ?, ?)
            ON CONFLICT (document_type_id, source_system, external_id)
                WHERE source_system IS NOT NULL AND external_id IS NOT NULL
            DO UPDATE SET
                document_date = EXCLUDED.document_date,
                document_number = EXCLUDED.document_number,
                title = EXCLUDED.title,
                amount = EXCLUDED.amount,
                currency = EXCLUDED.currency,
                status = EXCLUDED.status,
                external_hash = EXCLUDED.external_hash,
                metadata = EXCLUDED.metadata,
                imported_at = EXCLUDED.imported_at,
                updated_at = EXCLUDED.updated_at
            RETURNING document_id
        ', [
            $this->bankOperationDocumentTypeId(),
            $operationDate,
            $operationId,
            $this->documentTitle($operation, $operationId),
            data_get($operation, 'amount'),
            $this->currency($operation, $account),
            'imported',
            $sourceSystem,
            $externalId,
            hash('sha256', $externalId.'|'.$this->json($operation)),
            $this->json([
                'bank_id' => $bankId,
                'account_number' => $accountNumber,
                'bank_account_id' => (int) $account->bank_account_id,
                'bank_transaction_id' => $bankTransactionId,
            ]),
            $now,
            $now,
            $now,
        ]);

        $documentId = (int) $document->document_id;

        DB::statement('
            INSERT INTO legal.document_bank_transaction (
                document_id,
                bank_transaction_id,
                bank_account_id,
                bank_id,
                account_number,
                external_operation_id,
                external_id,
                operation_date,
                draw_date,
                charge_date,
                order_intraday,
                amount,
                signed_amount,
                currency,
                payer_name,
                payer_inn,
                payer_kpp,
                payer_account,
                payer_bic,
                payer_bank,
                payer_corr_account,
                recipient_name,
                recipient_inn,
                recipient_kpp,
                recipient_account,
                recipient_bic,
                recipient_bank,
                recipient_corr_account,
                payment_purpose,
                payment_type,
                operation_type,
                uin,
                creator_status,
                kbk,
                oktmo,
                tax_evidence,
                tax_period,
                tax_doc_number,
                tax_doc_date,
                tax_type,
                execution_order,
                raw_payload,
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?, ?
            )
            ON CONFLICT (bank_account_id, external_operation_id)
            DO UPDATE SET
                document_id = EXCLUDED.document_id,
                bank_transaction_id = EXCLUDED.bank_transaction_id,
                bank_id = EXCLUDED.bank_id,
                account_number = EXCLUDED.account_number,
                external_id = EXCLUDED.external_id,
                operation_date = EXCLUDED.operation_date,
                draw_date = EXCLUDED.draw_date,
                charge_date = EXCLUDED.charge_date,
                order_intraday = EXCLUDED.order_intraday,
                amount = EXCLUDED.amount,
                signed_amount = EXCLUDED.signed_amount,
                currency = EXCLUDED.currency,
                payer_name = EXCLUDED.payer_name,
                payer_inn = EXCLUDED.payer_inn,
                payer_kpp = EXCLUDED.payer_kpp,
                payer_account = EXCLUDED.payer_account,
                payer_bic = EXCLUDED.payer_bic,
                payer_bank = EXCLUDED.payer_bank,
                payer_corr_account = EXCLUDED.payer_corr_account,
                recipient_name = EXCLUDED.recipient_name,
                recipient_inn = EXCLUDED.recipient_inn,
                recipient_kpp = EXCLUDED.recipient_kpp,
                recipient_account = EXCLUDED.recipient_account,
                recipient_bic = EXCLUDED.recipient_bic,
                recipient_bank = EXCLUDED.recipient_bank,
                recipient_corr_account = EXCLUDED.recipient_corr_account,
                payment_purpose = EXCLUDED.payment_purpose,
                payment_type = EXCLUDED.payment_type,
                operation_type = EXCLUDED.operation_type,
                uin = EXCLUDED.uin,
                creator_status = EXCLUDED.creator_status,
                kbk = EXCLUDED.kbk,
                oktmo = EXCLUDED.oktmo,
                tax_evidence = EXCLUDED.tax_evidence,
                tax_period = EXCLUDED.tax_period,
                tax_doc_number = EXCLUDED.tax_doc_number,
                tax_doc_date = EXCLUDED.tax_doc_date,
                tax_type = EXCLUDED.tax_type,
                execution_order = EXCLUDED.execution_order,
                raw_payload = EXCLUDED.raw_payload,
                updated_at = EXCLUDED.updated_at
        ', [
            $documentId,
            $bankTransactionId,
            (int) $account->bank_account_id,
            $bankId,
            $accountNumber,
            $operationId,
            data_get($operation, 'id'),
            $operationDate,
            $this->date(data_get($operation, 'drawDate')),
            $this->date(data_get($operation, 'chargeDate')),
            $operationId,
            data_get($operation, 'amount'),
            $signedAmount,
            $this->currency($operation, $account),
            $this->nullableString(data_get($operation, 'payerName')),
            $this->nullableString(data_get($operation, 'payerInn')),
            $this->nullableString(data_get($operation, 'payerKpp')),
            $this->nullableString(data_get($operation, 'payerAccount')),
            $this->nullableString(data_get($operation, 'payerBic')),
            $this->nullableString(data_get($operation, 'payerBank')),
            $this->nullableString(data_get($operation, 'payerCorrAccount')),
            $this->nullableString(data_get($operation, 'recipient')),
            $this->nullableString(data_get($operation, 'recipientInn')),
            $this->nullableString(data_get($operation, 'recipientKpp')),
            $this->nullableString(data_get($operation, 'recipientAccount')),
            $this->nullableString(data_get($operation, 'recipientBic')),
            $this->nullableString(data_get($operation, 'recipientBank')),
            $this->nullableString(data_get($operation, 'recipientCorrAccount')),
            $this->nullableString(data_get($operation, 'paymentPurpose')),
            $this->nullableString(data_get($operation, 'paymentType')),
            $this->nullableString(data_get($operation, 'operationType')),
            $this->nullableString(data_get($operation, 'uin')),
            $this->nullableString(data_get($operation, 'creatorStatus')),
            $this->nullableString(data_get($operation, 'kbk')),
            $this->nullableString(data_get($operation, 'oktmo')),
            $this->nullableString(data_get($operation, 'taxEvidence')),
            $this->nullableString(data_get($operation, 'taxPeriod')),
            $this->nullableString(data_get($operation, 'taxDocNumber')),
            $this->nullableString(data_get($operation, 'taxDocDate')),
            $this->nullableString(data_get($operation, 'taxType')),
            $this->nullableString(data_get($operation, 'executionOrder')),
            $this->json($operation),
            $now,
            $now,
        ]);

        $this->upsertDocumentParty($documentId, 'payer', [
            'name' => data_get($operation, 'payerName'),
            'inn' => data_get($operation, 'payerInn'),
            'kpp' => data_get($operation, 'payerKpp'),
            'account' => data_get($operation, 'payerAccount'),
            'bic' => data_get($operation, 'payerBic'),
            'bank' => data_get($operation, 'payerBank'),
            'corr_account' => data_get($operation, 'payerCorrAccount'),
        ]);

        $this->upsertDocumentParty($documentId, 'recipient', [
            'name' => data_get($operation, 'recipient'),
            'inn' => data_get($operation, 'recipientInn'),
            'kpp' => data_get($operation, 'recipientKpp'),
            'account' => data_get($operation, 'recipientAccount'),
            'bic' => data_get($operation, 'recipientBic'),
            'bank' => data_get($operation, 'recipientBank'),
            'corr_account' => data_get($operation, 'recipientCorrAccount'),
        ]);
    }

    /**
     * @param  array{name: mixed, inn: mixed, kpp: mixed, account: mixed, bic: mixed, bank: mixed, corr_account: mixed}  $party
     */
    private function upsertDocumentParty(int $documentId, string $role, array $party): void
    {
        $name = $this->nullableString($party['name']);

        if ($name === null) {
            return;
        }

        $documentPartyRoleId = $this->documentPartyRoleId($role);

        DB::table('legal.document_parties')->upsert([[
            'document_id' => $documentId,
            'party_id' => null,
            'document_party_role_id' => $documentPartyRoleId,
            'role_index' => 1,
            'name_snapshot' => $name,
            'inn_snapshot' => $this->nullableString($party['inn']),
            'kpp_snapshot' => $this->nullableString($party['kpp']),
            'country_code' => 'RU',
            'metadata' => $this->json([
                'account' => $this->nullableString($party['account']),
                'bic' => $this->nullableString($party['bic']),
                'bank' => $this->nullableString($party['bank']),
                'corr_account' => $this->nullableString($party['corr_account']),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['document_id', 'document_party_role_id', 'role_index'], [
            'party_id',
            'name_snapshot',
            'inn_snapshot',
            'kpp_snapshot',
            'country_code',
            'metadata',
            'updated_at',
        ]);
    }

    private function documentPartyRoleId(string $role): int
    {
        if (isset($this->documentPartyRoleIds[$role])) {
            return $this->documentPartyRoleIds[$role];
        }

        $id = DB::table('legal.document_party_roles')
            ->where('code', $role)
            ->value('document_party_role_id');

        if ($id === null) {
            throw new RuntimeException("Document party role {$role} was not found in legal.document_party_roles.");
        }

        return $this->documentPartyRoleIds[$role] = (int) $id;
    }

    private function bankOperationDocumentTypeId(): int
    {
        if ($this->bankOperationDocumentTypeId !== null) {
            return $this->bankOperationDocumentTypeId;
        }

        $id = DB::table('legal.document_types')
            ->where('code', self::DOCUMENT_TYPE_BANK_OPERATION)
            ->value('document_type_id');

        if ($id === null) {
            throw new RuntimeException('Document type bank_operation was not found in legal.document_types.');
        }

        return $this->bankOperationDocumentTypeId = (int) $id;
    }

    private function documentExternalId(string $bankId, string $accountNumber, string $operationId): string
    {
        return $bankId.':'.$accountNumber.':'.$operationId;
    }

    private function documentTitle(array $operation, string $operationId): string
    {
        $purpose = $this->nullableString(data_get($operation, 'paymentPurpose'));

        return $purpose !== null ? mb_substr($purpose, 0, 500) : 'Bank operation '.$operationId;
    }

    private function currency(array $operation, object $account): string
    {
        return (string) (data_get($operation, 'currency') ?: ($account->currency ?? 'RUB'));
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

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
