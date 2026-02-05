<?php

namespace Tests\Feature\Ltr;

use App\Models\Ltr\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentChunkApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_authentication(): void
    {
        $document = Document::query()->create([
            'uuid' => (string) str()->uuid(),
            'original_filename' => 'unauthenticated.pdf',
            'storage_path' => 'docs/unauthenticated.pdf',
        ]);

        $response = $this->getJson("/api/v1/ltr/documents/{$document->id}/chunks");

        $response->assertUnauthorized();
    }

    public function test_it_lists_chunks_for_document_with_preview(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Document::query()->create([
            'uuid' => (string) str()->uuid(),
            'original_filename' => 'doc-1.pdf',
            'storage_path' => 'docs/doc-1.pdf',
        ]);

        $sampleDocument = Document::query()->create([
            'uuid' => (string) str()->uuid(),
            'original_filename' => 'doc-2.pdf',
            'storage_path' => 'docs/doc-2.pdf',
        ]);

        $sampleDocument->chunks()->create([
            'chunk_uid' => 'DOC_2_C1',
            'chunk_index' => 0,
            'page_number' => 1,
            'text' => 'First page short chunk.',
        ]);

        $sampleDocument->chunks()->create([
            'chunk_uid' => 'DOC_2_C2',
            'chunk_index' => 1,
            'page_number' => 2,
            'text' => str_repeat('A', 240),
        ]);

        $response = $this->getJson('/api/v1/ltr/documents/2/chunks');

        $response
            ->assertOk()
            ->assertJsonPath('document_id', 2)
            ->assertJsonPath('total_chunks', 2)
            ->assertJsonPath('chunks.0.chunk_index', 0)
            ->assertJsonPath('chunks.0.page_number', 1)
            ->assertJsonPath('chunks.0.preview', 'First page short chunk.')
            ->assertJsonPath('chunks.1.chunk_index', 1)
            ->assertJsonPath('chunks.1.page_number', 2)
            ->assertJsonPath('chunks.1.preview', str_repeat('A', 200));
    }
}
