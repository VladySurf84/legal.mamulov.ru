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

        $legalColumns = [
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
        ];

        $legalRows = $this->fetchRows($remote, 'legal.legal', $legalColumns, ['legal_id']);
        $legalIdMap = [];

        foreach ($legalRows as &$row) {
            $oldLegalId = (int) $row['legal_id'];
            $newLegalId = (string) $row['legal_inn'];
            $legalIdMap[$oldLegalId] = $newLegalId;
            $row['legal_id'] = $newLegalId;
        }
        unset($row);

        $this->line(sprintf('legal.legal -> legal.legal_own: %d row(s)', count($legalRows)));

        $bankRows = $this->fetchRows($remote, 'legal.bank', ['bank_id', 'bank_name', 'api_provider_id'], ['bank_id']);
        $this->line(sprintf('legal.bank: %d row(s)', count($bankRows)));

        $bankAccountColumns = [
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
        ];
        $bankAccountRows = $this->fetchRows($remote, 'legal.bank_account', $bankAccountColumns, ['account_number', 'bank_id']);

        foreach ($bankAccountRows as &$row) {
            $row['legal_id'] = $legalIdMap[(int) $row['legal_id']] ?? (string) $row['legal_id'];
        }
        unset($row);

        $this->line(sprintf('legal.bank_account: %d row(s)', count($bankAccountRows)));

        if (! $this->option('dry-run')) {
            DB::table('legal.legal_own')->upsert(
                $legalRows,
                ['legal_id'],
                array_values(array_diff($legalColumns, ['legal_id']))
            );
            DB::table('legal.bank')->upsert(
                $bankRows,
                ['bank_id'],
                ['bank_name', 'api_provider_id']
            );
            DB::table('legal.bank_account')->upsert(
                $bankAccountRows,
                ['account_number', 'bank_id'],
                array_values(array_diff($bankAccountColumns, ['account_number', 'bank_id']))
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
