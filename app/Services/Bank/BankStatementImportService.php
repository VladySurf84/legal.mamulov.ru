<?php

namespace App\Services\Bank;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BankStatementImportService
{
    public const BANK_ID_OZON = '044525068';

    private const DOCUMENT_SOURCE_1C_CLIENT_BANK_EXCHANGE = '1c_client_bank_exchange';

    public function __construct(
        private readonly TinkoffBankSyncService $bankSyncService = new TinkoffBankSyncService,
    ) {}

    public function importFile(
        string $file,
        ?string $bankId = null,
        bool $rebuildMoneyLayer = false,
        ?string $sourceFileName = null,
        ?int $uploadedByUserId = null,
    ): array
    {
        if (! is_file($file)) {
            throw new RuntimeException("File '{$file}' was not found.");
        }

        $sourceFileName ??= basename($file);
        $fileHash = hash_file('sha256', $file);
        $fileSize = filesize($file);
        $now = now();

        ['account_number' => $accountNumber, 'rows' => $rows] = $this->parse1CClientBankExchangeFile($file);

        $bankId ??= $this->bankIdByAccountNumber($accountNumber);
        $provider = $this->providerForBankId($bankId);
        $sourceSystem = self::DOCUMENT_SOURCE_1C_CLIENT_BANK_EXCHANGE;
        $importRunId = $this->startImportRun($sourceFileName, $fileHash, $fileSize !== false ? $fileSize : null, $bankId, $provider);

        try {
            $storedPath = $this->storeOriginal($file, $sourceFileName, $fileHash, $importRunId);
            $uploadedFileId = $this->insertUploadedFile(
                $importRunId,
                $provider,
                $sourceFileName,
                $storedPath,
                $fileHash,
                $fileSize !== false ? $fileSize : 0,
                $uploadedByUserId,
                $now,
            );

            $operations = $this->map1CClientBankExchangeRows($rows);
            $count = $this->bankSyncService->upsertImportedOperations(
                $operations,
                $bankId,
                $accountNumber,
                $sourceSystem,
                [
                    'import_run_id' => $importRunId,
                    'uploaded_file_id' => $uploadedFileId,
                    'source_file_name' => $sourceFileName,
                    'stored_path' => $storedPath,
                    'file_sha256' => $fileHash,
                    'file_size' => $fileSize !== false ? $fileSize : null,
                    'mime_type' => 'text/plain',
                    'encoding' => 'Windows-1251/UTF-8',
                ],
            );

            if ($rebuildMoneyLayer) {
                app(\App\Services\Layers\MoneyLayerBuilder::class)->rebuild();
            }

            $this->finishImportRun($importRunId, 'success', count($rows), $count, [
                'bank_id' => $bankId,
                'account_number' => $accountNumber,
                'uploaded_file_id' => $uploadedFileId,
                'stored_path' => $storedPath,
            ]);

            return [
                'import_run_id' => $importRunId,
                'uploaded_file_id' => $uploadedFileId,
                'stored_path' => $storedPath,
                'bank_id' => $bankId,
                'account_number' => $accountNumber,
                'rows' => count($rows),
                'operations' => $count,
            ];
        } catch (Throwable $exception) {
            $this->finishImportRun($importRunId, 'failed', 0, 0, [
                'bank_id' => $bankId,
            ], $exception);

            throw $exception;
        }
    }

    /**
     * @return array{account_number: string, rows: array<int, array<string, string>>}
     */
    public function parse1CClientBankExchangeFile(string $file): array
    {
        if (! is_file($file)) {
            throw new RuntimeException("File '{$file}' was not found.");
        }

        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new RuntimeException("File '{$file}' could not be opened.");
        }

        $rows = [];
        $accountNumber = null;
        $insideAccountSection = false;
        $document = null;

