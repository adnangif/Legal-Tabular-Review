<?php

namespace App\Services\Ltr;

use Illuminate\Support\Collection;

class ExportService
{
    public function csvHeader(): array
    {
        return [
            'Field Key',
            'Field',
            'Document',
            'Value',
            'Confidence',
            'Status',
            'Source',
            'Conflict',
            'Citation Page',
            'Citation Chunk UID',
            'Citation Quote',
        ];
    }

    public function rowToCsv(array $row): array
    {
        return [
            $row['field_key'],
            $row['field'],
            $row['document'],
            $row['value'],
            $row['confidence'],
            $row['status'],
            $row['source'],
            $row['conflict'] ? 'yes' : 'no',
            $row['citation_page'],
            $row['citation_chunk_uid'],
            $row['citation_quote'],
        ];
    }

    /**
     * Canonical shaped dataset for tabular exports.
     * Header shape: Field Key, Field, Conflict, then per-document columns.
     */
    public function buildCanonicalComparisonExport(array $comparisonPayload, array $filters = []): array
    {
        $documents = collect($comparisonPayload['documents'])->values();
        $parsedFilters = $this->parseFilters($filters);

        $docLabels = $this->resolveDocumentLabels($documents);

        $header = ['Field Key', 'Field', 'Conflict'];
        foreach ($documents as $doc) {
            $docId = (int) $doc->id;
            $label = $docLabels[$docId] ?? ('Doc #' . $docId);

            $header[] = $label . ' Value';
            $header[] = $label . ' Confidence';
            $header[] = $label . ' Citation Page';
            $header[] = $label . ' Citation Quote';
        }

        $rows = [];

        foreach ($comparisonPayload['fields'] as $fieldRow) {
            $fieldKey = $fieldRow->field_key;

            if ($parsedFilters['field_keys']->isNotEmpty() && !$parsedFilters['field_keys']->contains($fieldKey)) {
                continue;
            }

            $conflict = $this->isFieldInConflict($fieldRow);
            if ($parsedFilters['only_conflicts'] && !$conflict) {
                continue;
            }

            $row = [
                $fieldKey,
                $fieldRow->label,
                $conflict ? 'yes' : 'no',
            ];

            foreach ($documents as $doc) {
                $cell = $fieldRow->cells[(string) $doc->id] ?? null;
                $normalized = $this->canonicalCellValues($cell, $parsedFilters);

                $row[] = $normalized['value'];
                $row[] = $normalized['confidence'];
                $row[] = $normalized['citation_page'];
                $row[] = $normalized['citation_quote'];
            }

            $rows[] = $row;
        }

        return [
            'header' => $header,
            'rows' => $rows,
        ];
    }

    /**
     * Build export rows from ComparisonService payload.
     * Applies filters at export-time.
     */
    public function flattenComparisonForExport(array $comparisonPayload, array $filters = []): array
    {
        $documents = collect($comparisonPayload['documents'])->keyBy('id');

        $fieldKeysFilter = collect($filters['field_keys'] ?? [])
            ->filter()
            ->map(fn ($v) => (string) $v)
            ->values();

        $statusFilter = $filters['status'] ?? null;
        $sourceFilter = $filters['source'] ?? null;

        $minConfidence = array_key_exists('min_confidence', $filters) && $filters['min_confidence'] !== null
            ? (float) $filters['min_confidence']
            : null;

        $onlyConflicts = (bool) ($filters['only_conflicts'] ?? false);
        $includeMissing = (bool) ($filters['include_missing'] ?? true);

        $rows = [];

        foreach ($comparisonPayload['fields'] as $fieldRow) {
            $fieldKey = $fieldRow->field_key;
            $fieldLabel = $fieldRow->label;

            if ($fieldKeysFilter->isNotEmpty() && !$fieldKeysFilter->contains($fieldKey)) {
                continue;
            }

            $conflict = $this->isFieldInConflict($fieldRow);

            if ($onlyConflicts && !$conflict) {
                continue;
            }

            foreach ($fieldRow->cells as $docId => $cell) {
                $doc = $documents->get((int) $docId);

                $status = $cell['status'] ?? null;
                $source = $cell['source'] ?? null;

                if (!$includeMissing && $status === 'missing') {
                    continue;
                }

                if ($statusFilter && $status !== $statusFilter) {
                    continue;
                }

                if ($sourceFilter && $source !== $sourceFilter) {
                    continue;
                }

                if ($minConfidence !== null) {
                    $conf = $cell['confidence'];
                    if ($conf === null || (float) $conf < $minConfidence) {
                        continue;
                    }
                }

                $citation = $cell['citation'] ?? null;

                $rows[] = [
                    'field_key' => $fieldKey,
                    'field' => $fieldLabel,
                    'document' => $doc?->title ?: $doc?->original_filename ?: ('Doc #' . $docId),
                    'value' => $cell['value'],
                    'confidence' => $cell['confidence'],
                    'status' => $status,
                    'source' => $source,
                    'conflict' => $conflict,
                    'citation_page' => $citation['page'] ?? null,
                    'citation_chunk_uid' => $citation['chunk_uid'] ?? null,
                    'citation_quote' => $citation['quote'] ?? null,
                ];
            }
        }

        return $rows;
    }

