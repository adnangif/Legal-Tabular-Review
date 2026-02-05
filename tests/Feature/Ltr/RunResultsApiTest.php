<?php

namespace Tests\Feature\Ltr;

use App\Models\Ltr\Document;
use App\Models\Ltr\DocumentChunk;
use App\Models\Ltr\ExtractedField;
use App\Models\Ltr\ExtractionRun;
use App\Models\Ltr\FieldReview;
use App\Models\Ltr\FieldTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RunResultsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_endpoint_returns_document_grouped_rows_with_deterministic_cells(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $run = ExtractionRun::query()->create([
            'run_uid' => 'run-results-uid',
            'status' => 'completed',
            'model_name' => 'gpt-5.2-codex',
            'prompt_version' => 'phase4-v1',
        ]);

        $documentA = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Doc A',
            'original_filename' => 'doc-a.pdf',
            'storage_path' => 'docs/doc-a.pdf',
        ]);

        $documentB = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Doc B',
            'original_filename' => 'doc-b.pdf',
            'storage_path' => 'docs/doc-b.pdf',
        ]);

        $templateLaw = FieldTemplate::query()->create([
            'key' => 'governing_law',
            'field_key' => 'governing_law',
            'label' => 'Governing Law',
            'sort_order' => 1,
        ]);

        $templateTerm = FieldTemplate::query()->create([
            'key' => 'term_months',
            'field_key' => 'term_months',
            'label' => 'Term (Months)',
            'sort_order' => 2,
        ]);

        $chunk = DocumentChunk::query()->create([
            'document_id' => $documentA->id,
            'chunk_uid' => 'chunk-a-001',
            'chunk_index' => 1,
            'text' => 'This agreement is governed by California law.',
        ]);

        ExtractedField::query()->create([
            'document_id' => $documentA->id,
            'field_template_id' => $templateLaw->id,
            'extraction_run_id' => $run->id,
            'raw_value' => 'California',
            'confidence' => 0.8123,
            'citation_document_chunk_id' => $chunk->id,
            'citation_quote' => 'governed by California law',
            'status' => 'extracted',
        ]);

        $docBExtracted = ExtractedField::query()->create([
            'document_id' => $documentB->id,
            'field_template_id' => $templateLaw->id,
            'extraction_run_id' => $run->id,
            'raw_value' => 'Texas',
            'confidence' => 0.4,
            'status' => 'needs_review',
        ]);

        FieldReview::query()->create([
            'document_id' => $documentB->id,
            'field_template_id' => $templateLaw->id,
            'extracted_field_id' => $docBExtracted->id,
            'reviewer_id' => $user->id,
            'review_status' => 'overridden',
            'decision' => 'overridden',
            'final_value' => 'New York',
            'is_current' => true,
        ]);

        $response = $this->getJson('/api/v1/ltr/runs/' . $run->id . '/results?document_ids[]=' . $documentA->id . '&document_ids[]=' . $documentB->id);

        $response->assertOk();

        $payload = $response->json();

        $this->assertSame(['run', 'documents'], array_keys($payload));
        $this->assertSame([
            'document_id',
            'document_uuid',
            'document_title',
            'results',
        ], array_keys($payload['documents'][0]));
        $this->assertSame([
            'template_key',
            'template_name',
            'status',
            'source',
            'value',
            'confidence',
            'citation',
            'review',
        ], array_keys($payload['documents'][0]['results'][0]));
        $this->assertSame(['chunk_id', 'chunk_uid', 'page', 'quote'], array_keys($payload['documents'][0]['results'][0]['citation']));

        $reviewPayload = $payload['documents'][1]['results'][0]['review'];
        $this->assertArrayHasKey('review_status', $reviewPayload);
        $this->assertArrayHasKey('reviewed_value', $reviewPayload);
        $this->assertArrayHasKey('review_notes', $reviewPayload);
        $this->assertArrayHasKey('reviewed_by', $reviewPayload);
        $this->assertArrayHasKey('reviewed_at', $reviewPayload);
        $this->assertArrayHasKey('reviewer_id', $reviewPayload);
        $this->assertArrayHasKey('note', $reviewPayload);

        $response->assertJson([
            'run' => [
                'id' => $run->id,
                'run_uid' => 'run-results-uid',
                'status' => 'completed',
                'model_name' => 'gpt-5.2-codex',
                'prompt_version' => 'phase4-v1',
                'started_at' => null,
                'completed_at' => null,
            ],
            'documents' => [
                [
                    'document_id' => $documentA->id,
                    'document_uuid' => $documentA->uuid,
                    'document_title' => 'Doc A',
                    'results' => [
                        [
                            'template_key' => 'governing_law',
                            'template_name' => 'Governing Law',
                            'status' => 'pending',
                            'source' => 'extraction',
                            'value' => 'California',
                            'confidence' => 0.8123,
                            'citation' => [
                                'chunk_id' => $chunk->id,
                                'chunk_uid' => 'chunk-a-001',
                                'page' => null,
                                'quote' => 'governed by California law',
                            ],
                            'review' => null,
                        ],
                        [
                            'template_key' => 'term_months',
                            'template_name' => 'Term (Months)',
                            'status' => 'missing',
                            'source' => null,
                            'value' => null,
                            'confidence' => null,
                            'citation' => [
                                'chunk_id' => null,
                                'chunk_uid' => null,
                                'page' => null,
                                'quote' => null,
                            ],
                            'review' => null,
                        ],
                    ],
                ],
                [
                    'document_id' => $documentB->id,
                    'document_uuid' => $documentB->uuid,
                    'document_title' => 'Doc B',
                    'results' => [
                        [
                            'template_key' => 'governing_law',
                            'template_name' => 'Governing Law',
                            'status' => 'approved',
                            'source' => 'review',
                            'value' => 'New York',
                            'confidence' => 0.4,
                            'citation' => [
                                'chunk_id' => null,
                                'chunk_uid' => null,
                                'page' => null,
                                'quote' => null,
                            ],
                            'review' => [
                                'review_status' => 'overridden',
                                'decision' => 'overridden',
                                'reviewed_at' => null,
                                'reviewed_by' => $user->id,
                                'review_notes' => null,
                                'reviewed_value' => 'New York',
                                'reviewer_id' => $user->id,
                                'note' => null,
                                'used_reviewed_value' => true,
                            ],
                        ],
                        [
                            'template_key' => 'term_months',
                            'template_name' => 'Term (Months)',
                            'status' => 'missing',
                            'source' => null,
                            'value' => null,
                            'confidence' => null,
                            'citation' => [
                                'chunk_id' => null,
                                'chunk_uid' => null,
                                'page' => null,
                                'quote' => null,
                            ],
                            'review' => null,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertJsonPath('documents.0.results.0.source', 'extraction');
        $response->assertJsonPath('documents.1.results.0.source', 'review');
        $response->assertJsonPath('documents.1.results.0.review.review_status', 'overridden');
        $response->assertJsonPath('documents.1.results.0.review.reviewed_by', $user->id);
    }
}
