<?php

namespace Tests\Feature\Ltr;

use App\Models\Ltr\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReingestDocumentCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_empty_extraction_creates_document_chunks_records(): void
    {
        Storage::fake('private');

        $document = $this->makeDocument('docs/non-empty.txt');
        Storage::disk('private')->put('docs/non-empty.txt', "First extracted chunk.\n\nSecond extracted chunk.");

        $this->artisan('ltr:reingest-document', ['document' => $document->id])
            ->assertExitCode(0);

        $this->assertDatabaseCount('ltr_document_chunks', 2);
        $this->assertDatabaseHas('ltr_documents', [
            'id' => $document->id,
            'needs_ocr' => 0,
        ]);
    }

    public function test_empty_extraction_sets_needs_ocr_true_and_creates_no_chunks(): void
    {
        Storage::fake('private');

        $document = $this->makeDocument('docs/empty.txt');
        Storage::disk('private')->put('docs/empty.txt', "  \n\n\t ");

        $this->artisan('ltr:reingest-document', ['document' => $document->id])
            ->assertExitCode(0);

        $this->assertDatabaseCount('ltr_document_chunks', 0);
        $this->assertDatabaseHas('ltr_documents', [
            'id' => $document->id,
            'needs_ocr' => 1,
        ]);
    }

    public function test_preview_output_is_present_for_non_ocr_documents(): void
    {
        Storage::fake('private');

        $document = $this->makeDocument('docs/preview.txt');
        Storage::disk('private')->put('docs/preview.txt', "A previewable chunk for this document.");

        $exitCode = Artisan::call('ltr:reingest-document', ['document' => $document->id]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Reingested document', $output);
        $this->assertStringContainsString('First chunk preview:', $output);
        $this->assertStringContainsString('trimmed_length=', $output);
        $this->assertStringContainsString('page=1', $output);
        $this->assertStringContainsString('chunk_index=0', $output);
        $this->assertStringContainsString('text="A previewable chunk for this document."', $output);
    }

    private function makeDocument(string $path): Document
    {
        return Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'original_filename' => basename($path),
            'mime_type' => 'text/plain',
            'storage_disk' => 'private',
            'storage_path' => $path,
            'source' => 'upload',
        ]);
    }
}