    /**
     * A field is a conflict if effective values differ across documents (ignoring null and trimming).
     */
    private function isFieldInConflict(object $fieldRow): bool
    {
        $values = collect($fieldRow->cells)
            ->map(fn ($cell) => $cell['value'] ?? null)
            ->filter(fn ($v) => $v !== null && trim((string) $v) !== '')
            ->map(fn ($v) => mb_strtolower(trim((string) $v)))
            ->unique()
            ->values();

        return $values->count() > 1;
    }

    public function buildWideExport(array $comparisonPayload, array $filters = []): array
    {
        $documents = collect($comparisonPayload['documents'])->keyBy('id');

        $fieldKeysFilter = collect($filters['field_keys'] ?? [])
            ->filter()
            ->map(fn ($v) => (string) $v)
            ->values();

        $statusFilter = $filters['status'] ?? null;
        $sourceFilter = $filters['source'] ?? null;

        $minConfidence = array_key_exists('min_confidence', $filters) && $filters['min_confidence'] !== null
            ? (float) $filters['min_confidence']
            : null;

        $onlyConflicts = (bool) ($filters['only_conflicts'] ?? false);
        $includeMissing = (bool) ($filters['include_missing'] ?? true);

        $docTitles = $documents->map(fn ($d) => $d->title ?: $d->original_filename ?: ('Doc #' . $d->id))->values();
        $comparisonHeader = array_merge(['Field Key', 'Field'], $docTitles->all());
        $metaHeader = $comparisonHeader; // same shape as comparison
        $metaRows = [];                  // each row aligns with comparison_rows
        $rowConflicts = [];     // parallel array to comparison_rows with boolean conflict flag for each row
        $statusCounts = [
            'approved' => 0,
            'needs_review' => 0,
            'ambiguous' => 0,
            'missing' => 0,
            'extracted' => 0,
        ];

        $sourceCounts = [
            'review' => 0,
            'extraction' => 0,
        ];

        $totalConflicts = 0;
        $totalFieldsExported = 0;

        $comparisonRows = [];
        $citationsRows = [];

        $citationsHeader = [
            'Field Key',
            'Field',
            'Document',
            'Value',
            'Status',
            'Source',
            'Confidence',
            'Citation Page',
            'Citation Chunk UID',
            'Citation Quote',
        ];

        foreach ($comparisonPayload['fields'] as $fieldRow) {
            $fieldKey = $fieldRow->field_key;
            $fieldLabel = $fieldRow->label;

            if ($fieldKeysFilter->isNotEmpty() && !$fieldKeysFilter->contains($fieldKey)) {
                continue;
            }

            $conflict = $this->isFieldInConflict($fieldRow);

            if ($onlyConflicts && !$conflict) {
                continue;
            }

            $totalFieldsExported++;
            if ($conflict) {
                $totalConflicts++;
            }

            $comparisonRow = [$fieldKey, $fieldLabel];
            $metaRow = [$fieldKey, $fieldLabel];

            foreach ($documents as $docId => $doc) {
                $cell = $fieldRow->cells[(string) $docId] ?? null;

                if (!$cell) {
                    $comparisonRow[] = null;
                    $metaRow[] = 'status:null|source:null|confidence:null';
                    continue;
                }

                $status = $cell['status'] ?? null;
                $source = $cell['source'] ?? null;
                $confidence = $cell['confidence'] ?? null;

                if ($status && isset($statusCounts[$status])) {
                    $statusCounts[$status]++;
                }

                if ($source && isset($sourceCounts[$source])) {
                    $sourceCounts[$source]++;
                }

                if (!$includeMissing && $status === 'missing') {
                    $comparisonRow[] = null;
                    $metaRow[] = 'status:missing|source:' . ($source ?? 'null') . '|confidence:' . ($confidence !== null ? (string) $confidence : 'null');
                    continue;
                }

                if ($statusFilter && $status !== $statusFilter) {
                    $comparisonRow[] = null;
                    $metaRow[] = 'status:' . ($status ?? 'null') . '|source:' . ($source ?? 'null') . '|confidence:' . ($confidence !== null ? (string) $confidence : 'null');
                    continue;
                }

                if ($sourceFilter && $source !== $sourceFilter) {
                    $comparisonRow[] = null;
                    $metaRow[] = 'status:' . ($status ?? 'null') . '|source:' . ($source ?? 'null') . '|confidence:' . ($confidence !== null ? (string) $confidence : 'null');
                    continue;
                }

                if ($minConfidence !== null) {
                    if ($confidence === null || (float) $confidence < $minConfidence) {
                        $comparisonRow[] = null;
                        $metaRow[] = 'status:' . ($status ?? 'null') . '|source:' . ($source ?? 'null') . '|confidence:' . ($confidence !== null ? (string) $confidence : 'null');
                        continue;
                    }
                }

                $comparisonRow[] = $cell['value'];

                $citation = $cell['citation'] ?? null;
                $hasCitation = $citation && (
                    !empty($citation['page']) ||
                    !empty($citation['chunk_uid']) ||
                    !empty($citation['quote'])
                );

                if ($hasCitation) {
                    $citationsRows[] = [
                        $fieldKey,
                        $fieldLabel,
                        $doc->title ?: $doc->original_filename ?: ('Doc #' . $docId),
                        $cell['value'],
                        $status,
                        $source,
                        $confidence,
                        $citation['page'] ?? null,
                        $citation['chunk_uid'] ?? null,
                        $citation['quote'] ?? null,
                    ];
                }

                $metaRow[] = implode('|', [
                    'status:' . ($status ?? 'null'),
                    'source:' . ($source ?? 'null'),
                    'confidence:' . ($confidence !== null ? (string) $confidence : 'null'),
                ]);
            }

            $comparisonRows[] = $comparisonRow;
            $metaRows[] = $metaRow;
            $rowConflicts[] = $conflict;
        }

        return [
            'comparison_header' => $comparisonHeader,
            'comparison_rows' => $comparisonRows,
            'citations_header' => $citationsHeader,
            'citations_rows' => $citationsRows,
            'meta_header' => $metaHeader,
            'meta_rows' => $metaRows,
            'row_conflicts' => $rowConflicts,
            'summary' => [
                'total_fields' => $totalFieldsExported,
                'total_documents' => $documents->count(),
                'total_conflicts' => $totalConflicts,
                'status_counts' => $statusCounts,
                'source_counts' => $sourceCounts,
                'filters' => [
                    'field_keys' => $fieldKeysFilter->all(),
                    'status' => $statusFilter,
                    'source' => $sourceFilter,
                    'min_confidence' => $minConfidence,
                    'only_conflicts' => $onlyConflicts,
                    'include_missing' => $includeMissing,
                ],
            ],
        ];

    }

