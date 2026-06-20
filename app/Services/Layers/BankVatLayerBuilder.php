<?php

namespace App\Services\Layers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BankVatLayerBuilder
{
    public const SOURCE_SYSTEM = 'bank_payment_vat';

    public function rebuild(): int
    {
        return DB::transaction(function (): int {
            DB::table('legal.vat_events')
                ->where('source_system', self::SOURCE_SYSTEM)
                ->delete();

            $transactions = DB::select(<<<'SQL'
SELECT
    dbt.*,
    ba.legal_id
FROM legal.document_bank_transaction dbt
JOIN legal.bank_account ba
    ON ba.bank_account_id = dbt.bank_account_id
WHERE dbt.payment_purpose IS NOT NULL
  AND btrim(dbt.payment_purpose) <> ''
  AND COALESCE(dbt.amount, dbt.signed_amount) IS NOT NULL
ORDER BY dbt.operation_date, dbt.document_bank_transaction_id
SQL);

            $rows = [];

            foreach ($transactions as $transaction) {
                $vat = $this->vatFromTransaction($transaction);

                if ($vat === null || $vat <= 0) {
                    continue;
                }

                $isIncoming = $this->sameAccount($transaction->account_number, $transaction->recipient_account);
                $grossAmount = abs((float) ($transaction->amount ?? $transaction->signed_amount ?? 0));
                $date = $transaction->operation_date !== null
                    ? Carbon::parse((string) $transaction->operation_date)
                    : null;

                $rows[] = [
                    'source_system' => self::SOURCE_SYSTEM,
                    'source_document_id' => (int) $transaction->document_id,
                    'source_document_bank_transaction_id' => (int) $transaction->document_bank_transaction_id,
                    'legal_id' => (int) $transaction->legal_id,
                    'year' => $date?->year ?? (int) now()->year,
                    'quarter' => $date?->quarter ?? (int) now()->quarter,
                    'book_type' => $isIncoming ? 'sales' : 'purchase',
                    'vat_direction' => $isIncoming ? 'output' : 'input',
                    'occurred_on' => $date?->toDateString(),
                    'invoice_number' => null,
                    'invoice_date' => null,
                    'contractor_name' => $isIncoming ? $transaction->payer_name : $transaction->recipient_name,
                    'contractor_inn' => $this->nullableString($isIncoming ? $transaction->payer_inn : $transaction->recipient_inn),
                    'contractor_kpp' => $this->nullableString($isIncoming ? $transaction->payer_kpp : $transaction->recipient_kpp),
                    'amount_total' => $grossAmount,
                    'amount_without_vat' => $grossAmount - $vat,
                    'vat_amount' => $vat,
                    'signed_vat_amount' => $isIncoming ? $vat : -$vat,
                    'operation_code' => null,
                    'algorithm' => 'bank_payment_purpose_v1',
                    'metadata' => json_encode([
                        'source' => 'document_bank_transaction',
                        'bank_account_id' => (int) $transaction->bank_account_id,
                        'bank_transaction_id' => $transaction->bank_transaction_id !== null ? (int) $transaction->bank_transaction_id : null,
                        'external_operation_id' => $transaction->external_operation_id,
                        'payment_purpose' => $transaction->payment_purpose,
                        'vat_detection' => $this->vatDetectionMetadata($transaction),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('legal.vat_events')->insert($chunk);
            }

            return count($rows);
        });
    }

    private function vatFromTransaction(object $transaction): ?float
    {
        $purpose = $this->normalizePurpose((string) $transaction->payment_purpose);

        if ($purpose === '' || $this->hasWithoutVatMarker($purpose)) {
            return null;
        }

        if (! preg_match('/ндс/ui', $purpose)) {
            return null;
        }

        $explicitVat = $this->explicitVatAmount($purpose);

        if ($explicitVat !== null) {
            return $explicitVat;
        }

        $rate = $this->vatRate($purpose);

        if ($rate === null) {
            return null;
        }

        $grossAmount = abs((float) ($transaction->amount ?? $transaction->signed_amount ?? 0));

        if ($grossAmount <= 0) {
            return null;
        }

        return round($grossAmount * $rate / (100 + $rate), 2);
    }

    private function explicitVatAmount(string $purpose): ?float
    {
        if (! preg_match_all(
            '/(?:сумма\s+)?ндс\s*(?:\(?\d{1,2}(?:[,.]\d{1,2})?\s*%\)?\s*)?(?:[:\-—–]\s*)?(\d[\d\s]*(?:[,.]\d{1,2}|-\d{2}))(?=\s*(?:руб|р\.?|\(|[.;,]|$))/ui',
            $purpose,
            $matches
        )) {
            return null;
        }

        foreach ($matches[1] as $match) {
            $amount = $this->parseAmount($match);

            if ($amount !== null && $amount > 0) {
                return $amount;
            }
        }

        return null;
    }

    private function vatRate(string $purpose): ?float
    {
        if (preg_match('/(?:ндс[^\d]{0,30}|)(20|18|10|7|5)(?:[,.]0{1,2})?\s*%/ui', $purpose, $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }

        return null;
    }

    private function vatDetectionMetadata(object $transaction): array
    {
        $purpose = $this->normalizePurpose((string) $transaction->payment_purpose);

        return [
            'explicit_amount' => $this->explicitVatAmount($purpose),
            'rate' => $this->vatRate($purpose),
            'has_without_vat_marker' => $this->hasWithoutVatMarker($purpose),
        ];
    }

    private function hasWithoutVatMarker(string $purpose): bool
    {
        return (bool) preg_match('/(?:без\s+ндс|без\s+налога\s*\(?\s*ндс\s*\)?|ндс\s+не\s+облагается|не\s+облагается\s+ндс)/ui', $purpose);
    }

    private function parseAmount(string $value): ?float
    {
        $value = trim($value);
        $value = str_replace(' ', '', $value);
        $value = preg_replace('/-(\d{2})$/', '.$1', $value) ?? $value;
        $value = str_replace(',', '.', $value);

        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function normalizePurpose(string $purpose): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $purpose) ?? $purpose));
    }

    private function sameAccount(?string $left, ?string $right): bool
    {
        return trim((string) $left) !== ''
            && trim((string) $left) === trim((string) $right);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
