<?php

namespace App\Http\Controllers\Ltr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ltr\ShowCitationChunkRequest;
use App\Http\Resources\Ltr\CitationChunkResponse;
use App\Services\Ltr\CitationService;
use Illuminate\Http\Request;

class CitationController extends Controller
{
    public function showById(Request $request, int $chunk, CitationService $service)
    {
        $context = (int) $request->integer('context', 1);

        $payload = $service->byChunkId($chunk, $context);

        return new CitationChunkResponse((object) $payload);
    }

    public function showByUid(ShowCitationChunkRequest $request, CitationService $service)
    {
        $data = $request->validated();

        $payload = $service->byUid(
            (int) $data['document_id'],
            (string) $data['chunk_uid'],
            (int) ($data['context'] ?? 1)
        );

        return new CitationChunkResponse((object) $payload);
    }
}
