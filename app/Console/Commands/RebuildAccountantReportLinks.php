<?php

namespace App\Console\Commands;

use App\Services\Layers\AccountantReportLinkBuilder;
use Illuminate\Console\Command;

class RebuildAccountantReportLinks extends Command
{
    protected $signature = 'accountant-report-links:rebuild
        {--legal-id= : Limit rebuild to one legal entity}
        {--year= : Limit rebuild to one accountant report year}
        {--quarter= : Limit rebuild to one accountant report quarter}
        {--dry-run : Show matching statistics without writing links}';

    protected $description = 'Rebuild automatic links between accountant VAT book entries and bank transactions.';

    public function handle(AccountantReportLinkBuilder $builder): int
    {
        $filters = [
            'legal_id' => $this->integerOption('legal-id'),
            'year' => $this->integerOption('year'),
            'quarter' => $this->integerOption('quarter'),
        ];

        if ($filters['quarter'] !== null && ($filters['quarter'] < 1 || $filters['quarter'] > 4)) {
            $this->error('The --quarter option must be between 1 and 4.');

            return self::FAILURE;
        }

        $stats = $builder->rebuild($filters, (bool) $this->option('dry-run'));

        $this->info($this->option('dry-run')
            ? 'Accountant report link dry run complete.'
            : 'Accountant report links rebuilt.'
        );

        $this->table(
            ['Metric', 'Value'],
            [
                ['Candidates', $stats['candidates']],
                ['Entries with candidates', $stats['entries_with_candidates']],
                ['Ambiguous entries', $stats['ambiguous_entries']],
                ['Ambiguous transactions', $stats['ambiguous_transactions']],
                ['Matched', $stats['matched']],
                ['Sales pair entries matched', $stats['sales_pair_entries_matched']],
                ['Sales pair links inserted', $stats['sales_pair_links_inserted']],
                ['Inserted', $stats['inserted']],
            ]
        );

        return self::SUCCESS;
    }

    private function integerOption(string $name): ?int
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