        try {
            while (($line = fgets($handle)) !== false) {
                $line = $this->decodeLine($line);

                if (str_starts_with($line, 'СекцияРасчСчет')) {
                    $insideAccountSection = true;
                }

                if ($insideAccountSection && str_starts_with($line, 'РасчСчет=')) {
                    $accountNumber = trim(explode('=', $line, 2)[1] ?? '');
                }

                if (str_starts_with($line, 'КонецРасчСчет')) {
                    $insideAccountSection = false;
                }

                if ($insideAccountSection || $accountNumber === null) {
                    continue;
                }

                if (str_starts_with($line, 'СекцияДокумент=')) {
                    $document = [];
                    [$key, $value] = $this->splitKeyValue($line);
                    $document[$key] = $value;

                    continue;
                }

                if (str_starts_with($line, 'КонецДокумента')) {
                    if ($document !== null) {
                        $rows[] = $document;
                    }

                    $document = null;

                    continue;
                }

                if ($document === null || ! str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = $this->splitKeyValue($line);
                $document[$key] = $value;
            }
        } finally {
            fclose($handle);
        }

        if ($accountNumber === null || $accountNumber === '') {
            throw new RuntimeException('Account number was not found in 1CClientBankExchange file.');
        }

        return [
            'account_number' => $accountNumber,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function map1CClientBankExchangeRows(array $rows): array
    {
        $baseIds = array_map(fn (array $row): string => $this->bankReportOperationBaseId($row), $rows);
        $baseCounts = array_count_values($baseIds);
        $seen = [];
        $operations = [];

        foreach ($rows as $index => $row) {
            $baseId = $baseIds[$index];
            $seen[$baseId] = ($seen[$baseId] ?? 0) + 1;
            $suffix = ($baseCounts[$baseId] ?? 0) > 1 && $seen[$baseId] > 1
                ? '_row'.($index + 1)
                : '';

            $operations[] = $this->map1CClientBankExchangeRow($row, $baseId, $suffix);
        }

        return $operations;
    }

    private function map1CClientBankExchangeRow(array $row, string $baseOperationId, string $operationIdSuffix = ''): array
    {
        $date = $this->parseBankReportDate($row['Дата'] ?? null);
        $number = $row['Номер'] ?? '';
        $operationId = $baseOperationId.$operationIdSuffix;

        if ($number === '') {
            $operationId = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        return [
            'operationId' => $operationId,
            'id' => $number !== '' ? $number : $operationId,
            'date' => $date,
            'amount' => $this->parseBankReportAmount($row['Сумма'] ?? 0),
            'payerAccount' => $row['ПлательщикСчет'] ?? null,
            'drawDate' => $this->parseBankReportDate($row['ДатаСписано'] ?? null),
            'payerInn' => $row['ПлательщикИНН'] ?? null,
            'payerName' => $row['Плательщик1'] ?? null,
            'payerBank' => $row['ПлательщикБанк1'] ?? null,
            'payerBic' => $row['ПлательщикБИК'] ?? null,
            'payerKpp' => $row['ПлательщикКПП'] ?? null,
            'recipientAccount' => $row['ПолучательСчет'] ?? null,
            'chargeDate' => $this->parseBankReportDate($row['ДатаПоступило'] ?? null),
            'recipientInn' => $row['ПолучательИНН'] ?? null,
            'recipient' => $row['Получатель1'] ?? null,
            'recipientBank' => $row['ПолучательБанк1'] ?? null,
            'recipientBic' => $row['ПолучательБИК'] ?? null,
            'recipientKpp' => $row['ПолучательКПП'] ?? null,
            'recipientCorrAccount' => $row['ПолучательКорсчет'] ?? null,
            'paymentType' => $row['ВидПлатежа'] ?? null,
            'operationType' => $row['ВидОплаты'] ?? null,
            'executionOrder' => $row['Очередность'] ?? null,
            'paymentPurpose' => $row['НазначениеПлатежа'] ?? null,
            'uin' => $this->normalizeBankReportOptionalValue($row['Код'] ?? null),
            'creatorStatus' => $this->normalizeBankReportOptionalValue($row['СтатусСоставителя'] ?? null),
            'kbk' => $this->normalizeBankReportOptionalValue($row['ПоказательКБК'] ?? null),
            'oktmo' => $this->normalizeBankReportOptionalValue($row['ОКАТО'] ?? null),
            'taxEvidence' => $this->normalizeBankReportOptionalValue($row['ПоказательОснования'] ?? null),
            'taxPeriod' => $this->normalizeBankReportOptionalValue($row['ПоказательПериода'] ?? null),
            'taxDocNumber' => $this->normalizeBankReportOptionalValue($row['ПоказательНомера'] ?? null),
            'taxDocDate' => $this->parseBankReportDate($row['ПоказательДаты'] ?? null),
            'taxType' => $this->normalizeBankReportOptionalValue($row['КодНазПлатежа'] ?? null),
            'sourceRow' => $row,
        ];
    }

    private function bankReportOperationBaseId(array $row): string
    {
        $date = $this->parseBankReportDate($row['Дата'] ?? null);
        $number = $row['Номер'] ?? '';

        if ($number === '') {
            return hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        return ($date ?: 'no-date').'_'.$number;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitKeyValue(string $line): array
    {
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');

        return [trim($key), trim($value)];
    }

    private function decodeLine(string $line): string
    {
        if (! mb_check_encoding($line, 'UTF-8')) {
            $line = mb_convert_encoding($line, 'UTF-8', 'Windows-1251');
        }

        return trim($line);
    }

    private function parseBankReportDate(?string $date): ?string
    {
        $date = trim((string) $date);

        if ($date === '' || in_array($date, ['0', '00.00.0000'], true)) {
            return null;
        }

        foreach (['d.m.Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $date)->toDateString();
            } catch (\Throwable) {
                //
            }
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseBankReportAmount(int|float|string|null $amount): float
    {
        return (float) str_replace(',', '.', str_replace(' ', '', (string) ($amount ?? 0)));
    }

    private function normalizeBankReportOptionalValue(int|float|string|null $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || $value === '0' ? null : $value;
    }

    private function bankIdByAccountNumber(string $accountNumber): string
    {
        $bankIds = DB::table('legal.bank_account')
            ->where('account_number', $accountNumber)
            ->orderBy('bank_id')
            ->pluck('bank_id')
            ->all();

        if ($bankIds === []) {
            throw new RuntimeException("Bank account {$accountNumber} was not found in legal.bank_account.");
        }

        $bankIds = array_values(array_unique(array_map('strval', $bankIds)));

        if (count($bankIds) > 1) {
            throw new RuntimeException(
                "Bank account {$accountNumber} exists in several banks: ".implode(', ', $bankIds).'. Pass --bank-id explicitly.'
            );
        }

        return $bankIds[0];
    }

    private function providerForBankId(string $bankId): string
    {
        return $bankId === self::BANK_ID_OZON ? 'ozon_bank' : 'bank_statement_file';
    }

    private function startImportRun(string $sourceFileName, string $fileHash, ?int $fileSize, ?string $bankId, string $provider): int
    {
        $now = now();
        $row = DB::selectOne(<<<'SQL'
INSERT INTO legal.import_runs (
    provider,
    type,
    status,
    source_file_name,
    file_sha256,
    file_size,
    metadata,
    started_at,
    created_at,
    updated_at
) VALUES (?, ?, ?, ?, ?, ?, ?::jsonb, ?, ?, ?)
RETURNING import_run_id
SQL, [
            $provider,
            'bank_statement_file_import',
            'started',
            $sourceFileName,
            $fileHash,
            $fileSize,
            $this->json([
                'bank_id' => $bankId,
                'format' => '1CClientBankExchange',
            ]),
            $now,
            $now,
            $now,
        ]);

        return (int) $row->import_run_id;
    }

    private function finishImportRun(
        int $importRunId,
        string $status,
        int $recordsCount,
        int $operationsCount,
        array $metadata,
        ?Throwable $exception = null,
    ): void {
        DB::table('legal.import_runs')
            ->where('import_run_id', $importRunId)
            ->update([
                'status' => $status,
                'records_count' => $recordsCount,
                'operations_count' => $operationsCount,
                'error' => $exception?->getMessage(),
                'metadata' => $this->json($metadata),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function insertUploadedFile(
        int $importRunId,
        string $provider,
        string $sourceFileName,
        string $storedPath,
        string $fileHash,
        int $fileSize,
        ?int $uploadedByUserId,
        Carbon $now,
    ): int {
        $row = DB::selectOne(<<<'SQL'
INSERT INTO legal.uploaded_files (
    import_run_id,
    provider,
    file_role,
    source_file_name,
    stored_path,
    mime_type,
    file_sha256,
    file_size,
    uploaded_by_user_id,
    metadata,
    uploaded_at,
    created_at,
    updated_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?, ?, ?)
ON CONFLICT (stored_path)
DO UPDATE SET
    import_run_id = EXCLUDED.import_run_id,
    source_file_name = EXCLUDED.source_file_name,
    mime_type = EXCLUDED.mime_type,
    file_sha256 = EXCLUDED.file_sha256,
    file_size = EXCLUDED.file_size,
    uploaded_by_user_id = EXCLUDED.uploaded_by_user_id,
    metadata = EXCLUDED.metadata,
    uploaded_at = EXCLUDED.uploaded_at,
    updated_at = EXCLUDED.updated_at
RETURNING uploaded_file_id
SQL, [
            $importRunId,
            $provider,
            'bank_statement_source',
            $sourceFileName,
            $storedPath,
            'text/plain',
            $fileHash,
            $fileSize,
            $uploadedByUserId,
            $this->json([
                'format' => '1CClientBankExchange',
            ]),
            $now,
            $now,
            $now,
        ]);

        return (int) $row->uploaded_file_id;
    }

    private function storeOriginal(string $file, string $sourceFileName, string $fileHash, int $importRunId): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $sourceFileName) ?: 'ozon-bank-statement.txt';
        $storedPath = sprintf(
            'legal/imports/ozon-bank/%s/run_%d/%s_%s',
            now()->format('Y/m/d'),
            $importRunId,
            substr($fileHash, 0, 12),
            $safeName,
        );

        Storage::disk('local')->put($storedPath, file_get_contents($file));

        return $storedPath;
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