    private function parseFilters(array $filters): array
    {
        return [
            'field_keys' => collect($filters['field_keys'] ?? [])->filter()->map(fn ($v) => (string) $v)->values(),
            'status' => $filters['status'] ?? null,
            'source' => $filters['source'] ?? null,
            'min_confidence' => array_key_exists('min_confidence', $filters) && $filters['min_confidence'] !== null
                ? (float) $filters['min_confidence']
                : null,
            'only_conflicts' => (bool) ($filters['only_conflicts'] ?? false),
            'include_missing' => (bool) ($filters['include_missing'] ?? true),
        ];
    }

    private function canonicalCellValues(?array $cell, array $filters): array
    {
        if (!$cell) {
            return [
                'value' => null,
                'confidence' => null,
                'citation_page' => null,
                'citation_quote' => null,
            ];
        }

        $status = $cell['status'] ?? null;
        $source = $cell['source'] ?? null;
        $confidence = $cell['confidence'] ?? null;

        if (!$filters['include_missing'] && $status === 'missing') {
            return ['value' => null, 'confidence' => null, 'citation_page' => null, 'citation_quote' => null];
        }

        if ($filters['status'] && $status !== $filters['status']) {
            return ['value' => null, 'confidence' => null, 'citation_page' => null, 'citation_quote' => null];
        }

        if ($filters['source'] && $source !== $filters['source']) {
            return ['value' => null, 'confidence' => null, 'citation_page' => null, 'citation_quote' => null];
        }

        if ($filters['min_confidence'] !== null && ($confidence === null || (float) $confidence < $filters['min_confidence'])) {
            return ['value' => null, 'confidence' => null, 'citation_page' => null, 'citation_quote' => null];
        }

        $citation = $cell['citation'] ?? null;

        return [
            'value' => $cell['value'] ?? null,
            'confidence' => $confidence,
            'citation_page' => $citation['page'] ?? null,
            'citation_quote' => $citation['quote'] ?? null,
        ];
    }

    /**
     * @param  Collection<int, object>  $documents
     * @return array<int, string>
     */
    private function resolveDocumentLabels(Collection $documents): array
    {
        $baseLabels = [];
        foreach ($documents as $doc) {
            $base = trim((string) ($doc->title ?: $doc->original_filename ?: 'Doc #' . $doc->id));
            $baseLabels[(int) $doc->id] = $base !== '' ? $base : ('Doc #' . $doc->id);
        }

        $counts = array_count_values(array_values($baseLabels));

        $labels = [];
        foreach ($documents as $doc) {
            $docId = (int) $doc->id;
            $base = $baseLabels[$docId];
            $labels[$docId] = ($counts[$base] ?? 0) > 1
                ? $base . ' (#' . $docId . ')'
                : $base;
        }

        return $labels;
    }
}
