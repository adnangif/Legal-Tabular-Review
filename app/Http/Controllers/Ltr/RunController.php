<?php

namespace App\Http\Controllers\Ltr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ltr\ExecuteRunRequest;
use App\Http\Requests\Ltr\ListRunsRequest;
use App\Http\Requests\Ltr\ShowRunRequest;
use App\Models\Ltr\Document;
use App\Http\Requests\Ltr\ResultsRunRequest;
use App\Models\Ltr\ExtractionRun;
use App\Services\Ltr\ExtractionRunExecutor;
use App\Services\Ltr\RunService;
use Throwable;


class RunController extends Controller
{

    public function execute(ExecuteRunRequest $request, RunService $runService, ExtractionRunExecutor $executor)
    {
        $data = $request->validated();
        $overwrite = (bool) ($data['overwrite'] ?? false);

        $blockedDocumentIds = Document::query()
            ->whereIn('id', collect($data['cells'])->pluck('document_id')->unique()->values())
            ->where('needs_ocr', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($blockedDocumentIds->isNotEmpty()) {
            return response()->json([
                'message' => 'Extraction skipped: one or more documents requires OCR before extraction can run.',
                'blocked_document_ids' => $blockedDocumentIds,
            ], 409);
        }

        if (!empty($data['run_id'])) {
            $run = ExtractionRun::query()->findOrFail((int) $data['run_id']);

            $run->forceFill([
                'status' => 'running',
                'started_at' => now(),
                'completed_at' => null,
            ])->save();

            try {
                $runService->persistExtractedFields($run->id, $data['cells'], $overwrite);

                $run->forceFill([
                    'status' => 'completed',
                    'completed_at' => now(),
                ])->save();
            } catch (Throwable $exception) {
                $run->forceFill([
                    'status' => 'failed',
                    'completed_at' => now(),
                ])->save();

                throw $exception;
            }

            return response()->json([
                'run' => $run->fresh(),
            ]);
        }

        $run = $executor->execute([
            'model_name' => $data['model_name'] ?? null,
            'prompt_version' => $data['prompt_version'] ?? null,
        ], function (ExtractionRun $run) use ($runService, $data, $overwrite): array {
            $runService->persistExtractedFields($run->id, $data['cells'], $overwrite);

            return [
                'processed_cells' => count($data['cells']),
            ];
        });

        return response()->json([
            'run' => $run,
        ], 201);
    }

     public function index(ListRunsRequest $request, RunService $service)
    {
        $docIds = collect($request->validated('document_ids'))->map(fn ($v) => (int) $v)->values();
        $runs = $service->listForDocuments($docIds);

        return response()->json([
            'runs' => $runs->values(),
        ]);
    }

    public function show(ShowRunRequest $request, ExtractionRun $run, RunService $service)
    {
        $docIds = collect($request->validated('document_ids'))->map(fn ($v) => (int) $v)->values();

        $payload = $service->detailsForRun($run->id, $docIds);

        return response()->json($payload);
    }

    public function results(ResultsRunRequest $request, ExtractionRun $run, RunService $service)
    {
        $docIds = collect($request->validated('document_ids'))
            ->map(fn ($v) => (int) $v)
            ->values();

        $payload = $service->resultsForRun($run->id, $docIds->isEmpty() ? null : $docIds);

        return response()->json($payload);
    }
}
