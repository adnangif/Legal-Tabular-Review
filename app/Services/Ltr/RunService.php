<?php

namespace App\Services\Ltr;

use App\Models\Ltr\ExtractedField;
use App\Models\Ltr\ExtractionRun;
use App\Models\Ltr\FieldReview;
use App\Models\Ltr\FieldTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RunService
{
    public function __construct(private FieldValueResolver $fieldValueResolver)
    {
    }

    /**
     * Persist extraction cells for a run using the DB unique key
     * (document_id, field_template_id, extraction_run_id).
     *
     * @param  array<int, array<string, mixed>>  $cells
     */
    public function persistExtractedFields(int $runId, array $cells, bool $overwrite = false): void
    {
        $now = now();

        $rows = collect($cells)
            ->map(function (array $cell) use ($runId, $now) {
                return [
                    'document_id' => (int) $cell['document_id'],
                    'field_template_id' => (int) $cell['field_template_id'],
                    'extraction_run_id' => $runId,
                    'raw_value' => $cell['raw_value'] ?? null,
                    'normalized_value' => $cell['normalized_value'] ?? null,
                    'confidence' => $cell['confidence'] ?? null,
                    'citation_document_chunk_id' => $cell['citation_document_chunk_id'] ?? null,
                    'citation_page_number' => $cell['citation_page_number'] ?? null,
                    'citation_quote' => $cell['citation_quote'] ?? null,
                    'evidence_spans' => $cell['evidence_spans'] ?? null,
                    'status' => $cell['status'] ?? 'extracted',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values();

        $docIds = $rows->pluck('document_id')->unique()->values();
        $templateIds = $rows->pluck('field_template_id')->unique()->values();

        DB::transaction(function () use ($runId, $rows, $docIds, $templateIds, $overwrite) {
            // Overwrite is explicitly scoped to this run + these docs + these templates.
            if ($overwrite && $docIds->isNotEmpty() && $templateIds->isNotEmpty()) {
                ExtractedField::query()
                    ->where('extraction_run_id', $runId)
                    ->whereIn('document_id', $docIds)
                    ->whereIn('field_template_id', $templateIds)
                    ->delete();
            }

            if ($rows->isEmpty()) {
                return;
            }

            ExtractedField::query()->upsert(
                $rows->all(),
                ['document_id', 'field_template_id', 'extraction_run_id'],
                [
                    'raw_value',
                    'normalized_value',
                    'confidence',
                    'citation_document_chunk_id',
                    'citation_page_number',
                    'citation_quote',
                    'evidence_spans',
                    'status',
                    'updated_at',
                ]
            );
        });

        $this->refreshRunStatsMeta($runId, $now);
    }

    public function listForDocuments(Collection $docIds): Collection
    {
        // Find runs that have extracted fields for any of these documents
        $runIds = ExtractedField::query()
            ->whereIn('document_id', $docIds)
            ->select('extraction_run_id')
            ->distinct()
            ->pluck('extraction_run_id');

        if ($runIds->isEmpty()) {
            return collect();
        }
        // Aggregate counts per run for UI
        $counts = $this->aggregateStatsForRuns($runIds, $docIds);

        $runs = ExtractionRun::query()
            ->whereIn('id', $runIds)
            ->orderByDesc('id')
            ->get(['id', 'run_uid', 'status', 'model_name', 'prompt_version', 'started_at', 'completed_at', 'created_at']);

        return $runs->map(function ($run) use ($counts, $runs) {
            $c = $counts->get($run->id);
            $isLatest = $runs->first()?->id === $run->id;

            return [
                'id' => $run->id,
                'run_uid' => $run->run_uid,
                'is_latest' => $isLatest,
                'status' => $run->status,
                'model_name' => $run->model_name,
                'prompt_version' => $run->prompt_version,
                'started_at' => optional($run->started_at)->toIso8601String(),
                'completed_at' => optional($run->completed_at)->toIso8601String(),
                'created_at' => optional($run->created_at)->toIso8601String(),

                'stats' => [
                    ...$this->formatStatsFromAggregate($c),
                ],
            ];
        });
    }

    public function detailsForRun(int $runId, Collection $docIds): array
    {
        $run = ExtractionRun::query()->findOrFail($runId);

        // Documents (basic)
        $documents = \App\Models\Ltr\Document::query()
            ->whereIn('id', $docIds)
            ->get(['id', 'uuid', 'title', 'original_filename'])
            ->keyBy('id');

        // Field templates define the canonical rows
        $templates = \App\Models\Ltr\FieldTemplate::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'field_key', 'label', 'type', 'is_required']);

        // All extracted fields for this run + docs
        $extracted = \App\Models\Ltr\ExtractedField::query()
            ->where('extraction_run_id', $runId)
            ->whereIn('document_id', $docIds)
            ->whereIn('field_template_id', $templates->pluck('id'))
            ->get()
            ->keyBy(fn ($r) => $r->document_id . ':' . $r->field_template_id);
        
        $reviews = \App\Models\Ltr\FieldReview::query()
            ->where('is_current', true)
            ->whereIn('document_id', $docIds)
            ->whereIn('field_template_id', $templates->pluck('id'))
            ->get()
            ->keyBy(fn ($r) => $r->document_id . ':' . $r->field_template_id);
        
        $effectiveCell = function (int $docId, int $fieldTemplateId) use ($extracted, $reviews) {
            $key = $docId . ':' . $fieldTemplateId;

            $review = $reviews->get($key);
            $ext = $extracted->get($key);

            return $this->fieldValueResolver->resolve($ext, $review);
        };


        // Coverage per document + status counts
        
        $docCoverage = [];
        foreach ($docIds as $docId) {
            $statuses = $templates->map(function ($t) use ($docId, $effectiveCell) {
                return $effectiveCell($docId, $t->id)['status'];
            });

            $docCoverage[] = [
                'document_id' => $docId,
                'title' => ($documents[$docId]->title ?: $documents[$docId]->original_filename) ?? ('Doc #' . $docId),
                'counts' => [
                    'total_fields' => $statuses->count(),
                    'missing' => $statuses->filter(fn ($s) => $s === 'missing')->count(),
                    'needs_review' => $statuses->filter(fn ($s) => $s === 'needs_review')->count(),
                    'ambiguous' => $statuses->filter(fn ($s) => $s === 'ambiguous')->count(),
                    'extracted' => $statuses->filter(fn ($s) => $s === 'extracted')->count(),
                    'approved' => $statuses->filter(fn ($s) => $s === 'approved')->count(),
                ],
            ];
        }
        // Field-level summary across docs
        $fields = $templates->map(function ($t) use ($docIds, $effectiveCell) {
        $statuses = [];
        $values = [];

        foreach ($docIds as $docId) {
            $cell = $effectiveCell($docId, $t->id);

            $statuses[(string) $docId] = $cell['status'];

            $val = $cell['value'];
            if ($val !== null && trim((string) $val) !== '') {
                $values[] = mb_strtolower(trim((string) $val));
            }
        }

        $values = collect($values)->unique()->values();
        $conflict = $values->count() > 1;

        return [
            'field_id' => $t->id,
            'key' => $t->key,
            'field_key' => $t->field_key,
            'label' => $t->label,
            'type' => $t->type,
            'is_required' => (bool) $t->is_required,
            'conflict' => $conflict,
            'statuses' => $statuses,
            'counts' => [
                'missing' => collect($statuses)->filter(fn ($s) => $s === 'missing')->count(),
                'needs_review' => collect($statuses)->filter(fn ($s) => $s === 'needs_review')->count(),
                'ambiguous' => collect($statuses)->filter(fn ($s) => $s === 'ambiguous')->count(),
                'extracted' => collect($statuses)->filter(fn ($s) => $s === 'extracted')->count(),
                'approved' => collect($statuses)->filter(fn ($s) => $s === 'approved')->count(),
            ],
        ];
    })->values();


        $totals = [
            'missing' => 0,
            'needs_review' => 0,
            'ambiguous' => 0,
            'extracted' => 0,
            'approved' => 0,
        ];

        foreach ($docCoverage as $d) {
            foreach ($totals as $k => $_) {
                $totals[$k] += (int) ($d['counts'][$k] ?? 0);
            }
        }

        return [
            'run' => [
                'id' => $run->id,
                'run_uid' => $run->run_uid,
                'status' => $run->status,
                'model_name' => $run->model_name,
                'prompt_version' => $run->prompt_version,
                'started_at' => optional($run->started_at)->toIso8601String(),
                'completed_at' => optional($run->completed_at)->toIso8601String(),
                'created_at' => optional($run->created_at)->toIso8601String(),
            ],
            'stats' => [
                'total_fields' => $totals['missing'] + $totals['needs_review'] + $totals['ambiguous'] + $totals['extracted'] + $totals['approved'],
                'missing' => $totals['missing'],
                'needs_review' => $totals['needs_review'],
                'ambiguous' => $totals['ambiguous'],
                'extracted' => $totals['extracted'],
                'approved' => $totals['approved'],
                'conflicts' => $fields->where('conflict', true)->count(),
            ],
            'documents' => $docCoverage,
            'fields' => $fields,
        ];
    }

    public function resultsForRun(int $runId, ?Collection $docIds = null): array
    {
        $run = ExtractionRun::query()->findOrFail($runId);

        $docIdList = $docIds
            ? $docIds->map(fn ($id) => (int) $id)->unique()->values()
            : ExtractedField::query()
                ->where('extraction_run_id', $runId)
                ->select('document_id')
                ->distinct()
                ->orderBy('document_id')
                ->pluck('document_id')
                ->map(fn ($id) => (int) $id)
                ->values();

        $documents = \App\Models\Ltr\Document::query()
            ->whereIn('id', $docIdList)
            ->get(['id', 'uuid', 'title', 'original_filename'])
            ->keyBy('id');

        $templateRows = FieldTemplate::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'key', 'field_key', 'label']);

        $templateIds = $templateRows->pluck('id');

        $extracted = ExtractedField::query()
            ->with([
                'citationChunk:id,chunk_uid',
                'fieldTemplate:id,key,field_key,label',
            ])
            ->where('extraction_run_id', $runId)
            ->whereIn('document_id', $docIdList)
            ->whereIn('field_template_id', $templateIds)
            ->get()
            ->keyBy(fn ($row) => $row->document_id . ':' . $row->field_template_id);

        $reviews = FieldReview::query()
            ->where('is_current', true)
            ->whereIn('document_id', $docIdList)
            ->whereIn('field_template_id', $templateIds)
            ->get()
            ->keyBy(fn ($row) => $row->document_id . ':' . $row->field_template_id);

        $documentsPayload = $docIdList->map(function (int $docId) use ($documents, $templateRows, $extracted, $reviews) {
            $document = $documents->get($docId);

            $results = $templateRows->map(function ($template) use ($docId, $extracted, $reviews) {
                $key = $docId . ':' . $template->id;
                $row = $extracted->get($key);
                $review = $reviews->get($key);

                $resolved = $this->fieldValueResolver->resolve($row, $review);

                return [
                    'template_key' => $template->key ?: $template->field_key,
                    'template_name' => $template->label,
                    'status' => $resolved['status'],
                    'source' => $resolved['source'],
                    'value' => $resolved['resolved_value'],
                    'confidence' => $resolved['confidence'],
                    'citation' => [
                        'chunk_id' => $row?->citation_document_chunk_id,
                        'chunk_uid' => $resolved['citation']['chunk_uid'] ?? null,
                        'page' => $resolved['citation']['page'] ?? null,
                        'quote' => $resolved['citation']['quote'] ?? null,
                    ],
                    'review' => $resolved['review'],
                ];
            })->values();

            return [
                'document_id' => $docId,
                'document_uuid' => $document?->uuid,
                'document_title' => $document?->title ?: $document?->original_filename,
                'results' => $results,
            ];
        })->values();

        return [
            'run' => [
                'id' => $run->id,
                'run_uid' => $run->run_uid,
                'status' => $run->status,
                'model_name' => $run->model_name,
                'prompt_version' => $run->prompt_version,
                'started_at' => optional($run->started_at)->toIso8601String(),
                'completed_at' => optional($run->completed_at)->toIso8601String(),
            ],
            'documents' => $documentsPayload,
        ];
    }

    private function refreshRunStatsMeta(int $runId, Carbon $timestamp): void
    {
        $run = ExtractionRun::query()->findOrFail($runId);
        $aggregate = $this->aggregateStatsForRuns(collect([$runId]))->get($runId);
        $stats = $this->formatStatsFromAggregate($aggregate);

        $run->forceFill([
            'meta' => array_replace($run->meta ?? [], [
                'stats' => $stats,
                'stats_updated_at' => $timestamp->toIso8601String(),
            ]),
        ])->save();
    }

    private function aggregateStatsForRuns(Collection $runIds, ?Collection $docIds = null): Collection
    {
        if ($runIds->isEmpty()) {
            return collect();
        }

        $query = ExtractedField::query()
            ->whereIn('extraction_run_id', $runIds);

        if ($docIds !== null) {
            $query->whereIn('document_id', $docIds);
        }

        return $query
            ->select([
                'extraction_run_id',
                DB::raw('COUNT(*) as total_fields'),
                DB::raw('COUNT(DISTINCT document_id) as documents_covered'),
                DB::raw("SUM(CASE WHEN status = 'missing' THEN 1 ELSE 0 END) as missing_count"),
                DB::raw("SUM(CASE WHEN status = 'needs_review' THEN 1 ELSE 0 END) as needs_review_count"),
                DB::raw("SUM(CASE WHEN status = 'ambiguous' THEN 1 ELSE 0 END) as ambiguous_count"),
            ])
            ->groupBy('extraction_run_id')
            ->get()
            ->keyBy('extraction_run_id');
    }

    private function formatStatsFromAggregate(mixed $aggregate): array
    {
        $totalFields = $aggregate?->total_fields ? (int) $aggregate->total_fields : 0;
        $missing = $aggregate?->missing_count ? (int) $aggregate->missing_count : 0;
        $needsReview = $aggregate?->needs_review_count ? (int) $aggregate->needs_review_count : 0;
        $ambiguous = $aggregate?->ambiguous_count ? (int) $aggregate->ambiguous_count : 0;

        return [
            'total_fields' => $totalFields,
            'missing' => $missing,
            'needs_review' => $needsReview,
            'ambiguous' => $ambiguous,
            'extracted' => max(0, $totalFields - ($missing + $needsReview + $ambiguous)),
            'documents_covered' => $aggregate?->documents_covered ? (int) $aggregate->documents_covered : 0,
        ];
    }

}
