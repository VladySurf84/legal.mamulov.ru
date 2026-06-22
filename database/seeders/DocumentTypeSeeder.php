<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            ['code' => 'contract', 'name' => 'Договор', 'document_group' => 'contract', 'is_primary' => false, 'is_tax_document' => false, 'is_money_document' => false, 'is_inventory_document' => false, 'is_contract_document' => true, 'creates_accounting_events' => false, 'creates_management_events' => false, 'creates_tax_events' => false, 'requires_parties' => true, 'requires_lines' => false, 'supports_corrections' => true, 'supports_files' => true, 'default_direction' => null, 'metadata' => []],
            ['code' => 'additional_agreement', 'name' => 'Дополнительное соглашение', 'document_group' => 'contract', 'is_primary' => false, 'is_tax_document' => false, 'is_money_document' => false, 'is_inventory_document' => false, 'is_contract_document' => true, 'creates_accounting_events' => false, 'creates_management_events' => false, 'creates_tax_events' => false, 'requires_parties' => true, 'requires_lines' => false, 'supports_corrections' => true, 'supports_files' => true, 'default_direction' => null, 'metadata' => []],
            ['code' => 'invoice_for_payment', 'name' => 'Счет на оплату', 'document_group' => 'contract', 'is_primary' => false, 'is_tax_document' => false, 'is_money_document' => false, 'is_inventory_document' => false, 'is_contract_document' => false, 'creates_accounting_events' => false, 'creates_management_events' => true, 'creates_tax_events' => false, 'requires_parties' => true, 'requires_lines' => true, 'supports_corrections' => false, 'supports_files' => true, 'default_direction' => null, 'metadata' => []],
            ['code' => 'upd', 'name' => 'УПД', 'document_group' => 'primary', 'is_primary' => true, 'is_tax_document' => true, 'is_money_document' => false, 'is_inventory_document' => true, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => true, 'creates_tax_events' => true, 'requires_parties' => true, 'requires_lines' => true, 'supports_corrections' => true, 'supports_files' => true, 'default_direction' => null, 'metadata' => []],
            ['code' => 'correction_upd', 'name' => 'Корректировочный УПД', 'document_group' => 'primary', 'is_primary' => true, 'is_tax_document' => true, 'is_money_document' => false, 'is_inventory_document' => true, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => true, 'creates_tax_events' => true, 'requires_parties' => true, 'requires_lines' => true, 'supports_corrections' => true, 'supports_files' => true, 'default_direction' => null, 'metadata' => []],
            ['code' => 'act', 'name' => 'Акт', 'document_group' => 'primary', 'is_primary' => true, 'is_tax_document' => false, 'is_money_document' => false, 'is_inventory_document' => false, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => true, 'creates_tax_events' => false, 'requires_parties' => true, 'requires_lines' => true, 'supports_corrections' => true, 'supports_files' => true, 'default_direction' => null, 'metadata' => []],
            ['code' => 'correction_act', 'name' => 'Корректировочный акт', 'document_group' => 'primary', 'is_primary' => true, 'is_tax_document' => false, 'is_money_document' => false, 'is_inventory_document' => false, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => true, 'creates_tax_events' => false, 'requires_parties' => true, 'requires_lines' => true, 'supports_corrections' => true, 'supports_files' => true, 'default_direction' => null, 'metadata' => []],
            ['code' => 'vat_purchase_book', 'name' => 'Книга покупок', 'document_group' => 'tax', 'is_primary' => false, 'is_tax_document' => true, 'is_money_document' => false, 'is_inventory_document' => false, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => false, 'creates_tax_events' => true, 'requires_parties' => true, 'requires_lines' => true, 'supports_corrections' => false, 'supports_files' => true, 'default_direction' => 'incoming', 'metadata' => []],
            ['code' => 'vat_sales_book', 'name' => 'Книга продаж', 'document_group' => 'tax', 'is_primary' => false, 'is_tax_document' => true, 'is_money_document' => false, 'is_inventory_document' => false, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => false, 'creates_tax_events' => true, 'requires_parties' => true, 'requires_lines' => true, 'supports_corrections' => false, 'supports_files' => true, 'default_direction' => 'outgoing', 'metadata' => []],
            ['code' => 'bank_statement', 'name' => 'Банковская выписка', 'document_group' => 'money', 'is_primary' => false, 'is_tax_document' => false, 'is_money_document' => true, 'is_inventory_document' => false, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => true, 'creates_tax_events' => false, 'requires_parties' => true, 'requires_lines' => true, 'supports_corrections' => false, 'supports_files' => true, 'default_direction' => null, 'metadata' => []],
            ['code' => 'bank_operation', 'name' => 'Банковская операция', 'document_group' => 'money', 'is_primary' => false, 'is_tax_document' => false, 'is_money_document' => true, 'is_inventory_document' => false, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => true, 'creates_tax_events' => false, 'requires_parties' => true, 'requires_lines' => false, 'supports_corrections' => false, 'supports_files' => false, 'default_direction' => null, 'metadata' => []],
            ['code' => 'cash_operation', 'name' => 'Кассовая операция', 'document_group' => 'money', 'is_primary' => true, 'is_tax_document' => false, 'is_money_document' => true, 'is_inventory_document' => false, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => true, 'creates_tax_events' => false, 'requires_parties' => true, 'requires_lines' => false, 'supports_corrections' => true, 'supports_files' => true, 'default_direction' => null, 'metadata' => []],
            ['code' => 'marketplace_commissioner_report', 'name' => 'Отчет комиссионера маркетплейса', 'document_group' => 'marketplace', 'is_primary' => true, 'is_tax_document' => true, 'is_money_document' => false, 'is_inventory_document' => true, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => true, 'creates_tax_events' => true, 'requires_parties' => true, 'requires_lines' => true, 'supports_corrections' => true, 'supports_files' => true, 'default_direction' => null, 'metadata' => []],
            ['code' => 'marketplace_sales_report', 'name' => 'Отчет о продажах маркетплейса', 'document_group' => 'marketplace', 'is_primary' => false, 'is_tax_document' => false, 'is_money_document' => false, 'is_inventory_document' => true, 'is_contract_document' => false, 'creates_accounting_events' => false, 'creates_management_events' => true, 'creates_tax_events' => false, 'requires_parties' => true, 'requires_lines' => true, 'supports_corrections' => true, 'supports_files' => true, 'default_direction' => null, 'metadata' => []],
            ['code' => 'marketplace_payout_report', 'name' => 'Отчет о выплате маркетплейса', 'document_group' => 'marketplace', 'is_primary' => false, 'is_tax_document' => false, 'is_money_document' => true, 'is_inventory_document' => false, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => true, 'creates_tax_events' => false, 'requires_parties' => true, 'requires_lines' => true, 'supports_corrections' => false, 'supports_files' => true, 'default_direction' => null, 'metadata' => []],
            ['code' => 'production_order', 'name' => 'Производственное задание', 'document_group' => 'production', 'is_primary' => false, 'is_tax_document' => false, 'is_money_document' => false, 'is_inventory_document' => true, 'is_contract_document' => false, 'creates_accounting_events' => false, 'creates_management_events' => true, 'creates_tax_events' => false, 'requires_parties' => true, 'requires_lines' => true, 'supports_corrections' => true, 'supports_files' => true, 'default_direction' => null, 'metadata' => []],
            ['code' => 'inventory_transfer', 'name' => 'Перемещение ТМЦ', 'document_group' => 'inventory', 'is_primary' => true, 'is_tax_document' => false, 'is_money_document' => false, 'is_inventory_document' => true, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => true, 'creates_tax_events' => false, 'requires_parties' => true, 'requires_lines' => true, 'supports_corrections' => true, 'supports_files' => true, 'default_direction' => 'internal', 'metadata' => []],
            ['code' => 'inventory_write_off', 'name' => 'Списание ТМЦ', 'document_group' => 'inventory', 'is_primary' => true, 'is_tax_document' => false, 'is_money_document' => false, 'is_inventory_document' => true, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => true, 'creates_tax_events' => false, 'requires_parties' => true, 'requires_lines' => true, 'supports_corrections' => true, 'supports_files' => true, 'default_direction' => 'outgoing', 'metadata' => []],
            ['code' => 'stocktaking', 'name' => 'Инвентаризация', 'document_group' => 'inventory', 'is_primary' => true, 'is_tax_document' => false, 'is_money_document' => false, 'is_inventory_document' => true, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => true, 'creates_tax_events' => false, 'requires_parties' => true, 'requires_lines' => true, 'supports_corrections' => true, 'supports_files' => true, 'default_direction' => 'internal', 'metadata' => []],
            ['code' => 'tax_payment', 'name' => 'Уплата налога', 'document_group' => 'tax', 'is_primary' => true, 'is_tax_document' => true, 'is_money_document' => true, 'is_inventory_document' => false, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => true, 'creates_tax_events' => true, 'requires_parties' => true, 'requires_lines' => false, 'supports_corrections' => true, 'supports_files' => true, 'default_direction' => 'outgoing', 'metadata' => []],
            ['code' => 'tax_accrual', 'name' => 'Начисление налога', 'document_group' => 'tax', 'is_primary' => false, 'is_tax_document' => true, 'is_money_document' => false, 'is_inventory_document' => false, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => true, 'creates_tax_events' => true, 'requires_parties' => true, 'requires_lines' => false, 'supports_corrections' => true, 'supports_files' => false, 'default_direction' => null, 'metadata' => []],
            ['code' => 'manual_adjustment', 'name' => 'Ручная корректировка', 'document_group' => 'system', 'is_primary' => false, 'is_tax_document' => false, 'is_money_document' => false, 'is_inventory_document' => false, 'is_contract_document' => false, 'creates_accounting_events' => true, 'creates_management_events' => true, 'creates_tax_events' => true, 'requires_parties' => true, 'requires_lines' => false, 'supports_corrections' => true, 'supports_files' => false, 'default_direction' => null, 'metadata' => []],
            ['code' => 'manual_cash_operation', 'name' => 'Ручная денежная операция', 'document_group' => 'money', 'is_primary' => true, 'is_tax_document' => false, 'is_money_document' => true, 'is_inventory_document' => false, 'is_contract_document' => false, 'creates_accounting_events' => false, 'creates_management_events' => true, 'creates_tax_events' => false, 'requires_parties' => true, 'requires_lines' => false, 'supports_corrections' => false, 'supports_files' => false, 'default_direction' => 'internal', 'metadata' => ['source' => 'manual_kassa']],
        ];

        DB::table('legal.document_types')->upsert(
            array_map(fn (array $row) => [
                ...$row,
                'metadata' => json_encode($row['metadata'], JSON_UNESCAPED_UNICODE),
                'is_active' => $row['is_active'] ?? true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $rows),
            ['code'],
            [
                'name',
                'document_group',
                'is_primary',
                'is_tax_document',
                'is_money_document',
                'is_inventory_document',
                'is_contract_document',
                'creates_accounting_events',
                'creates_management_events',
                'creates_tax_events',
                'requires_parties',
                'requires_lines',
                'supports_corrections',
                'supports_files',
                'default_direction',
                'metadata',
                'is_active',
                'updated_at',
            ]
        );
    }
}
