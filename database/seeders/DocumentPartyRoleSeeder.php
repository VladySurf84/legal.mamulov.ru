<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentPartyRoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            ['code' => 'party', 'name' => 'Сторона', 'description' => 'Универсальная сторона документа, когда роль симметрична.', 'document_group' => null, 'is_system' => true, 'is_active' => true, 'sort_order' => 10, 'metadata' => []],
            ['code' => 'payer', 'name' => 'Плательщик', 'description' => 'Сторона, которая перечисляет деньги.', 'document_group' => 'money', 'is_system' => true, 'is_active' => true, 'sort_order' => 20, 'metadata' => []],
            ['code' => 'recipient', 'name' => 'Получатель', 'description' => 'Сторона, которая получает деньги.', 'document_group' => 'money', 'is_system' => true, 'is_active' => true, 'sort_order' => 30, 'metadata' => []],
            ['code' => 'seller', 'name' => 'Продавец / исполнитель', 'description' => 'Сторона, которая продает товар, работу или услугу.', 'document_group' => 'primary', 'is_system' => true, 'is_active' => true, 'sort_order' => 40, 'metadata' => []],
            ['code' => 'buyer', 'name' => 'Покупатель / заказчик', 'description' => 'Сторона, которая покупает товар, работу или услугу.', 'document_group' => 'primary', 'is_system' => true, 'is_active' => true, 'sort_order' => 50, 'metadata' => []],
            ['code' => 'supplier', 'name' => 'Поставщик', 'description' => 'Сторона, которая поставляет товар или материал.', 'document_group' => 'primary', 'is_system' => true, 'is_active' => true, 'sort_order' => 60, 'metadata' => []],
            ['code' => 'customer', 'name' => 'Заказчик', 'description' => 'Сторона, которая заказывает работу, услугу или поставку.', 'document_group' => 'primary', 'is_system' => true, 'is_active' => true, 'sort_order' => 70, 'metadata' => []],
            ['code' => 'shipper', 'name' => 'Грузоотправитель', 'description' => 'Сторона, отправляющая груз.', 'document_group' => 'primary', 'is_system' => true, 'is_active' => true, 'sort_order' => 80, 'metadata' => []],
            ['code' => 'consignee', 'name' => 'Грузополучатель', 'description' => 'Сторона, принимающая груз.', 'document_group' => 'primary', 'is_system' => true, 'is_active' => true, 'sort_order' => 90, 'metadata' => []],
            ['code' => 'carrier', 'name' => 'Перевозчик', 'description' => 'Сторона, которая перевозит груз.', 'document_group' => 'primary', 'is_system' => true, 'is_active' => true, 'sort_order' => 100, 'metadata' => []],
            ['code' => 'agent', 'name' => 'Агент', 'description' => 'Агент в агентской или посреднической схеме.', 'document_group' => 'agency', 'is_system' => true, 'is_active' => true, 'sort_order' => 110, 'metadata' => []],
            ['code' => 'principal', 'name' => 'Принципал', 'description' => 'Принципал в агентской или посреднической схеме.', 'document_group' => 'agency', 'is_system' => true, 'is_active' => true, 'sort_order' => 120, 'metadata' => []],
            ['code' => 'commissioner', 'name' => 'Комиссионер', 'description' => 'Комиссионер в комиссионной схеме.', 'document_group' => 'agency', 'is_system' => true, 'is_active' => true, 'sort_order' => 130, 'metadata' => []],
            ['code' => 'bank', 'name' => 'Банк', 'description' => 'Банк, участвующий в документе или операции.', 'document_group' => 'money', 'is_system' => true, 'is_active' => true, 'sort_order' => 140, 'metadata' => []],
            ['code' => 'tax_authority', 'name' => 'Налоговый орган', 'description' => 'Налоговая или иной фискальный орган.', 'document_group' => 'tax', 'is_system' => true, 'is_active' => true, 'sort_order' => 150, 'metadata' => []],
            ['code' => 'marketplace', 'name' => 'Маркетплейс', 'description' => 'Маркетплейс или площадка.', 'document_group' => 'marketplace', 'is_system' => true, 'is_active' => true, 'sort_order' => 160, 'metadata' => []],
            ['code' => 'issuer', 'name' => 'Составитель', 'description' => 'Сторона, которая выпустила или составила документ.', 'document_group' => null, 'is_system' => true, 'is_active' => true, 'sort_order' => 170, 'metadata' => []],
            ['code' => 'signer', 'name' => 'Подписант', 'description' => 'Физическое или юридическое лицо, подписавшее документ.', 'document_group' => null, 'is_system' => true, 'is_active' => true, 'sort_order' => 180, 'metadata' => []],
        ];

        DB::table('legal.document_party_roles')->upsert(
            array_map(fn (array $row) => [
                ...$row,
                'metadata' => json_encode($row['metadata'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ], $rows),
            ['code'],
            ['name', 'description', 'document_group', 'is_system', 'is_active', 'sort_order', 'metadata', 'updated_at']
        );
    }
}
