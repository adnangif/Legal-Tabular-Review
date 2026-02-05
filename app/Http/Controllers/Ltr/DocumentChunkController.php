<?php

namespace App\Http\Controllers\Ltr;

use App\Http\Controllers\Controller;
use App\Models\Ltr\Document;

class DocumentChunkController extends Controller
{
    public function index(Document $document)
    {
        $chunks = $document->chunks()
            ->select(['id', 'chunk_index', 'page_number', 'text'])
            ->orderBy('chunk_index')
            ->get()
            ->map(fn ($chunk) => [
                'id' => $chunk->id,
                'chunk_index' => $chunk->chunk_index,
                'page_number' => $chunk->page_number,
                'preview' => mb_substr((string) $chunk->text, 0, 200),
            ])
            ->values();

        return response()->json([
            'document_id' => $document->id,
            'total_chunks' => $chunks->count(),
            'chunks' => $chunks,
        ]);
    }
}
