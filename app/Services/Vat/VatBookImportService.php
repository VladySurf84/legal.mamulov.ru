<?php

namespace App\Services\Vat;

use App\Services\Layers\AccountantReportLinkBuilder;
use App\Services\Layers\VatLayerBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;

class VatBookImportService
{
    public function __construct(
        private readonly VatLayerBuilder $vatLayerBuilder,
        private readonly AccountantReportLinkBuilder $accountantReportLinkBuilder,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function importFile(string|UploadedFile $file, ?string $sourceFileName = null): array
    {
        $path = $file instanceof UploadedFile ? (string) $file->getRealPath() : $file;
        $sourceFileName ??= $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($path);

        if (! is_file($path)) {
            throw new RuntimeException("VAT book file '{$path}' was not found.");
        }

        $hash = hash_file('sha256', $path);
        $size = filesize($path);
        $xml = $this->loadXml($path);
        $parsed = $this->parse($xml);

        $summary = DB::transaction(function () use ($path, $sourceFileName, $hash, $size, $xml, $parsed): array {
            $legalId = $this->legalIdByInn($parsed['owner_inn']);
            $storedPath = $this->storeOriginal($path, $sourceFileName, $hash, $parsed);
            $now = now();

            DB::table('legal.vat_book_imports')
                ->where('legal_id', $legalId)
                ->where('book_type', $parsed['book_type'])
                ->where('year', $parsed['year'])
                ->where('quarter', $parsed['quarter'])
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'updated_at' => $now,
                ]);

            DB::table('legal.vat_book_imports')
                ->where('file_sha256', $hash)
                ->delete();

            $import = DB::selectOne('
                INSERT INTO legal.vat_book_imports (
                    legal_id,
                    book_type,
                    year,
                    quarter,
                    period_code,
                    knd,
                    file_identifier,
                    source_file_name,
                    stored_path,
                    file_sha256,
                    file_size,
                    xml_version,
                    program_version,
                    total_amount,
                    total_amount_without_vat,
                    total_vat_amount,
                    entries_count,
                    is_active,
                    imported_at,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING vat_book_import_id
            ', [
                $legalId,
                $parsed['book_type'],
                $parsed['year'],
                $parsed['quarter'],
                $parsed['period_code'],
                $this->string($xml->Документ?->attributes()?->КНД ?? null),
                $this->string($xml->attributes()?->ИдФайл ?? null),
                $sourceFileName,
                $storedPath,
                $hash,
                $size,
                $this->string($xml->attributes()?->ВерсФорм ?? null),
                $this->string($xml->attributes()?->ВерсПрог ?? null),
                $parsed['totals']['amount_total'],
                $parsed['totals']['amount_without_vat'],
                $parsed['totals']['vat_amount'],
                count($parsed['entries']),
                true,
                $now,
                $now,
                $now,
            ]);

            $importId = (int) $import->vat_book_import_id;
            $this->insertEntries($importId, $legalId, $parsed['book_type'], $parsed['year'], $parsed['quarter'], $parsed['period_code'], $parsed['entries']);

            return [
                'vat_book_import_id' => $importId,
                'legal_id' => $legalId,
                'book_type' => $parsed['book_type'],
                'year' => $parsed['year'],
                'quarter' => $parsed['quarter'],
                'period_code' => $parsed['period_code'],
                'entries_count' => count($parsed['entries']),
                'source_file_name' => $sourceFileName,
                'stored_path' => $storedPath,
            ];
        });

