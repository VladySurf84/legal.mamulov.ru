<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
INSERT INTO legal.document_types (
    code,
    name,
    document_group,
    is_primary,
    is_tax_document,
    is_money_document,
    is_inventory_document,
    is_contract_document,
    creates_accounting_events,
    creates_management_events,
    creates_tax_events,
    requires_parties,
    requires_lines,
    supports_corrections,
    supports_files,
    default_direction,
    metadata,
    is_active,
    created_at,
    updated_at
) VALUES (
    'manual_cash_operation',
    'Ручная денежная операция',
    'money',
    true,
    false,
    true,
    false,
    false,
    false,
    true,
    false,
    true,
    false,
    false,
    false,
    'internal',
    '{"source": "manual_kassa"}'::jsonb,
    true,
    now(),
    now()
)
ON CONFLICT (code) DO UPDATE SET
    name = EXCLUDED.name,
    document_group = EXCLUDED.document_group,
    is_primary = EXCLUDED.is_primary,
    is_money_document = EXCLUDED.is_money_document,
    creates_management_events = EXCLUDED.creates_management_events,
    requires_parties = EXCLUDED.requires_parties,
    supports_files = EXCLUDED.supports_files,
    default_direction = EXCLUDED.default_direction,
    metadata = legal.document_types.metadata || EXCLUDED.metadata,
    is_active = true,
    updated_at = now()
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.kassa
    ADD COLUMN IF NOT EXISTS legal_id varchar(12),
    ADD COLUMN IF NOT EXISTS document_id bigint
SQL);

        DB::statement("UPDATE legal.kassa SET legal_id = '504025873801' WHERE legal_id IS NULL");

        DB::statement(<<<'SQL'
ALTER TABLE legal.kassa
    ALTER COLUMN legal_id SET NOT NULL
SQL);

        DB::statement('DROP INDEX IF EXISTS legal.kassa_user_id_idx');
        DB::statement('ALTER TABLE legal.kassa DROP COLUMN IF EXISTS user_id');

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'kassa_legal_id_format_check'
            AND conrelid = 'legal.kassa'::regclass
    ) THEN
        ALTER TABLE legal.kassa
            ADD CONSTRAINT kassa_legal_id_format_check
            CHECK (legal_id ~ '^[0-9]{10,12}$');
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'kassa_legal_id_fkey'
            AND conrelid = 'legal.kassa'::regclass
    ) THEN
        ALTER TABLE legal.kassa
            ADD CONSTRAINT kassa_legal_id_fkey
            FOREIGN KEY (legal_id)
            REFERENCES legal.legal_own(legal_id)
            ON UPDATE CASCADE
            ON DELETE RESTRICT;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'kassa_document_id_key'
            AND conrelid = 'legal.kassa'::regclass
    ) THEN
        ALTER TABLE legal.kassa
            ADD CONSTRAINT kassa_document_id_key UNIQUE (document_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'kassa_document_id_fkey'
            AND conrelid = 'legal.kassa'::regclass
    ) THEN
        ALTER TABLE legal.kassa
            ADD CONSTRAINT kassa_document_id_fkey
            FOREIGN KEY (document_id)
            REFERENCES legal.documents(document_id)
            ON UPDATE CASCADE
            ON DELETE SET NULL;
    END IF;
END $$;
SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS kassa_legal_id_time_idx ON legal.kassa (legal_id, "time")');
        DB::statement('CREATE INDEX IF NOT EXISTS kassa_document_id_idx ON legal.kassa (document_id)');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION legal.sync_kassa_document()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    v_document_type_id smallint;
    v_source_record_id bigint;
    v_document_id bigint;
    v_article text;
    v_legal_name text;
    v_external_id text;
    v_title text;
    v_raw_payload jsonb;
    v_metadata jsonb;
    v_payer_role_id smallint;
    v_recipient_role_id smallint;
    v_own_role_id smallint;
    v_other_role_id smallint;
