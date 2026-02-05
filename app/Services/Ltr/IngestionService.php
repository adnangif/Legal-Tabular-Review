<?php

namespace App\Services\Ltr;

use App\Models\Ltr\Document;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class IngestionService
{
    /**
     * @param  array<int, array<string, mixed>>  $chunks
     */
    public function persistExtractedTextAndChunks(Document $document, ?string $extractedText, array $chunks): void
    {
        $trimmedText = trim((string) $extractedText);

        if ($trimmedText === '') {
            $deletedChunks = $document->chunks()->count();
            if ($deletedChunks > 0) {
                $document->chunks()->delete();
            }

            $document->forceFill(['needs_ocr' => true])->save();

            Log::warning('OCR required: extracted text is empty; chunking skipped for document.', [
                'document_id' => $document->id,
                'document_uuid' => $document->uuid,
                'deleted_chunks' => $deletedChunks,
            ]);

            return;
        }

        $document->forceFill(['needs_ocr' => false])->save();

        $document->chunks()->delete();

        $document->chunks()->createMany(
            collect($chunks)
                ->values()
                ->map(fn (array $chunk, int $index) => [
                    'chunk_uid' => (string) Arr::get($chunk, 'chunk_uid', sprintf('DOC_%d_C%d', $document->id, $index + 1)),
                    'page_number' => (int) Arr::get($chunk, 'page_number', 1),
                    'chunk_index' => (int) Arr::get($chunk, 'chunk_index', 0),
                    'text' => (string) Arr::get($chunk, 'text', ''),
                    'char_start' => Arr::get($chunk, 'char_start'),
                    'char_end' => Arr::get($chunk, 'char_end'),
                ])
                ->all()
        );

        Log::info('Document text extracted and chunked.', [
            'document_id' => $document->id,
            'document_uuid' => $document->uuid,
            'chunk_count' => count($chunks),
            'needs_ocr' => false,
        ]);
    }
}
