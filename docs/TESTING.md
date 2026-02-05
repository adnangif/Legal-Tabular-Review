# Testing & Evaluation

## Extraction Accuracy

Evaluating the performance of the AI models is critical. We use the human "Ground Truth" established during the review process.

### Methodology
1.  **Baseline**: Execute an `ExtractionRun`.
2.  **Review**: Human reviewers process a sample set of documents, creating `FieldReview` records.
3.  **Comparison**: Compare `ExtractedField.raw_value` against `FieldReview.final_value`.

### Metrics
-   **Precision**: % of "Extracted" values that were "Accepted" by humans.
-   **Recall**: Ability to find values that humans manually entered (where AI returned null).
-   **F1 Score**: Harmonic mean of Precision and Recall.

*Note: Accuracy metrics are calculated via offline analysis scripts querying the `ltr_field_reviews` table.*

## Coverage

The codebase is covered by PHPUnit tests located in `tests/`.

### Feature Tests (`tests/Feature/Ltr`)
Focus on integration and full API lifecycles.
-   `ExtractionRunLifecycleTest`: Verifies state machine of runs (`created` -> `completed`).
-   `ReviewStatusBehaviorTest`: Ensures reviews correctly override extracted values and update statuses.
-   `ReviewWebUxTest`: Checks that the web endpoints return correct view data.
-   `ExportComparisonParityTest`: Guarantees that the exported Excel matches the JSON API data.

### Unit Tests (`tests/Unit/Ltr`)
Focus on isolated logic.
-   `FieldValueResolver`: Logic to determine which value to show (Review vs Extraction).
-   `ExportServiceWideRowsTest`: Data formatting logic for exports.

## Review QA Checklist

When deploying changes or validating a new model version, perform the following QA steps:

-   [ ] **Ingestion**: Upload a complex PDF. Verify it splits into chunks in the DB.
-   [ ] **Extraction**: Trigger a run. Ensure status moves to `completed` and `ltr_extracted_fields` are populated.
-   [ ] **UI Rendering**: Open the Comparison View. Verify citations load when clicking a cell.
-   [ ] **Review Logic**:
    -   Accept a value -> Refresh -> Status is Green/Accepted.
    -   Reject a value -> Refresh -> Value is blank/Status Red.
    -   Override a value -> Refresh -> New value shown.
-   [ ] **Export**: Download the Excel report. verify the "Override" value allows appears in the cell, not the original raw extraction.