BEGIN
    SELECT document_type_id
    INTO v_document_type_id
    FROM legal.document_types
    WHERE code = 'manual_cash_operation';

    IF v_document_type_id IS NULL THEN
        RAISE EXCEPTION 'Document type manual_cash_operation was not found in legal.document_types';
    END IF;

    SELECT article
    INTO v_article
    FROM legal.kassa_article
    WHERE article_id = NEW.article_id;

    SELECT legal_name
    INTO v_legal_name
    FROM legal.legal_own
    WHERE legal_id = NEW.legal_id;

    IF v_legal_name IS NULL THEN
        RAISE EXCEPTION 'Own legal entity % was not found in legal.legal_own', NEW.legal_id;
    END IF;

    SELECT document_party_role_id
    INTO v_payer_role_id
    FROM legal.document_party_roles
    WHERE code = 'payer';

    SELECT document_party_role_id
    INTO v_recipient_role_id
    FROM legal.document_party_roles
    WHERE code = 'recipient';

    IF v_payer_role_id IS NULL OR v_recipient_role_id IS NULL THEN
        RAISE EXCEPTION 'Document party roles payer/recipient were not found';
    END IF;

    v_external_id := 'kassa:' || NEW.kassa_id::text;
    v_title := concat_ws(
        ' · ',
        'Ручная денежная операция',
        NULLIF(v_article, ''),
        NULLIF(left(NEW.description, 160), '')
    );
    v_raw_payload := jsonb_build_object(
        'kassa_id', NEW.kassa_id,
        'article_id', NEW.article_id,
        'article', v_article,
        'legal_id', NEW.legal_id,
        'time', NEW."time",
        'amount', NEW.amount,
        'description', NEW.description,
        'created', NEW.created
    );
    v_metadata := jsonb_build_object(
        'source', 'kassa',
        'kassa_id', NEW.kassa_id,
        'article_id', NEW.article_id,
        'article', v_article,
        'legal_id', NEW.legal_id,
        'description', NEW.description
    );

    INSERT INTO legal.source_records (
        source_system,
        source_channel,
        source_record_type,
        external_id,
        received_at,
        recorded_at,
        raw_payload,
        metadata,
        created_at,
        updated_at
    ) VALUES (
        'manual_kassa',
        'manual',
        'manual_cash_operation',
        v_external_id,
        NEW.created,
        NEW."time",
        v_raw_payload,
        jsonb_build_object('legal_id', NEW.legal_id),
        now(),
        now()
    )
    ON CONFLICT (source_system, source_record_type, external_id)
        WHERE external_id IS NOT NULL
    DO UPDATE SET
        received_at = EXCLUDED.received_at,
        recorded_at = EXCLUDED.recorded_at,
        raw_payload = EXCLUDED.raw_payload,
        metadata = EXCLUDED.metadata,
        updated_at = now()
    RETURNING source_record_id
    INTO v_source_record_id;

    INSERT INTO legal.documents (
        document_type_id,
        document_date,
        document_number,
        title,
        amount,
        currency,
        status,
        source_system,
        external_id,
        metadata,
        imported_at,
        created_at,
        updated_at
    ) VALUES (
        v_document_type_id,
        NEW."time"::date,
        NEW.kassa_id::text,
        v_title,
        NEW.amount::numeric(18,2),
        '643',
        'imported',
        'manual_kassa',
        v_external_id,
        v_metadata,
        COALESCE(NEW.created, now()),
        now(),
        now()
    )
    ON CONFLICT (document_type_id, source_system, external_id)
        WHERE source_system IS NOT NULL AND external_id IS NOT NULL
    DO UPDATE SET
        document_date = EXCLUDED.document_date,
        document_number = EXCLUDED.document_number,
        title = EXCLUDED.title,
        amount = EXCLUDED.amount,
        currency = EXCLUDED.currency,
        status = EXCLUDED.status,
        metadata = EXCLUDED.metadata,
        imported_at = EXCLUDED.imported_at,
        updated_at = now()
    RETURNING document_id
    INTO v_document_id;

    NEW.document_id := v_document_id;

    INSERT INTO legal.document_sources (
        document_id,
        source_record_id,
        source_item_key,
        source_role,
        confidence,
        matched_by,
        matched_at,
        metadata,
        created_at,
        updated_at
    ) VALUES (
        v_document_id,
        v_source_record_id,
        '',
        'manual_input',
        1,
        'kassa_trigger',
        now(),
        jsonb_build_object('kassa_id', NEW.kassa_id),
        now(),
        now()
    )
    ON CONFLICT (source_record_id, source_item_key)
    DO UPDATE SET
        document_id = EXCLUDED.document_id,
        source_role = EXCLUDED.source_role,
        confidence = EXCLUDED.confidence,
        matched_by = EXCLUDED.matched_by,
        matched_at = EXCLUDED.matched_at,
        metadata = EXCLUDED.metadata,
        updated_at = now();

    DELETE FROM legal.document_parties
    WHERE document_id = v_document_id
        AND metadata->>'source' = 'manual_kassa';

    IF NEW.amount < 0 THEN
        v_own_role_id := v_payer_role_id;
        v_other_role_id := v_recipient_role_id;
    ELSE
        v_own_role_id := v_recipient_role_id;
        v_other_role_id := v_payer_role_id;
    END IF;

    INSERT INTO legal.document_parties (
        document_id,
        document_party_role_id,
        role_index,
        name_snapshot,
        inn_snapshot,
        metadata,
        created_at,
        updated_at
    ) VALUES (
        v_document_id,
        v_own_role_id,
        1,
        v_legal_name,
        NEW.legal_id,
        jsonb_build_object('source', 'manual_kassa', 'party_kind', 'own_legal'),
        now(),
        now()
    );

    INSERT INTO legal.document_parties (
        document_id,
        document_party_role_id,
        role_index,
        name_snapshot,
        metadata,
        created_at,
        updated_at
    ) VALUES (
        v_document_id,
        v_other_role_id,
        1,
        COALESCE(NULLIF(v_article, ''), 'Ручной ввод кассы'),
        jsonb_build_object('source', 'manual_kassa', 'party_kind', 'manual_counterparty', 'article_id', NEW.article_id),
        now(),
        now()
    );

    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION legal.delete_kassa_document()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.document_id IS NOT NULL THEN
        DELETE FROM legal.documents
        WHERE document_id = OLD.document_id
            AND source_system = 'manual_kassa';
    END IF;

    DELETE FROM legal.source_records
    WHERE source_system = 'manual_kassa'
        AND source_record_type = 'manual_cash_operation'
        AND external_id = 'kassa:' || OLD.kassa_id::text;

    RETURN OLD;
