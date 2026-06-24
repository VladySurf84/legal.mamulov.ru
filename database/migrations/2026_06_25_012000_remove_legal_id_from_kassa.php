<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE legal.cash_entries ALTER COLUMN legal_id DROP NOT NULL');

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
    v_external_id text;
    v_title text;
    v_raw_payload jsonb;
    v_metadata jsonb;
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
        '{}'::jsonb,
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

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS kassa_sync_document_before_write ON legal.kassa;
CREATE TRIGGER kassa_sync_document_before_write
BEFORE INSERT OR UPDATE OF article_id, "time", amount, description, created
ON legal.kassa
FOR EACH ROW
EXECUTE FUNCTION legal.sync_kassa_document();
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.kassa
    DROP CONSTRAINT IF EXISTS kassa_legal_id_fkey,
    DROP CONSTRAINT IF EXISTS kassa_legal_id_format_check
SQL);

        DB::statement('DROP INDEX IF EXISTS legal.kassa_legal_id_time_idx');
        DB::statement('ALTER TABLE legal.kassa DROP COLUMN IF EXISTS legal_id');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE legal.kassa ADD COLUMN IF NOT EXISTS legal_id varchar(12)");
        DB::statement("UPDATE legal.kassa SET legal_id = '504025873801' WHERE legal_id IS NULL");
        DB::statement("UPDATE legal.cash_entries SET legal_id = '504025873801' WHERE legal_id IS NULL");
        DB::statement("ALTER TABLE legal.cash_entries ALTER COLUMN legal_id SET NOT NULL");
    }
};
