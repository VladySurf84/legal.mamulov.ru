<?php

namespace App\Services\Bank;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OzonBankStatementImportService
{
    public const BANK_ID_OZON = '044525068';

    private const DOCUMENT_SOURCE_OZON_BANK_FILE = 'ozon_bank_file';

    public function __construct(
        private readonly TinkoffBankSyncService $bankSyncService = new TinkoffBankSyncService,
    ) {}

    public function importFile(string $file, ?string $bankId = null, bool $rebuildMoneyLayer = false): array
    {
        ['account_number' => $accountNumber, 'rows' => $rows] = $this->parse1CClientBankExchangeFile($file);

        $bankId ??= $this->bankIdByAccountNumber($accountNumber);
        $operations = array_map(fn (array $row): array => $this->map1CClientBankExchangeRow($row), $rows);
        $count = $this->bankSyncService->upsertImportedOperations(
            $operations,
            $bankId,
            $accountNumber,
            self::DOCUMENT_SOURCE_OZON_BANK_FILE,
        );

        if ($rebuildMoneyLayer) {
            app(\App\Services\Layers\MoneyLayerBuilder::class)->rebuild();
        }

        return [
            'bank_id' => $bankId,
            'account_number' => $accountNumber,
            'rows' => count($rows),
            'operations' => $count,
        ];
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

    private function map1CClientBankExchangeRow(array $row): array
    {
        $date = $this->parseBankReportDate($row['Дата'] ?? null);
        $number = $row['Номер'] ?? '';
        $operationId = ($date ?: 'no-date').'_'.$number;

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
}
