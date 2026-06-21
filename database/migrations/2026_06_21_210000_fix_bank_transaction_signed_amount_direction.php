<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::statement(<<<'SQL'
UPDATE legal.document_bank_transaction
SET signed_amount = CASE
        WHEN btrim(account_number) = btrim(COALESCE(recipient_account, '')) THEN ABS(COALESCE(amount, signed_amount, 0))
        WHEN btrim(account_number) = btrim(COALESCE(payer_account, '')) THEN -ABS(COALESCE(amount, signed_amount, 0))
        ELSE signed_amount
    END,
    updated_at = now()
WHERE account_number IS NOT NULL
SQL);

            DB::statement(<<<'SQL'
UPDATE legal.source_record_bank_details
SET signed_amount = CASE
        WHEN btrim(account_number) = btrim(COALESCE(
            (SELECT party.account_number
             FROM legal.source_record_parties party
             WHERE party.source_record_id = source_record_bank_details.source_record_id
                AND party.source_party_role = 'recipient'
             ORDER BY party.role_index
             LIMIT 1),
            ''
        )) THEN ABS(COALESCE(signed_amount, 0))
        WHEN btrim(account_number) = btrim(COALESCE(
            (SELECT party.account_number
             FROM legal.source_record_parties party
             WHERE party.source_record_id = source_record_bank_details.source_record_id
                AND party.source_party_role = 'payer'
             ORDER BY party.role_index
             LIMIT 1),
            ''
        )) THEN -ABS(COALESCE(signed_amount, 0))
        ELSE signed_amount
    END,
    updated_at = now()
WHERE account_number IS NOT NULL
SQL);
        });
    }

    public function down(): void
    {
        // The previous sign convention was incorrect: incoming payments were negative.
    }
};