        $summary['vat_events_count'] = $this->vatLayerBuilder->rebuild();
        $summary['accountant_report_link_stats'] = $this->accountantReportLinkBuilder->rebuild([
            'legal_id' => $summary['legal_id'],
            'year' => $summary['year'],
            'quarter' => $summary['quarter'],
        ]);

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function parse(SimpleXMLElement $xml): array
    {
        $document = $xml->Документ;

        if (! $document instanceof SimpleXMLElement) {
            throw new RuntimeException('В XML отсутствует узел Документ.');
        }

        if (isset($document->СвКнПок)) {
            return $this->parsePurchaseBook($document);
        }

        if (isset($document->СвКнПрод)) {
            return $this->parseSalesBook($document);
        }

        throw new RuntimeException('XML не похож на книгу покупок или книгу продаж.');
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePurchaseBook(SimpleXMLElement $document): array
    {
        $header = $document->СвКнПок;
        $periodCode = (int) $this->requiredAttr($header, 'Период', 'СвКнПок@Период');
        $year = (int) $this->requiredAttr($header, 'ОтчетГод', 'СвКнПок@ОтчетГод');
        $entries = [];

        foreach ($document->СвПокупка as $row) {
            $rowNumber = (int) $this->requiredAttr($row, 'НомПП', 'СвПокупка@НомПП');
            $entries[] = [
                'row_number' => $rowNumber,
                'operation_code' => $this->nodeText($row->КодВидОпер ?? null),
                'invoice_number' => $this->attr($row, 'НомерСчФ'),
                'invoice_date' => $this->date($this->attr($row, 'ДатаСчФ')),
                'correction_invoice_number' => $this->attr($row, 'НомерКСчФ'),
                'correction_invoice_date' => $this->date($this->attr($row, 'ДатаКСчФ')),
                'acceptance_date' => $this->date($this->nodeText($row->ДатаПринУчет ?? null)),
                'payment_doc_number' => $this->attr($row->ДокПдтвУпл ?? null, 'НомДокПдтвУпл'),
                'payment_doc_date' => $this->date($this->attr($row->ДокПдтвУпл ?? null, 'ДатаДокПдтвУпл')),
                'contractor_name' => $this->attr($row, 'НаимПрод'),
                'contractor_inn' => $this->attr($row, 'ИННЮЛ') ?? $this->attr($row, 'ИННФЛ'),
                'contractor_kpp' => $this->attr($row, 'КПП'),
                'currency_code' => $this->attr($row, 'КодОКВ'),
                'amount_total' => $this->decimal($this->attr($row, 'СтПокСчФВал')),
                'amount_without_vat' => null,
                'vat_amount' => $this->decimal($this->firstNestedValue($row->СумНДСВыч ?? null, ['СумНДС'])),
                'raw_entry' => $this->xmlElementToArray($row),
            ];
        }

        return [
            'book_type' => 'purchase',
            'owner_inn' => $this->ownerInn($document->СвПокуп ?? null),
            'year' => $year,
            'quarter' => $this->quarterFromPeriodCode($periodCode),
            'period_code' => $periodCode,
            'totals' => [
                'amount_total' => null,
                'amount_without_vat' => null,
                'vat_amount' => $this->decimal($this->attr($header->Всего ?? null, 'СумНДСВыч')),
            ],
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSalesBook(SimpleXMLElement $document): array
    {
        $header = $document->СвКнПрод;
        $periodCode = (int) $this->requiredAttr($header, 'Период', 'СвКнПрод@Период');
        $year = (int) $this->requiredAttr($header, 'ОтчетГод', 'СвКнПрод@ОтчетГод');
        $entries = [];

        foreach ($document->СвПродаж as $row) {
            $rowNumber = (int) $this->requiredAttr($row, 'НомПП', 'СвПродаж@НомПП');
            $invoiceNumber = $this->attr($row, 'НомерКСчФ') ?? $this->attr($row, 'НомерСчФ');
            $invoiceDate = $this->attr($row, 'ДатаКСчФ') ?? $this->attr($row, 'ДатаСчФ');

            $entries[] = [
                'row_number' => $rowNumber,
                'operation_code' => $this->nodeText($row->КодВидОпер ?? null),
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $this->date($invoiceDate),
                'correction_invoice_number' => $this->attr($row, 'НомерКСчФ') !== null ? $this->attr($row, 'НомерСчФ') : null,
                'correction_invoice_date' => $this->attr($row, 'ДатаКСчФ') !== null ? $this->date($this->attr($row, 'ДатаСчФ')) : null,
                'acceptance_date' => $this->date($invoiceDate),
                'payment_doc_number' => null,
                'payment_doc_date' => null,
                'contractor_name' => $this->attr($row, 'НаимПок'),
                'contractor_inn' => $this->attr($row, 'ИННЮЛ') ?? $this->attr($row, 'ИННФЛ'),
                'contractor_kpp' => $this->attr($row, 'КПП'),
                'currency_code' => $this->attr($row, 'КодОКВ'),
                'amount_total' => $this->decimal($this->attr($row, 'СтТовУчНалРубКоп')),
                'amount_without_vat' => $this->decimal($this->firstAttrValue($row, ['СтТовРубКоп20', 'СтТовРубКоп22', 'СтТовРубКоп5'])),
                'vat_amount' => $this->decimal($this->firstAttrValue($row, ['СумНДСРубКоп20', 'СумНДСРубКоп22', 'СумНДСРубКоп5'])),
                'raw_entry' => $this->xmlElementToArray($row),
            ];
        }

        return [
            'book_type' => 'sales',
            'owner_inn' => $this->ownerInn($document->СвПродав ?? null),
            'year' => $year,
            'quarter' => $this->quarterFromPeriodCode($periodCode),
            'period_code' => $periodCode,
            'totals' => [
                'amount_total' => $this->decimal($this->attr($header->Всего ?? null, 'СтТовУчНалРубКоп')),
                'amount_without_vat' => $this->decimal($this->firstAttrValue($header->Всего ?? null, ['СтТовРубКоп20', 'СтТовРубКоп22', 'СтТовРубКоп5'])),
                'vat_amount' => $this->decimal($this->firstAttrValue($header->Всего ?? null, ['СумНДСРубКоп20', 'СумНДСРубКоп22', 'СумНДСРубКоп5'])),
            ],
            'entries' => $entries,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function insertEntries(int $importId, int $legalId, string $bookType, int $year, int $quarter, int $periodCode, array $entries): void
    {
        $now = now();

        foreach (array_chunk($entries, 500) as $chunk) {
            $rows = [];

            foreach ($chunk as $entry) {
                $rows[] = [
                    'vat_book_import_id' => $importId,
                    'legal_id' => $legalId,
                    'book_type' => $bookType,
                    'year' => $year,
                    'quarter' => $quarter,
                    'period_code' => $periodCode,
                    'row_number' => $entry['row_number'],
                    'operation_code' => $entry['operation_code'],
                    'invoice_number' => $entry['invoice_number'],
                    'invoice_date' => $entry['invoice_date'],
                    'correction_invoice_number' => $entry['correction_invoice_number'],
                    'correction_invoice_date' => $entry['correction_invoice_date'],
                    'acceptance_date' => $entry['acceptance_date'],
                    'payment_doc_number' => $entry['payment_doc_number'],
                    'payment_doc_date' => $entry['payment_doc_date'],
                    'contractor_name' => $entry['contractor_name'],
                    'contractor_inn' => $entry['contractor_inn'],
                    'contractor_kpp' => $entry['contractor_kpp'],
                    'currency_code' => $entry['currency_code'],
                    'amount_total' => $entry['amount_total'],
                    'amount_without_vat' => $entry['amount_without_vat'],
                    'vat_amount' => $entry['vat_amount'],
                    'raw_entry' => json_encode($entry['raw_entry'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('legal.vat_book_entries')->insert($rows);
        }
    }

    private function loadXml(string $path): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $xml = simplexml_load_file($path);

        if ($xml === false) {
            $message = collect(libxml_get_errors())
                ->map(fn (\LibXMLError $error): string => trim($error->message))
                ->filter()
                ->implode('; ');

            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            throw new RuntimeException($message !== '' ? $message : 'Не удалось прочитать XML.');
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml;
    }

    private function storeOriginal(string $path, string $sourceFileName, string $hash, array $parsed): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $sourceFileName) ?: 'vat-book.xml';
        $storedPath = sprintf(
            'legal/vat-books/%d/q%d/%s/%s_%s',
            $parsed['year'],
            $parsed['quarter'],
            $parsed['book_type'],
            substr($hash, 0, 12),
            $safeName,
        );

        Storage::disk('local')->put($storedPath, file_get_contents($path));

        return $storedPath;
    }

    private function ownerInn(?SimpleXMLElement $node): string
    {
        $inn = $this->attr($node?->СведИП ?? null, 'ИННФЛ')
            ?? $this->attr($node?->СведЮЛ ?? null, 'ИННЮЛ');

        if ($inn === null) {
            throw new RuntimeException('В XML не найден ИНН нашего юрлица.');
        }

        return $inn;
    }

    private function legalIdByInn(string $inn): int
    {
        $legalId = DB::table('legal.legal')
            ->where('legal_inn', $inn)
            ->value('legal_id');

        if ($legalId === null) {
            throw new RuntimeException("ИНН {$inn} не найден в legal.legal.");
        }

        return (int) $legalId;
    }

    private function quarterFromPeriodCode(int $periodCode): int
    {
        return match ($periodCode) {
            21 => 1,
            22 => 2,
            23 => 3,
            24 => 4,
            default => throw new RuntimeException("Неподдерживаемый код периода {$periodCode}."),
        };
    }

    private function requiredAttr(?SimpleXMLElement $node, string $name, string $label): string
    {
        return $this->attr($node, $name) ?? throw new RuntimeException("В XML отсутствует {$label}.");
    }

    private function attr(?SimpleXMLElement $node, string $name): ?string
    {
        if (! $node instanceof SimpleXMLElement) {
            return null;
        }

        $attributes = $node->attributes();

        if (! isset($attributes[$name])) {
            return null;
        }

        return $this->string($attributes[$name]);
    }

    private function nodeText(?SimpleXMLElement $node): ?string
    {
        return $node instanceof SimpleXMLElement ? $this->string($node) : null;
    }

    private function string(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function decimal(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return str_replace(',', '.', str_replace(' ', '', $value));
    }

    private function date(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Carbon::createFromFormat('d.m.Y', $value)->toDateString();
    }

    /**
     * @param  array<int, string>  $names
     */
    private function firstAttrValue(?SimpleXMLElement $node, array $names): ?string
    {
        foreach ($names as $name) {
            $value = $this->attr($node, $name);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $names
     */
    private function firstNestedValue(?SimpleXMLElement $node, array $names): ?string
    {
        if (! $node instanceof SimpleXMLElement) {
            return null;
        }

        foreach ($names as $name) {
            if (isset($node->{$name})) {
                return $this->nodeText($node->{$name});
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function xmlElementToArray(SimpleXMLElement $element): array
    {
        return json_decode(
            json_encode($element, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
