<?php

namespace App\Services\Ltr;

use App\Models\Ltr\Document;
use App\Models\Ltr\DocumentChunk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ReingestDocumentService
{
    /**
     * @return array{needs_ocr: bool, chunks_created: int, preview: array<string, mixed>|null}
     */
    public function reingest(Document $document): array
    {
        return DB::transaction(function () use ($document) {
            $raw = Storage::disk($document->storage_disk)->get($document->storage_path);
            $chunks = $this->extractChunks($raw);

            DocumentChunk::query()->where('document_id', $document->id)->delete();

            $needsOcr = count($chunks) === 0;
            $persisted = [];

            foreach ($chunks as $index => $text) {
                $chunk = DocumentChunk::query()->create([
                    'document_id' => $document->id,
                    'chunk_uid' => sprintf('DOC_%d_P1_C%d', $document->id, $index + 1),
                    'page_number' => 1,
                    'chunk_index' => $index,
                    'text' => $text,
                ]);

                $persisted[] = $chunk;
            }

            $document->forceFill(['needs_ocr' => $needsOcr])->save();

            $chunkCount = count($persisted);
            $storedChunkCount = DocumentChunk::query()
                ->where('document_id', $document->id)
                ->count();

            if (!$needsOcr && ($chunkCount === 0 || $storedChunkCount === 0)) {
                throw new RuntimeException("Reingest failed: document {$document->id} has needs_ocr=false but zero chunks were persisted.");
            }

            $first = $persisted[0] ?? null;
            $preview = $first
                ? [
                    'trimmed_length' => Str::length(trim($first->text)),
                    'page_number' => $first->page_number,
                    'chunk_index' => $first->chunk_index,
                    'text_snippet' => Str::limit(preg_replace('/\s+/', ' ', trim($first->text)) ?? '', 120),
                ]
                : null;

            return [
                'needs_ocr' => $needsOcr,
                'chunks_created' => $chunkCount,
                'preview' => $preview,
            ];
        });
    }

    /**
     * @return list<string>
     */
    private function extractChunks(string $raw): array
    {
        $normalized = trim($raw);

        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/\R{2,}/', $normalized) ?: [];

        return array_values(array_filter(array_map(static fn (string $part): string => trim($part), $parts), static fn (string $part): bool => $part !== ''));
    }
}
