<?php

namespace App\Services\Ltr;

use App\Models\Ltr\Document;
use App\Models\Ltr\DocumentChunk;

class CitationService
{
    public function byChunkId(int $chunkId, int $context = 1): array
    {
        $chunk = DocumentChunk::query()->findOrFail($chunkId);
        return $this->buildPayload($chunk, $context);
    }

    public function byUid(int $documentId, string $chunkUid, int $context = 1): array
    {
        $chunk = DocumentChunk::query()
            ->where('document_id', $documentId)
            ->where('chunk_uid', $chunkUid)
            ->firstOrFail();

        return $this->buildPayload($chunk, $context);
    }

    private function buildPayload(DocumentChunk $chunk, int $context): array
    {
        $doc = Document::query()
            ->where('id', $chunk->document_id)
            ->firstOrFail(['id', 'uuid', 'title', 'original_filename']);

        $before = [];
        $after = [];

        if ($context > 0) {
            // Prefer chunk_index ordering when available
            if (!is_null($chunk->chunk_index)) {
                $before = DocumentChunk::query()
                    ->where('document_id', $chunk->document_id)
                    ->where('page_number', $chunk->page_number)
                    ->where('chunk_index', '<', $chunk->chunk_index)
                    ->orderByDesc('chunk_index')
                    ->limit($context)
                    ->get(['id', 'chunk_uid', 'page_number', 'chunk_index', 'text'])
                    ->reverse()
                    ->values()
                    ->toArray();

                $after = DocumentChunk::query()
                    ->where('document_id', $chunk->document_id)
                    ->where('page_number', $chunk->page_number)
                    ->where('chunk_index', '>', $chunk->chunk_index)
                    ->orderBy('chunk_index')
                    ->limit($context)
                    ->get(['id', 'chunk_uid', 'page_number', 'chunk_index', 'text'])
                    ->values()
                    ->toArray();
            } else {
                // Fallback ordering by id
                $before = DocumentChunk::query()
                    ->where('document_id', $chunk->document_id)
                    ->where('id', '<', $chunk->id)
                    ->orderByDesc('id')
                    ->limit($context)
                    ->get(['id', 'chunk_uid', 'page_number', 'chunk_index', 'text'])
                    ->reverse()
                    ->values()
                    ->toArray();

                $after = DocumentChunk::query()
                    ->where('document_id', $chunk->document_id)
                    ->where('id', '>', $chunk->id)
                    ->orderBy('id')
                    ->limit($context)
                    ->get(['id', 'chunk_uid', 'page_number', 'chunk_index', 'text'])
                    ->values()
                    ->toArray();
            }
        }

        return [
            'document' => [
                'id' => $doc->id,
                'uuid' => $doc->uuid,
                'title' => $doc->title ?: $doc->original_filename,
            ],
            'chunk' => [
                'id' => $chunk->id,
                'chunk_uid' => $chunk->chunk_uid,
                'page_number' => $chunk->page_number,
                'chunk_index' => $chunk->chunk_index,
                'text' => $chunk->text,
            ],
            'contextBefore' => $before,
            'contextAfter' => $after,
        ];
    }
}
