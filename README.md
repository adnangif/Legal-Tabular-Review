# LTR Demo (Laravel)

Minimal setup guide for running the LTR comparison/review demo locally.

## Prerequisites

- PHP 8.2+ with required extensions for Laravel (`mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `ctype`, `json`)
- Composer 2+
- A supported DB + PHP driver (default is MySQL + `pdo_mysql`; SQLite + `pdo_sqlite` also works if you update `.env`)

## Quick setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Then update DB settings in `.env` (`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

## Database bootstrap (migrations + seed)

```bash
php artisan migrate --seed
```

This creates all LTR tables and seeds a default user (`test@example.com`) via `DatabaseSeeder`.

## Run the app

```bash
php artisan serve
```

Open: `http://127.0.0.1:8000`

## Web UI

- Picker: `GET /ltr`
- Comparison table: `GET /ltr/table?document_ids[]=...&run_id=...`
- Reviews from the table: `POST /ltr/reviews`
- Citation chunk detail: `GET /ltr/citations/chunk/{chunk}`
- Web exports:
  - `GET /ltr/exports/comparison.csv`
  - `GET /ltr/exports/comparison.xlsx`
  - `GET /ltr/exports/wide.xlsx`

## API

Authenticated API endpoints are under `/api/v1/ltr/...`.

- Comparison data: `GET /api/v1/ltr/comparison?document_ids[]=...`
- Reviews: `POST /api/v1/ltr/reviews`
- API exports:
  - `GET /api/v1/ltr/exports/comparison.csv`
  - `GET /api/v1/ltr/exports/comparison.xlsx`
  - `GET /api/v1/ltr/exports/comparison-wide.xlsx`

## Key LTR tables/entities and relationships

- `ltr_documents`: source documents (file metadata, storage path).
- `ltr_document_chunks`: chunked text extracted from each document (`document_id` → `ltr_documents`).
- `ltr_field_templates`: canonical fields to extract (e.g., policy number, dates).
- `ltr_extraction_runs`: each model extraction execution (run metadata/status).
- `ltr_extracted_fields`: extracted value per **document + field + run**; can reference a citation chunk.
- `ltr_field_reviews`: human-reviewed final decision per **document + field** (current and history).
- `ltr_exports`: export tracking/audit records.

In short: **documents** are chunked, **runs** produce **extracted fields** against **field templates**, and reviewers finalize outcomes in **field reviews** that power comparison/export outputs.
