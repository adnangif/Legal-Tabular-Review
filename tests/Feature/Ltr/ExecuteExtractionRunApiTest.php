<?php

namespace Tests\Feature\Ltr;

use App\Models\Ltr\Document;
use App\Models\Ltr\ExtractedField;
use App\Models\Ltr\ExtractionRun;
use App\Models\Ltr\FieldTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExecuteExtractionRunApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_execute_the_same_run_twice_with_overwrite_without_duplicates(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $run = ExtractionRun::query()->create([
            'model_name' => 'gpt-5.2-codex',
            'prompt_version' => 'phase4-v1',
        ]);

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'original_filename' => 'contract.pdf',
            'storage_path' => 'contracts/contract.pdf',
        ]);

        $template = FieldTemplate::query()->create([
            'field_key' => 'governing_law',
            'label' => 'Governing Law',
        ]);

        $payload = [
            'run_id' => $run->id,
            'overwrite' => 1,
            'cells' => [
                [
                    'document_id' => $document->id,
                    'field_template_id' => $template->id,
                    'raw_value' => 'New York',
                    'status' => 'extracted',
                ],
            ],
        ];

        $first = $this->postJson('/api/v1/ltr/runs/execute', $payload);

        $first->assertOk()
            ->assertJsonPath('run.id', $run->id)
            ->assertJsonPath('run.status', 'completed');

        $this->assertDatabaseCount('ltr_extracted_fields', 1);

        $secondPayload = $payload;
        $secondPayload['cells'][0]['raw_value'] = 'Delaware';

        $second = $this->postJson('/api/v1/ltr/runs/execute', $secondPayload);

        $second->assertOk()
            ->assertJsonPath('run.id', $run->id)
            ->assertJsonPath('run.status', 'completed');

        $this->assertDatabaseCount('ltr_extracted_fields', 1);

        $field = ExtractedField::query()
            ->where('extraction_run_id', $run->id)
            ->where('document_id', $document->id)
            ->where('field_template_id', $template->id)
            ->first();

        $this->assertNotNull($field);
        $this->assertSame('Delaware', $field->raw_value);
    }

    public function test_it_blocks_extraction_when_any_document_requires_ocr(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'original_filename' => 'scanned-contract.pdf',
            'storage_path' => 'contracts/scanned-contract.pdf',
            'needs_ocr' => true,
        ]);

        $template = FieldTemplate::query()->create([
            'field_key' => 'governing_law',
            'label' => 'Governing Law',
        ]);

        $response = $this->postJson('/api/v1/ltr/runs/execute', [
            'cells' => [
                [
                    'document_id' => $document->id,
                    'field_template_id' => $template->id,
                    'raw_value' => 'New York',
                    'status' => 'extracted',
                ],
            ],
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Extraction skipped: one or more documents requires OCR before extraction can run.')
            ->assertJsonPath('blocked_document_ids.0', $document->id);

        $this->assertDatabaseMissing('ltr_extracted_fields', [
            'document_id' => $document->id,
        ]);
    }

}
