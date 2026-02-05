<?php

namespace App\Services\Ltr;

use App\Models\Ltr\Document;
use App\Models\Ltr\DocumentChunk;
use App\Services\Ltr\Pdf\PdfTextExtractor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentIngestionService
{
    public function __construct(private readonly PdfTextExtractor $pdfTextExtractor)
    {
    }

    /**
     * @return array{document_id:int, outcome:string, chunks_inserted:int, first_chunk_preview:string|null}
     */
    public function reingest(Document $document): array
    {
        $bytes = Storage::disk($document->storage_disk)->get($document->storage_path);

        $extractedText = $this->pdfTextExtractor->extract($bytes);
        $normalizedText = $this->normalizeText($extractedText);
        $chunks = $this->chunkText($normalizedText);

        DB::transaction(function () use ($document, $chunks): void {
            DocumentChunk::query()->where('document_id', $document->id)->delete();

            if (empty($chunks)) {
                return;
            }

            $rows = [];
            $timestamp = now();

            foreach ($chunks as $index => $chunk) {
                $rows[] = [
                    'document_id' => $document->id,
                    'chunk_uid' => sprintf('DOC_%d_C%d', $document->id, $index + 1),
                    'page_number' => null,
                    'chunk_index' => $index,
                    'text' => $chunk['text'],
                    'char_start' => $chunk['char_start'],
                    'char_end' => $chunk['char_end'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            DocumentChunk::query()->insert($rows);
        });

        return [
            'document_id' => $document->id,
            'outcome' => $extractedText === '' ? 'empty_text' : 'ok',
            'chunks_inserted' => count($chunks),
            'first_chunk_preview' => $chunks[0]['text'] ?? null,
        ];
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[\t ]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array<int, array{text:string,char_start:int,char_end:int}>
     */
    private function chunkText(string $text, int $maxChars = 1200): array
    {
        if ($text === '') {
            return [];
        }

        $paragraphs = preg_split("/\n\n+/", $text) ?: [];
        $chunks = [];
        $buffer = '';
        $cursor = 0;

        $flush = function () use (&$buffer, &$chunks, &$cursor): void {
            if ($buffer === '') {
                return;
            }

            $chunkText = trim($buffer);
            $length = mb_strlen($chunkText);

            $chunks[] = [
                'text' => $chunkText,
                'char_start' => $cursor,
                'char_end' => $cursor + max($length - 1, 0),
            ];

            $cursor += $length;
            $buffer = '';
        };

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            $candidate = $buffer === '' ? $paragraph : $buffer."\n\n".$paragraph;

            if (mb_strlen($candidate) <= $maxChars) {
                $buffer = $candidate;
                continue;
            }

            $flush();

            while (mb_strlen($paragraph) > $maxChars) {
                $slice = mb_substr($paragraph, 0, $maxChars);
                $sliceLength = mb_strlen($slice);

                $chunks[] = [
                    'text' => $slice,
                    'char_start' => $cursor,
                    'char_end' => $cursor + max($sliceLength - 1, 0),
                ];

                $cursor += $sliceLength;
                $paragraph = ltrim(mb_substr($paragraph, $maxChars));
            }

            $buffer = $paragraph;
        }

        $flush();

        return $chunks;
    }
}
