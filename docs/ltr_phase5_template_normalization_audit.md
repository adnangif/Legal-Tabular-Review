# LTR Phase 5 — Template Normalization Audit

Scope inspected:
- `ltr_documents`
- `ltr_document_chunks`
- `ltr_field_templates`
- `ltr_extraction_runs`
- `ltr_extracted_fields`

## 1) Schema inventory from migrations

### `ltr_documents`
Columns:
- `id` (primary key)
- `uuid` (unique)
- `title` (nullable)
- `original_filename`
- `mime_type` (nullable)
- `storage_disk` (default `private`)
- `storage_path`
- `sha256` (nullable, length 64)
- `source` (default `upload`)
- `meta` (nullable JSON)
- `needs_ocr` (default `false`)
- `created_at`, `updated_at`

Indexes/constraints declared:
- Primary key on `id`
- Unique on `uuid`
- Index on `sha256`
- Index on `needs_ocr`

### `ltr_document_chunks`
Columns:
- `id` (primary key)
- `document_id` (FK -> `ltr_documents.id`, cascade delete)
- `chunk_uid`
- `page_number` (nullable unsigned int)
- `chunk_index` (default `0`)
- `text` (longText)
- `char_start` (nullable unsigned int)
- `char_end` (nullable unsigned int)
- `created_at`, `updated_at`

Indexes/constraints declared:
- Primary key on `id`
- Foreign key on `document_id`
- Unique composite on (`document_id`, `chunk_uid`)
- Index composite on (`document_id`, `page_number`)

### `ltr_field_templates`
Columns:
- `id` (primary key)
- `field_key` (unique)
- `key` (nullable; added later; backfilled from `field_key`)
- `label`
- `type` (default `string`)
- `expected_format` (nullable)
- `is_required` (default `false`)
- `allow_multiple` (default `false`)
- `normalization_rules` (nullable JSON)
- `extraction_hints` (nullable JSON)
- `sort_order` (default `0`)
- `created_at`, `updated_at`

Indexes/constraints declared:
- Primary key on `id`
- Unique on `field_key`
- Unique on `key` (named `ltr_field_templates_key_unique`)

### `ltr_extraction_runs`
Columns:
- `id` (primary key)
- `run_uid` (unique)
- `status` (default initially `queued`, later altered to `created` for mysql/pgsql)
- `model_name` (nullable)
- `prompt_version` (nullable)
- `started_at` (nullable timestamp)
- `completed_at` (nullable timestamp)
- `meta` (nullable JSON)
- `created_at`, `updated_at`

Indexes/constraints declared:
- Primary key on `id`
- Unique on `run_uid`

### `ltr_extracted_fields`
Columns:
- `id` (primary key)
- `document_id` (FK -> `ltr_documents.id`, cascade delete)
- `field_template_id` (FK -> `ltr_field_templates.id`, cascade delete)
- `extraction_run_id` (FK -> `ltr_extraction_runs.id`, cascade delete)
- `raw_value` (nullable longText)
- `normalized_value` (nullable JSON)
- `confidence` (nullable decimal 5,4)
- `citation_document_chunk_id` (nullable FK -> `ltr_document_chunks.id`, null on delete)
- `citation_page_number` (nullable unsigned int)
- `citation_quote` (nullable text)
- `evidence_spans` (nullable JSON)
- `status` (default `extracted`)
- `created_at`, `updated_at`

Indexes/constraints declared:
- Primary key on `id`
- Foreign keys: `document_id`, `field_template_id`, `extraction_run_id`, `citation_document_chunk_id`
- Unique composite (`document_id`, `field_template_id`, `extraction_run_id`) named `ltr_unique_extract`
- Index composite (`document_id`, `field_template_id`)

## 2) Model alignment snapshot

- `Document` fillable/casts include `needs_ocr`, matching the alter migration.
- `DocumentChunk` aligns with columns used by ingestion and citation.
- `FieldTemplate` includes both `key` and `field_key`, with mutators/accessor that keep them in sync.
- `ExtractionRun` model sets default status `created` in `creating` hook.
- `ExtractedField` fillable/casts align with table fields.

## 3) Mismatches relevant to Phase 5 checklist

1. **Stable key is not fully enforced at DB level**
   - `ltr_field_templates.key` is added as `nullable` and never migrated to `NOT NULL`.
   - This means “stable key required” is only partially guaranteed by app behavior, not strict schema.

2. **Dual-key columns increase drift risk**
   - Both `field_key` and `key` are unique and present.
   - App code frequently selects/filters by `field_key` while only exposing `key` in responses as an alias.
   - This is transitional but not a full normalization to `key` as canonical selector.

3. **Pattern/cell selection flow still uses IDs (and strict exists validation)**
   - Execute-run request requires `cells.*.field_template_id` with `exists:ltr_field_templates,id`.
   - There is no request path for selecting templates by `key`.
   - Therefore unknown keys cannot currently degrade to `status=missing`; unknown identifiers fail validation before processing.

4. **Run status default has migration/runtime divergence edge case**
   - Base migration default is `queued`, with follow-up raw SQL changing to `created` only for mysql/pgsql.
   - `ExtractionRun` model enforces `created` in `creating`, so runtime create is consistent, but raw DB defaults may differ by driver (notably sqlite test env).

## 4) Phase 5 readiness summary

- `FieldTemplate` **does have** a DB `key` column with unique index.
- Full normalization to “key is the canonical selector” is **not complete** in request validation and selection pathways.
- “Unknown keys => missing (not error)” behavior is **not currently achievable** where strict `exists` validation is applied.
