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

        $this->ensureReferenceRows();

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
                        $this->upsertAccount($account, (string) $credential['legal_id']);
                    }
                });

                foreach ($this->periodChunks($fromDate, $tillDate, $chunkDays) as [$chunkFrom, $chunkTill]) {
                    $statementResult = $this->client->statementWithRequest(
                        $credential['token'],
                        $syncRunId,
                        $credential['account_number'],
                        $chunkFrom,
                        $chunkTill
                    );
                    $operations = $this->operationsFromStatement($statementResult['data']);
                    $apiSyncRequestId = $statementResult['api_sync_request_id'];

                    DB::transaction(function () use ($operations, $credential, $apiSyncRequestId): void {
                        foreach ($operations as $operation) {
                            $this->upsertOperation(
                                $operation,
                                $credential['account_number'],
                                self::BANK_ID_TINKOFF,
                                self::DOCUMENT_SOURCE_TINKOFF_BANK,
                                $apiSyncRequestId
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

    public function syncSinceActivationDates(?string $accountNumber = null, int $chunkDays = 30): array
    {
        if ($chunkDays < 1) {
            throw new RuntimeException('The chunk size must be greater than zero.');
        }

        $this->ensureReferenceRows();

        $credentials = $this->credentials($accountNumber);
        $tillDate = now()->startOfDay();
        $till = $tillDate->toDateString();
        $from = $this->earliestActivationDate($credentials)?->toDateString() ?? $till;
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
                        $this->upsertAccount($account, (string) $credential['legal_id']);
                    }
                });

                $fromDate = $this->activationDateForCredential($credential) ?? $tillDate;
                if ($fromDate->greaterThan($tillDate)) {
                    $fromDate = $tillDate->copy();
                }
                if (Carbon::parse($summary['from'])->greaterThan($fromDate)) {
                    $summary['from'] = $fromDate->toDateString();
                }

                foreach ($this->periodChunks($fromDate, $tillDate, $chunkDays) as [$chunkFrom, $chunkTill]) {
                    $statementResult = $this->client->statementWithRequest(
                        $credential['token'],
                        $syncRunId,
                        $credential['account_number'],
                        $chunkFrom,
                        $chunkTill
                    );
                    $operations = $this->operationsFromStatement($statementResult['data']);
                    $apiSyncRequestId = $statementResult['api_sync_request_id'];

                    DB::transaction(function () use ($operations, $credential, $apiSyncRequestId): void {
                        foreach ($operations as $operation) {
                            $this->upsertOperation(
                                $operation,
                                $credential['account_number'],
                                self::BANK_ID_TINKOFF,
                                self::DOCUMENT_SOURCE_TINKOFF_BANK,
                                $apiSyncRequestId
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
     * @return array<int, array{api_credential_id: int, legal_id: string, account_number: string, token: string}>
     */
    private function credentials(?string $accountNumber = null): array
    {
        $credentials = ApiCredential::query()
            ->from('legal.api_credentials as c')
            ->join('legal.bank_account as ba', DB::raw('ba.bank_account_id::text'), '=', 'c.owner_id')
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
                'legal_id' => (string) $credential->legal_id,
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

    /**
     * @param  array<int, array{account_number: string}>  $credentials
     */
    private function earliestActivationDate(array $credentials): ?Carbon
    {
        $accountNumbers = array_values(array_unique(array_column($credentials, 'account_number')));

        if ($accountNumbers === []) {
            return null;
        }

        $date = DB::table('legal.bank_account')
            ->where('bank_id', self::BANK_ID_TINKOFF)
            ->whereIn('account_number', $accountNumbers)
            ->whereNotNull('activation_date')
            ->min('activation_date');

        return $date !== null ? Carbon::parse((string) $date)->startOfDay() : null;
    }

    /**
     * @param  array{account_number: string}  $credential
     */
    private function activationDateForCredential(array $credential): ?Carbon
    {
        $date = DB::table('legal.bank_account')
            ->where('bank_id', self::BANK_ID_TINKOFF)
            ->where('account_number', $credential['account_number'])
            ->value('activation_date');

        return $date !== null ? Carbon::parse((string) $date)->startOfDay() : null;
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

    private function ensureReferenceRows(): void
    {
        $this->bankOperationDocumentTypeId();
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
                'period_from' => $summary['from'],
                'period_till' => $summary['till'],
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

    private function upsertAccount(array $account, string $legalId): void
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
        array $sourceContext = [],
    ): int {
        if ($operations === []) {
            return 0;
        }

        return DB::transaction(function () use ($operations, $bankId, $accountNumber, $sourceSystem, $sourceContext): int {
            $count = 0;

            foreach ($operations as $operation) {
                $this->upsertOperation($operation, $accountNumber, $bankId, $sourceSystem, null, $sourceContext);
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
        ?int $apiSyncRequestId,
        array $sourceContext = [],
    ): void
    {
        $operationId = (string) data_get($operation, 'operationId');

        if ($operationId === '') {
            return;
        }

        $account = DB::table('legal.bank_account')
            ->where('bank_id', $bankId)
            ->where('account_number', $accountNumber)
            ->first();

        if ($account === null) {
            throw new RuntimeException("Bank account {$accountNumber} was not found for bank {$bankId}");
        }

        $signedAmount = $this->signedAmount($operation, $accountNumber);

        $documentId = $this->upsertDocumentBankTransaction(
            $operation,
            $account,
            $bankId,
            $accountNumber,
            $signedAmount,
            $sourceSystem
        );

        $sourceRecordId = $this->upsertSourceRecordForBankOperation(
            $operation,
            $account,
            $bankId,
            $accountNumber,
            $signedAmount,
            $sourceSystem,
            $apiSyncRequestId,
            $sourceContext,
        );

        $this->upsertDocumentSource($documentId, $sourceRecordId, $sourceSystem);
    }

    private function upsertDocumentBankTransaction(
        array $operation,
        object $account,
        string $bankId,
        string $accountNumber,
        float $signedAmount,
        string $sourceSystem,
    ): int {
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
            ]),
            $now,
            $now,
            $now,
        ]);

        $documentId = (int) $document->document_id;

        DB::statement('
            INSERT INTO legal.document_bank_transaction (
                document_id,
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
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?, ?
            )
            ON CONFLICT (bank_account_id, external_operation_id)
            DO UPDATE SET
                document_id = EXCLUDED.document_id,
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

        return $documentId;
    }

    private function upsertSourceRecordForBankOperation(
        array $operation,
        object $account,
        string $bankId,
        string $accountNumber,
        float $signedAmount,
        string $sourceSystem,
        ?int $apiSyncRequestId,
        array $sourceContext = [],
    ): int {
        $operationId = (string) data_get($operation, 'operationId');
        $externalId = $this->documentExternalId($bankId, $accountNumber, $operationId);
        $operationDate = $this->date(data_get($operation, 'date'));
        $now = now();
        $rawPayload = $this->operationSourcePayload($operation);
        $metadata = [
            'bank_id' => $bankId,
            'account_number' => $accountNumber,
            'bank_account_id' => (int) $account->bank_account_id,
        ];

        if ($sourceContext !== []) {
            $metadata['source_context'] = $sourceContext;
        }

        $sourceRecord = DB::selectOne(<<<'SQL'
INSERT INTO legal.source_records (
    source_system,
    source_channel,
    source_record_type,
    external_id,
    external_hash,
    source_api_sync_request_id,
    source_file_path,
    received_at,
    recorded_at,
    raw_payload,
    metadata,
    created_at,
    updated_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, ?, ?)
ON CONFLICT (source_system, source_record_type, external_id)
    WHERE external_id IS NOT NULL
DO UPDATE SET
    source_channel = EXCLUDED.source_channel,
    external_hash = EXCLUDED.external_hash,
    source_api_sync_request_id = EXCLUDED.source_api_sync_request_id,
    source_file_path = EXCLUDED.source_file_path,
    received_at = EXCLUDED.received_at,
    recorded_at = EXCLUDED.recorded_at,
    raw_payload = EXCLUDED.raw_payload,
    metadata = EXCLUDED.metadata,
    updated_at = EXCLUDED.updated_at
RETURNING source_record_id
SQL, [
            $sourceSystem,
            $apiSyncRequestId !== null ? 'api' : 'imported_operation',
            'bank_statement_line',
            $externalId,
            hash('sha256', $externalId.'|'.$this->json($rawPayload)),
            $apiSyncRequestId,
            $sourceContext['stored_path'] ?? null,
            $now,
            $operationDate,
            $this->json($rawPayload),
            $this->json($metadata),
            $now,
            $now,
        ]);

        $sourceRecordId = (int) $sourceRecord->source_record_id;

        $this->upsertSourceRecordFile($sourceRecordId, $sourceContext, $now);

        DB::table('legal.source_record_bank_details')->upsert([[
            'source_record_id' => $sourceRecordId,
            'bank_account_id' => (int) $account->bank_account_id,
            'bank_id' => $bankId,
            'account_number' => $accountNumber,
            'external_operation_id' => $operationId,
            'operation_date' => $operationDate,
            'draw_date' => $this->date(data_get($operation, 'drawDate')),
            'charge_date' => $this->date(data_get($operation, 'chargeDate')),
            'order_intraday' => $operationId,
            'payment_purpose' => $this->nullableString(data_get($operation, 'paymentPurpose')),
            'payment_type' => $this->nullableString(data_get($operation, 'paymentType')),
            'operation_type' => $this->nullableString(data_get($operation, 'operationType')),
            'signed_amount' => $signedAmount,
            'saldo' => data_get($operation, 'saldo'),
            'tax_fields' => $this->json([
                'uin' => $this->nullableString(data_get($operation, 'uin')),
                'creator_status' => $this->nullableString(data_get($operation, 'creatorStatus')),
                'kbk' => $this->nullableString(data_get($operation, 'kbk')),
                'oktmo' => $this->nullableString(data_get($operation, 'oktmo')),
                'tax_evidence' => $this->nullableString(data_get($operation, 'taxEvidence')),
                'tax_period' => $this->nullableString(data_get($operation, 'taxPeriod')),
                'tax_doc_number' => $this->nullableString(data_get($operation, 'taxDocNumber')),
                'tax_doc_date' => $this->nullableString(data_get($operation, 'taxDocDate')),
                'tax_type' => $this->nullableString(data_get($operation, 'taxType')),
                'execution_order' => $this->nullableString(data_get($operation, 'executionOrder')),
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['source_record_id'], [
            'bank_account_id',
            'bank_id',
            'account_number',
            'external_operation_id',
            'operation_date',
            'draw_date',
            'charge_date',
            'order_intraday',
            'payment_purpose',
            'payment_type',
            'operation_type',
            'signed_amount',
            'saldo',
            'tax_fields',
            'updated_at',
        ]);

        $this->upsertSourceRecordParty($sourceRecordId, 'payer', [
            'name' => data_get($operation, 'payerName'),
            'inn' => data_get($operation, 'payerInn'),
            'kpp' => data_get($operation, 'payerKpp'),
            'account' => data_get($operation, 'payerAccount'),
            'bic' => data_get($operation, 'payerBic'),
            'bank' => data_get($operation, 'payerBank'),
            'corr_account' => data_get($operation, 'payerCorrAccount'),
        ]);

        $this->upsertSourceRecordParty($sourceRecordId, 'recipient', [
            'name' => data_get($operation, 'recipient'),
            'inn' => data_get($operation, 'recipientInn'),
            'kpp' => data_get($operation, 'recipientKpp'),
            'account' => data_get($operation, 'recipientAccount'),
            'bic' => data_get($operation, 'recipientBic'),
            'bank' => data_get($operation, 'recipientBank'),
            'corr_account' => data_get($operation, 'recipientCorrAccount'),
        ]);

        DB::table('legal.source_record_amounts')->upsert([[
            'source_record_id' => $sourceRecordId,
            'amount_type' => 'statement_amount',
            'amount' => (float) data_get($operation, 'amount', 0),
            'currency' => $this->currency($operation, $account),
            'tax_rate' => null,
            'raw_payload' => $this->json([
                'amount' => data_get($operation, 'amount'),
                'signed_amount' => $signedAmount,
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['source_record_id', 'amount_type'], [
            'amount',
            'currency',
            'tax_rate',
            'raw_payload',
            'updated_at',
        ]);

        return $sourceRecordId;
    }

    private function operationSourcePayload(array $operation): array
    {
        $sourceRow = data_get($operation, 'sourceRow');

        return is_array($sourceRow) ? $sourceRow : $operation;
    }

    private function upsertSourceRecordFile(int $sourceRecordId, array $sourceContext, Carbon $now): void
    {
        $storedPath = $this->nullableString($sourceContext['stored_path'] ?? null);

        if ($storedPath === null) {
            return;
        }

        $fileHash = $this->nullableString($sourceContext['file_sha256'] ?? null);
        $values = [
            'source_record_id' => $sourceRecordId,
            'file_role' => 'source_statement',
            'source_file_name' => $this->nullableString($sourceContext['source_file_name'] ?? null),
            'stored_path' => $storedPath,
            'mime_type' => $this->nullableString($sourceContext['mime_type'] ?? null),
            'file_sha256' => $fileHash,
            'file_size' => $sourceContext['file_size'] ?? null,
            'encoding' => $this->nullableString($sourceContext['encoding'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $existing = $fileHash !== null
            ? DB::table('legal.source_record_files')
                ->where('source_record_id', $sourceRecordId)
                ->where('file_sha256', $fileHash)
                ->first()
            : null;

        if ($existing === null) {
            DB::table('legal.source_record_files')->insert($values);

            return;
        }

        unset($values['source_record_id'], $values['file_sha256'], $values['created_at']);

        DB::table('legal.source_record_files')
            ->where('source_record_file_id', $existing->source_record_file_id)
            ->update($values);
    }

    /**
     * @param  array{name: mixed, inn: mixed, kpp: mixed, account: mixed, bic: mixed, bank: mixed, corr_account: mixed}  $party
     */
    private function upsertSourceRecordParty(int $sourceRecordId, string $role, array $party): void
    {
        $name = $this->nullableString($party['name']);

        if ($name === null) {
            return;
        }

        DB::table('legal.source_record_parties')->upsert([[
            'source_record_id' => $sourceRecordId,
            'source_party_role' => $role,
            'role_index' => 1,
            'party_name' => $name,
            'inn' => $this->nullableString($party['inn']),
            'kpp' => $this->nullableString($party['kpp']),
            'account_number' => $this->nullableString($party['account']),
            'bank_bic' => $this->nullableString($party['bic']),
            'bank_name' => $this->nullableString($party['bank']),
            'address' => null,
            'raw_payload' => $this->json([
                'corr_account' => $this->nullableString($party['corr_account']),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['source_record_id', 'source_party_role', 'role_index'], [
            'party_name',
            'inn',
            'kpp',
            'account_number',
            'bank_bic',
            'bank_name',
            'address',
            'raw_payload',
            'updated_at',
        ]);
    }

    private function upsertDocumentSource(int $documentId, int $sourceRecordId, string $sourceSystem): void
    {
        DB::statement(<<<'SQL'
INSERT INTO legal.document_sources (
    document_id,
    source_record_id,
    source_item_key,
    source_role,
    confidence,
    matched_by,
    matched_at,
    metadata,
    created_at,
    updated_at
) VALUES (?, ?, '', 'primary', 1, ?, ?, ?::jsonb, ?, ?)
ON CONFLICT (source_record_id, source_item_key)
DO UPDATE SET
    document_id = EXCLUDED.document_id,
    source_role = EXCLUDED.source_role,
    confidence = EXCLUDED.confidence,
    matched_by = EXCLUDED.matched_by,
    matched_at = EXCLUDED.matched_at,
    metadata = EXCLUDED.metadata,
    updated_at = EXCLUDED.updated_at
SQL, [
            $documentId,
            $sourceRecordId,
            $sourceSystem.'_sync',
            now(),
            $this->json([
                'source_system' => $sourceSystem,
            ]),
            now(),
            now(),
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
