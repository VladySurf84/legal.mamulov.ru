<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class ImportBankDirectories extends Command
{
    protected $signature = 'legacy:import-bank-directories
        {--dry-run : Only show source counts without writing local tables}';

    protected $description = 'Import legal entities, banks and bank accounts from the legacy PostgreSQL database.';

    public function handle(): int
    {
        $remote = $this->remoteConnection();
        $tables = [
            'legal.legal' => [
                'key' => ['legal_id'],
                'columns' => [
                    'legal_id',
                    'legal_name',
                    'legal_fullname',
                    'legal_letter',
                    'firstname',
                    'lastname',
                    'middlename',
                    'legal_color',
                    'tax_system',
                    'tax_rate',
                    'vat_rate',
                    'legal_inn',
                    'legal_ogrn',
                    'legal_comment',
                    'addr_index',
                    'addr_region_code',
                    'addr_region',
                    'addr_town',
                    'addr_street',
                    'addr_house',
                    'addr_flat',
                    'suz_conn_id',
                    'suz_oms_id',
                    'cert_cn',
                    'edo_light_id',
                    'cdek_client_id',
                    'cdek_client_secret',
                    'cdek_token',
                    'cdek_token_expired',
                    'dellin_session_id',
                    'tax_periods',
                ],
            ],
            'legal.bank' => [
                'key' => ['bank_id'],
                'columns' => ['bank_id', 'bank_name', 'api_provider_id'],
            ],
            'legal.bank_account' => [
                'key' => ['account_number', 'bank_id'],
                'columns' => [
                    'account_number',
                    'bank_id',
                    'legal_id',
                    'name',
                    'currency',
                    'account_type',
                    'activation_date',
                    'balance_otb',
                    'balance_authorized',
                    'balance_pending_payments',
                    'balance_pending_requisitions',
                ],
            ],
        ];

        foreach ($tables as $table => $meta) {
            $rows = $this->fetchRows($remote, $table, $meta['columns'], $meta['key']);
            $this->line(sprintf('%s: %d row(s)', $table, count($rows)));

            if ($this->option('dry-run') || $rows === []) {
                continue;
            }

            DB::table($table)->upsert(
                $rows,
                $meta['key'],
                array_values(array_diff($meta['columns'], $meta['key']))
            );
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. Local tables were not changed.');

            return self::SUCCESS;
        }

        $this->info('Bank directories imported.');

        return self::SUCCESS;
    }

    private function remoteConnection(): PDO
    {
        $config = config('legacy.pgsql');
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['sslmode']
        );

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * @param array<int, string> $columns
     * @param array<int, string> $orderBy
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(PDO $remote, string $table, array $columns, array $orderBy): array
    {
        $sql = sprintf(
            'select %s from %s order by %s',
            implode(', ', array_map(fn (string $column): string => sprintf('"%s"', $column), $columns)),
            $table,
            implode(', ', array_map(fn (string $column): string => sprintf('"%s"', $column), $orderBy))
        );

        return $remote->query($sql)->fetchAll();
    }
}
