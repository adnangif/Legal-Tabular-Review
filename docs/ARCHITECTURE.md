# Architecture Design

## System Overview

Legal Tabular Review (LTR) is a Laravel 11 application designed to facilitate the comparison and review of structured data extracted from documents. It employs a service-oriented architecture, leveraging the power of Laravel's ecosystem (Sanctum for auth, Eloquent for ORM) and a modern frontend integration (Vue/Blade).

## Component Boundaries

### 1. Authentication Layer
- **Technology**: Laravel Sanctum.
- **Responsibility**: Manages API token issuance and validation.
- **Boundaries**: Protects `/api/v1/ltr/*` routes. Public routes are limited to `/auth/token` and web view read-only access where applicable (though predominantly authenticated).

### 2. LTR Domain (Core Logic)
- **Location**: `App\Models\Ltr`, `App\Services\Ltr`.
- **Responsibility**:
  - **Ingestion**: Handling document uploads and chunking text.
  - **Extraction**: Coordinating AI model runs (e.g., GPT) to extract structured fields.
  - **Normalization**: Standardizing extracted values against field templates.
  - **Review**: Managing the lifecycle of human verification (Accept/Reject/Override).

### 3. API & Web Interfaces
- **API**: `routes/api.php` (prefix `v1/ltr`). Serves JSON data for external integrations and the frontend SPA components.
- **Web**: `routes/web.php` (prefix `ltr`). Serves HTML views for the comparison table and other UI elements.

## Data Flow

1.  **Ingestion**:
    -   User uploads PDF/Document -> `Document` record created.
    -   System reads content -> Splits into `DocumentChunk` records.

2.  **Extraction**:
    -   `ExtractionRun` initiated (Model + Prompt Version).
    -   System iterates `FieldTemplate`s against `DocumentChunk`s.
    -   **Result**: `ExtractedField` records created (Raw Value).

3.  **Review Loop**:
    -   Reviewer fetches `Comparison Matrix` (Documents vs. Templates).
    -   Reviewer submits `FieldReview` (Decision: Accepted/Rejected/Edited).
    -   System updates `ExtractedField` status (e.g., `extracted` -> `accepted`).

4.  **Export**:
    -   User requests export.
    -   System aggregates `Documents` + `FieldReviews` (favoring manual decision over raw extraction).
    -   **Output**: CSV/XLSX file.

## Storage Schema

The system relies on a relational MySQL database.

| Table | Description | Key Relationships |
| :--- | :--- | :--- |
| `ltr_documents` | Stores document metadata and file paths. | `hasMany` Chunks, ExtractedFields |
| `ltr_document_chunks` | Text segments from documents for citation. | `belongsTo` Document |
| `ltr_field_templates` | Definitions of data points to extract (Schema). | `hasMany` ExtractedFields |
| `ltr_extraction_runs` | Logs of AI processing batches. | `hasMany` ExtractedFields |
| `ltr_extracted_fields` | The raw output of an extraction run. | `belongsTo` Doc, Run, Template |
| `ltr_field_reviews` | Human judgments on extracted fields. | `belongsTo` ExtractedField (optional), User |