END;
$$;

DROP TRIGGER IF EXISTS kassa_sync_document_before_write ON legal.kassa;
CREATE TRIGGER kassa_sync_document_before_write
BEFORE INSERT OR UPDATE OF article_id, legal_id, "time", amount, description, created
ON legal.kassa
FOR EACH ROW
EXECUTE FUNCTION legal.sync_kassa_document();

DROP TRIGGER IF EXISTS kassa_delete_document_after_delete ON legal.kassa;
CREATE TRIGGER kassa_delete_document_after_delete
AFTER DELETE
ON legal.kassa
FOR EACH ROW
EXECUTE FUNCTION legal.delete_kassa_document();
SQL);

        DB::statement('UPDATE legal.kassa SET legal_id = legal_id');
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS kassa_delete_document_after_delete ON legal.kassa;
DROP TRIGGER IF EXISTS kassa_sync_document_before_write ON legal.kassa;
DROP FUNCTION IF EXISTS legal.delete_kassa_document();
DROP FUNCTION IF EXISTS legal.sync_kassa_document();

DELETE FROM legal.documents
WHERE source_system = 'manual_kassa';

DELETE FROM legal.source_records
WHERE source_system = 'manual_kassa'
    AND source_record_type = 'manual_cash_operation';

DELETE FROM legal.document_types
WHERE code = 'manual_cash_operation';
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.kassa
    DROP CONSTRAINT IF EXISTS kassa_document_id_fkey,
    DROP CONSTRAINT IF EXISTS kassa_document_id_key,
    DROP CONSTRAINT IF EXISTS kassa_legal_id_fkey,
    DROP CONSTRAINT IF EXISTS kassa_legal_id_format_check
SQL);

        DB::statement('DROP INDEX IF EXISTS legal.kassa_document_id_idx');
        DB::statement('DROP INDEX IF EXISTS legal.kassa_legal_id_time_idx');

        DB::statement('ALTER TABLE legal.kassa ADD COLUMN IF NOT EXISTS user_id integer DEFAULT 0 NOT NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS kassa_user_id_idx ON legal.kassa (user_id, "time")');

        DB::statement(<<<'SQL'
ALTER TABLE legal.kassa
    DROP COLUMN IF EXISTS document_id,
    DROP COLUMN IF EXISTS legal_id
SQL);
    }
};
