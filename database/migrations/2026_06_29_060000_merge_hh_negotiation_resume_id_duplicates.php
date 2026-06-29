<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
DO $$
DECLARE
    group_record record;
    target_id bigint;
    target_resume_id text;
    source_record record;
BEGIN
    FOR group_record IN
        WITH keyed AS (
            SELECT
                hh_negotiation_id,
                hh_vacancy_id,
                COALESCE(
                    (regexp_match(alternate_url, '/resume/([A-Za-z0-9]+)'))[1],
                    (regexp_match(resume_url, '/resume/([A-Za-z0-9]+)'))[1],
                    (regexp_match(resume_raw->>'alternate_url', '/resume/([A-Za-z0-9]+)'))[1],
                    (regexp_match(raw #>> '{resume,alternate_url}', '/resume/([A-Za-z0-9]+)'))[1],
                    (regexp_match(raw #>> '{resume,url}', '/resume/([A-Za-z0-9]+)'))[1],
                    CASE WHEN resume_id ~ '^[A-Za-z0-9]{30,}$' THEN resume_id END
                ) AS resume_url_key
            FROM legal.hh_negotiations
        )
        SELECT hh_vacancy_id, resume_url_key
        FROM keyed
        WHERE resume_url_key IS NOT NULL
        GROUP BY hh_vacancy_id, resume_url_key
        HAVING count(*) > 1
    LOOP
        SELECT hh_negotiation_id, resume_id
        INTO target_id, target_resume_id
        FROM legal.hh_negotiations
        WHERE hh_vacancy_id = group_record.hh_vacancy_id
          AND COALESCE(
                (regexp_match(alternate_url, '/resume/([A-Za-z0-9]+)'))[1],
                (regexp_match(resume_url, '/resume/([A-Za-z0-9]+)'))[1],
                (regexp_match(resume_raw->>'alternate_url', '/resume/([A-Za-z0-9]+)'))[1],
                (regexp_match(raw #>> '{resume,alternate_url}', '/resume/([A-Za-z0-9]+)'))[1],
                (regexp_match(raw #>> '{resume,url}', '/resume/([A-Za-z0-9]+)'))[1],
                CASE WHEN resume_id ~ '^[A-Za-z0-9]{30,}$' THEN resume_id END
          ) = group_record.resume_url_key
        ORDER BY
            CASE WHEN resume_id ~ '^[0-9]+$' THEN 0 ELSE 1 END,
            (pdf_path IS NULL),
            COALESCE(codex_analysis_score, analysis_score, 0) DESC,
            hh_negotiation_id DESC
        LIMIT 1;

        FOR source_record IN
            SELECT *
            FROM legal.hh_negotiations
            WHERE hh_vacancy_id = group_record.hh_vacancy_id
              AND hh_negotiation_id <> target_id
              AND COALESCE(
                    (regexp_match(alternate_url, '/resume/([A-Za-z0-9]+)'))[1],
                    (regexp_match(resume_url, '/resume/([A-Za-z0-9]+)'))[1],
                    (regexp_match(resume_raw->>'alternate_url', '/resume/([A-Za-z0-9]+)'))[1],
                    (regexp_match(raw #>> '{resume,alternate_url}', '/resume/([A-Za-z0-9]+)'))[1],
                    (regexp_match(raw #>> '{resume,url}', '/resume/([A-Za-z0-9]+)'))[1],
                    CASE WHEN resume_id ~ '^[A-Za-z0-9]{30,}$' THEN resume_id END
              ) = group_record.resume_url_key
            ORDER BY hh_negotiation_id
        LOOP
            UPDATE legal.hh_negotiations AS target
            SET
                hh_id = COALESCE(target.hh_id, source_record.hh_id),
                candidate_name = COALESCE(NULLIF(target.candidate_name, ''), source_record.candidate_name),
                resume_title = COALESCE(NULLIF(target.resume_title, ''), source_record.resume_title),
                area_name = COALESCE(NULLIF(target.area_name, ''), source_record.area_name),
                status_id = COALESCE(NULLIF(target.status_id, ''), source_record.status_id),
                status_name = COALESCE(NULLIF(target.status_name, ''), source_record.status_name),
                salary_text = COALESCE(NULLIF(target.salary_text, ''), source_record.salary_text),
                alternate_url = COALESCE(NULLIF(target.alternate_url, ''), source_record.alternate_url),
                resume_url = COALESCE(NULLIF(target.resume_url, ''), source_record.resume_url),
                pdf_url = COALESCE(NULLIF(target.pdf_url, ''), source_record.pdf_url),
                pdf_path = COALESCE(NULLIF(target.pdf_path, ''), source_record.pdf_path),
                responded_at = COALESCE(target.responded_at, source_record.responded_at),
                updated_at_hh = COALESCE(GREATEST(target.updated_at_hh, source_record.updated_at_hh), target.updated_at_hh, source_record.updated_at_hh),
                downloaded_at = COALESCE(GREATEST(target.downloaded_at, source_record.downloaded_at), target.downloaded_at, source_record.downloaded_at),
                analyzed_at = COALESCE(GREATEST(target.analyzed_at, source_record.analyzed_at), target.analyzed_at, source_record.analyzed_at),
                codex_analyzed_at = COALESCE(GREATEST(target.codex_analyzed_at, source_record.codex_analyzed_at), target.codex_analyzed_at, source_record.codex_analyzed_at),
                analysis_score = COALESCE(GREATEST(target.analysis_score, source_record.analysis_score), target.analysis_score, source_record.analysis_score),
                codex_analysis_score = COALESCE(GREATEST(target.codex_analysis_score, source_record.codex_analysis_score), target.codex_analysis_score, source_record.codex_analysis_score),
                analysis_summary = CASE
                    WHEN target.analysis_summary IS NULL OR btrim(target.analysis_summary) = '' THEN source_record.analysis_summary
                    WHEN source_record.analysis_summary IS NULL OR btrim(source_record.analysis_summary) = '' OR target.analysis_summary = source_record.analysis_summary THEN target.analysis_summary
                    ELSE target.analysis_summary || E'\n\nСклеено из дубля #' || source_record.hh_negotiation_id || E'\n' || source_record.analysis_summary
                END,
                codex_analysis_summary = CASE
                    WHEN target.codex_analysis_summary IS NULL OR btrim(target.codex_analysis_summary) = '' THEN source_record.codex_analysis_summary
                    WHEN source_record.codex_analysis_summary IS NULL OR btrim(source_record.codex_analysis_summary) = '' OR target.codex_analysis_summary = source_record.codex_analysis_summary THEN target.codex_analysis_summary
                    ELSE target.codex_analysis_summary || E'\n\nСклеено из дубля #' || source_record.hh_negotiation_id || E'\n' || source_record.codex_analysis_summary
                END,
                analysis_flags = COALESCE(target.analysis_flags, '{}'::jsonb) || COALESCE(source_record.analysis_flags, '{}'::jsonb),
                codex_analysis_flags = COALESCE(target.codex_analysis_flags, '{}'::jsonb) || COALESCE(source_record.codex_analysis_flags, '{}'::jsonb),
                raw = COALESCE(target.raw, '{}'::jsonb) || jsonb_build_object('merged_hh_negotiation_' || source_record.hh_negotiation_id, source_record.raw),
                resume_raw = COALESCE(target.resume_raw, '{}'::jsonb) || jsonb_build_object('merged_hh_negotiation_' || source_record.hh_negotiation_id, source_record.resume_raw),
                updated_at = now()
            WHERE target.hh_negotiation_id = target_id;

            UPDATE legal.hh_browser_captures
            SET resume_id = target_resume_id,
                updated_at = now()
            WHERE hh_vacancy_id = group_record.hh_vacancy_id
              AND (
                  resume_id = source_record.resume_id
                  OR COALESCE(
                        (regexp_match(original_url, '/resume/([A-Za-z0-9]+)'))[1],
                        (regexp_match(candidate_resume_url, '/resume/([A-Za-z0-9]+)'))[1],
                        (regexp_match(page_url, '/resume/([A-Za-z0-9]+)'))[1],
                        (regexp_match(resume_structured->>'originalUrl', '/resume/([A-Za-z0-9]+)'))[1],
                        (regexp_match(payload #>> '{candidate,resumeUrl}', '/resume/([A-Za-z0-9]+)'))[1],
                        (regexp_match(payload #>> '{page,originalUrl}', '/resume/([A-Za-z0-9]+)'))[1],
                        (regexp_match(payload #>> '{page,url}', '/resume/([A-Za-z0-9]+)'))[1],
                        CASE WHEN resume_id ~ '^[A-Za-z0-9]{30,}$' THEN resume_id END
                  ) = group_record.resume_url_key
              );

            DELETE FROM legal.hh_negotiations
            WHERE hh_negotiation_id = source_record.hh_negotiation_id;
        END LOOP;
    END LOOP;
END $$;
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'hh_negotiations_vacancy_resume_unique'
          AND conrelid = 'legal.hh_negotiations'::regclass
    ) THEN
        ALTER TABLE legal.hh_negotiations
            ADD CONSTRAINT hh_negotiations_vacancy_resume_unique UNIQUE (hh_vacancy_id, resume_id);
    END IF;
END $$;
SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS hh_browser_captures_vacancy_resume_idx ON legal.hh_browser_captures (hh_vacancy_id, resume_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS legal.hh_browser_captures_vacancy_resume_idx');
    }
};

