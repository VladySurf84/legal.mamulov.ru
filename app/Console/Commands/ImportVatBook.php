<?php

namespace App\Console\Commands;

use App\Services\Vat\VatBookImportService;
use Illuminate\Console\Command;

class ImportVatBook extends Command
{
    protected $signature = 'vat-books:import
        {files* : One or more purchase/sales book XML files}';

    protected $description = 'Import accountant VAT purchase/sales book XML files into legal.vat_book_* tables.';

    public function handle(VatBookImportService $service): int
    {
        foreach ($this->argument('files') as $file) {
            $summary = $service->importFile((string) $file);

            $this->info(sprintf(
                'Imported %s: %s %d Q%d, %d row(s), import #%d, %d VAT event(s), %d link(s)',
                $summary['source_file_name'],
                $summary['book_type'],
                $summary['year'],
                $summary['quarter'],
                $summary['entries_count'],
                $summary['vat_book_import_id'],
                $summary['vat_events_count'],
                $summary['accountant_report_link_stats']['inserted'],
            ));
        }

        return self::SUCCESS;
    }
}
