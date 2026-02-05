<?php

namespace Tests\Feature\Ltr;

use App\Models\Ltr\Document;
use App\Services\Ltr\IngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class IngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_document_as_needing_ocr_and_skips_chunking_for_empty_text(): void
    {
        Log::spy();

        $document = Document::query()->create([
            'uuid' => (string) str()->uuid(),
            'original_filename' => 'empty.pdf',
            'storage_path' => 'ltr/empty.pdf',
        ]);

        $document->chunks()->create([
            'chunk_uid' => 'DOC_1_C1',
            'page_number' => 1,
            'chunk_index' => 0,
            'text' => 'stale text',
        ]);

        app(IngestionService::class)->persistExtractedTextAndChunks($document, "  \n\t", []);

        $document->refresh();

        $this->assertTrue($document->needs_ocr);
        $this->assertDatabaseCount('ltr_document_chunks', 0);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_it_clears_needs_ocr_and_stores_chunks_for_non_empty_text(): void
    {
        Log::spy();

        $document = Document::query()->create([
            'uuid' => (string) str()->uuid(),
            'original_filename' => 'scanned.pdf',
            'storage_path' => 'ltr/scanned.pdf',
            'needs_ocr' => true,
        ]);

        app(IngestionService::class)->persistExtractedTextAndChunks($document, 'Extracted content', [
            [
                'chunk_uid' => 'DOC_2_C1',
                'page_number' => 1,
                'chunk_index' => 0,
                'text' => 'Extracted content',
            ],
        ]);

        $document->refresh();

        $this->assertFalse($document->needs_ocr);
        $this->assertDatabaseCount('ltr_document_chunks', 1);

        Log::shouldHaveReceived('info')->once();
    }
}
