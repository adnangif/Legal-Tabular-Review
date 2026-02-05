<?php

namespace App\Http\Controllers\Ltr;

use App\Http\Controllers\Controller;
use App\Models\Ltr\Document;
use App\Services\Ltr\ComparisonService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class ComparisonController extends Controller
{
    public function index(Request $request)
    {
        $documents = Document::query()
            ->orderBy('title')
            ->orderBy('original_filename')
            ->orderBy('id')
            ->get(['id', 'title', 'original_filename']);

        $defaults = [
            'document_ids' => collect($request->input('document_ids', []))
                ->filter(fn ($value) => is_numeric($value))
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values()
                ->all(),
            'min_confidence' => $request->input('min_confidence', ''),
            'status' => $request->input('status', ''),
            'include_missing' => $request->boolean('include_missing'),
            'conflicts_only' => $request->boolean('conflicts_only'),
        ];

        return view('ltr.index', [
            'documents' => $documents,
            'defaults' => $defaults,
        ]);
    }

    public function table(Request $request, ComparisonService $comparisonService)
    {
        $validated = Validator::make($request->query(), [
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['integer', 'distinct', 'exists:ltr_documents,id'],
            'run_id' => ['nullable', 'integer', 'exists:ltr_extraction_runs,id'],
        ])->validate();

        $docIds = collect($validated['document_ids'])
            ->map(fn ($v) => (int) $v)
            ->values();

        $comparison = $comparisonService->build($docIds, isset($validated['run_id']) ? (int) $validated['run_id'] : null);

        return view('ltr.table', [
            'run' => $comparison['run'],
            'documents' => $comparison['documents'],
            'fields' => $comparison['fields'],
        ]);
    }

    public function show(Request $request, ComparisonService $comparisonService)
    {
        $docIds = collect($request->input('document_ids', []))
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        abort_if($docIds->isEmpty(), 422, 'document_ids is required');

        $comparison = $comparisonService->build($docIds, $request->integer('run_id'));

        return response()->json([
            'meta' => [
                'run_id' => $comparison['run']->id,
                'run_uid' => $comparison['run']->run_uid,
                'documents' => $comparison['documents']->map(fn ($d) => [
                    'id' => $d->id,
                    'uuid' => $d->uuid,
                    'title' => $d->title ?: $d->original_filename,
                ])->values(),
                'generated_at' => Carbon::now()->toIso8601String(),
            ],
            'fields' => $comparison['fields']->values(),
        ]);
    }
}
