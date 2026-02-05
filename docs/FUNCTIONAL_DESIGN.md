# Functional Design

## User Flows

### 1. Document Ingestion & Extraction
*   **Actor**: System / Admin
*   **Action**: Uploads a set of PDF documents.
*   **System**:
    1.  Stores file.
    2.  Parses text into chunks.
    3.  Triggers `ExtractionRun` using configured AI model.
    4.  Populates `ExtractedField` table with initial findings.

### 2. Comparison Review (The "Grid")
*   **Actor**: Human Reviewer
*   **Action**: Navigates to the Comparison Table View.
*   **View**: Matrix of [Rows: Documents] x [Cols: Field Templates].
*   **Interaction**:
    -   Cells show specific extracted values (e.g., "Effective Date: 2023-01-01").
    -   Status indicators (Pending, Reviewed, Missing).
    -   Clicking a cell opens the **Review Pane**.

### 3. Field Review Decision
*   **Actor**: Human Reviewer
*   **Action**: Reviews a specific cell in the Comparison Table.
*   **Context**: System shows the extracted value and the source **Citation** (highlighted text chunk).
*   **Options**:
    -   **Accept**: Confirms the AI extraction is correct.
    -   **Reject**: Marks the extraction as incorrect (value becomes null or "missing").
    -   **Override**: Manually enters the correct value.
*   **Result**: A `FieldReview` record is saved, linked to the user. The cell status updates to "Reviewed".

### 4. Exporting Data
*   **Actor**: Analyst / User
*   **Action**: Clicks "Export to Excel/CSV".
*   **System**:
    -   Queries all active Documents.
    -   Resolves final values (If `FieldReview` exists, use that; else use `ExtractedField` or null).
    -   Generates a downloadable file.

## API Behaviors

### Core Endpoints

| Method | Endpoint | Purpose |
| :--- | :--- | :--- |
| `POST` | `/api/v1/ltr/runs/execute` | Triggers a new extraction run. Payload includes model config. |
| `GET` | `/api/v1/ltr/comparison` | Returns the matrix data: list of documents each containing a list of field results. |
| `GET` | `/api/v1/ltr/citations/chunks/{id}`| Returns the text text content for a specific chunk (used for evidence verification). |
| `POST` | `/api/v1/ltr/reviews` | Submits a review decision. Required: `document_id`, `field_template_id`, `review_status`. |
| `GET` | `/api/v1/ltr/exports/comparison.xlsx` | Streamed download of the current state of reviews. |

## Status Transitions

### Extraction Run Status
`created` → `running` → `completed` (or `failed`)
- Managed by `ExtractionRunExecutor` service.

### Fact/Field Status
1.  **Extracted**: Initial state after AI processing.
2.  **Pending**: Displayed to user if no `FieldReview` exists.
3.  **Accepted**: User confirmed the value.
4.  **Rejected**: User denied the value (treated as missing/blank).
5.  **Edited**: User provided a manual override.

## Edge Cases

-   **Re-extraction**: If a document is re-run, new `ExtractedField` records are created. The system must decide whether to invalidate previous reviews or attempt to map them (currently, reviews are often tied to specific extraction/document context).
-   **Missing Values**: AI may fail to find a value. These appear as empty/null in the extraction. Reviewers can then "Fill" them manually.
-   **Concurrent Reviews**: Optimistic locking is not strictly enforced, but last-write-wins is the general policy for the same field/document pair.
