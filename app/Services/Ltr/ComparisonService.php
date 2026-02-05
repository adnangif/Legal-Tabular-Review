<?php

namespace App\Services\Ltr;

use App\Models\Ltr\Document;
use App\Models\Ltr\ExtractionRun;
use App\Models\Ltr\ExtractedField;
use App\Models\Ltr\FieldReview;
use App\Models\Ltr\FieldTemplate;
use Illuminate\Support\Collection;

class ComparisonService
{
    public function __construct(private FieldValueResolver $fieldValueResolver)
    {
    }

    /**
     * @param  Collection<int>  $docIds
     */
    public function build(Collection $docIds, ?int $runId = null): array
    {
        $documents = Document::query()
            ->whereIn('id', $docIds)
            ->get(['id', 'uuid', 'title', 'original_filename']);

        if ($documents->count() !== $docIds->count()) {
            abort(404, 'One or more documents not found');
        }

        $run = $this->resolveRun($docIds, $runId);

        $templates = FieldTemplate::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'field_key', 'label', 'type', 'is_required']);

        $extracted = ExtractedField::query()
            ->with(['citationChunk:id,document_id,chunk_uid'])
            ->where('extraction_run_id', $run->id)
            ->whereIn('document_id', $docIds)
            ->whereIn('field_template_id', $templates->pluck('id'))
            ->get()
            ->keyBy(fn ($r) => $r->document_id . ':' . $r->field_template_id);

        $reviews = FieldReview::query()
            ->where('is_current', true)
            ->whereIn('document_id', $docIds)
            ->whereIn('field_template_id', $templates->pluck('id'))
            ->get()
            ->keyBy(fn ($r) => $r->document_id . ':' . $r->field_template_id);

        $fieldRows = $templates->map(function ($t) use ($docIds, $extracted, $reviews, $run) {
            $cells = [];

            foreach ($docIds as $docId) {
                $key = $docId . ':' . $t->id;

                $review = $reviews->get($key);
                $ext = $extracted->get($key);

                $cell = $this->fieldValueResolver->resolve($ext, $review);
                $cell['extracted_field_id'] = $ext?->id;
                $cell['extraction_run_id'] = $run->id;

                $cells[(string) $docId] = $cell;
            }

            return (object) [
                'field_id' => $t->id,
                'key' => $t->key,
                'field_key' => $t->field_key,
                'label' => $t->label,
                'type' => $t->type,
                'is_required' => (bool) $t->is_required,
                'cells' => $cells,
            ];
        });

        return [
            'run' => $run,
            'documents' => $documents,
            'fields' => $fieldRows,
        ];
    }

    private function resolveRun(Collection $docIds, ?int $runId): ExtractionRun
    {
        if ($runId) {
            return ExtractionRun::query()->findOrFail($runId);
        }

        $latestRunId = ExtractedField::query()
            ->whereIn('document_id', $docIds)
            ->max('extraction_run_id');

        abort_if(!$latestRunId, 404, 'No extraction run found for these documents');

        return ExtractionRun::query()->findOrFail($latestRunId);
    }

}
