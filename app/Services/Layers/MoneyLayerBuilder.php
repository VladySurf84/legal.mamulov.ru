<?php

namespace App\Services\Layers;

use Illuminate\Support\Facades\DB;

class MoneyLayerBuilder
{
    public function rebuild(): int
    {
        return DB::transaction(function (): int {
            DB::table('legal.money_edges')->delete();

            $payerRoleId = $this->roleId('payer');
            $recipientRoleId = $this->roleId('recipient');

            DB::insert(<<<'SQL'
INSERT INTO legal.money_edges (
    source_document_id,
    source_document_bank_transaction_id,
    payer_document_party_id,
    recipient_document_party_id,
    occurred_on,
    amount,
    currency,
    payer_name_snapshot,
    payer_inn_snapshot,
    recipient_name_snapshot,
    recipient_inn_snapshot,
    payment_purpose,
    metadata,
    created_at,
    updated_at
)
SELECT
    dbt.document_id,
    dbt.document_bank_transaction_id,
    payer.document_party_id,
    recipient.document_party_id,
    dbt.operation_date,
    COALESCE(ABS(dbt.amount), ABS(dbt.signed_amount), 0),
    COALESCE(dbt.currency, 'RUB'),
    COALESCE(payer.name_snapshot, dbt.payer_name),
    COALESCE(payer.inn_snapshot, dbt.payer_inn),
    COALESCE(recipient.name_snapshot, dbt.recipient_name),
    COALESCE(recipient.inn_snapshot, dbt.recipient_inn),
    dbt.payment_purpose,
    jsonb_build_object(
        'source', 'document_bank_transaction',
        'bank_account_id', dbt.bank_account_id,
        'external_operation_id', dbt.external_operation_id
    ),
    now(),
    now()
FROM legal.document_bank_transaction dbt
LEFT JOIN legal.document_parties payer
    ON payer.document_id = dbt.document_id
    AND payer.document_party_role_id = ?
    AND payer.role_index = 1
LEFT JOIN legal.document_parties recipient
    ON recipient.document_id = dbt.document_id
    AND recipient.document_party_role_id = ?
    AND recipient.role_index = 1
SQL, [$payerRoleId, $recipientRoleId]);

            return DB::table('legal.money_edges')->count();
        });
    }

    private function roleId(string $code): int
    {
        return (int) DB::table('legal.document_party_roles')
            ->where('code', $code)
            ->value('document_party_role_id');
    }
}
