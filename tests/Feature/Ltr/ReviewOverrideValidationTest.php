<?php

namespace Tests\Feature\Ltr;

use App\Models\Ltr\Document;
use App\Models\Ltr\FieldTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewOverrideValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_override_accepts_valid_string_value(): void
    {
        [$document, $template] = $this->makeDocumentAndTemplate([
            'type' => 'string',
            'expected_format' => '/^[A-Za-z ]+$/',
        ]);

        $response = $this->submitOverride($document->id, $template->id, 'Master Service Agreement');

        $response
            ->assertOk()
            ->assertJsonPath('review.review_status', 'overridden')
            ->assertJsonPath('review.reviewed_value', 'Master Service Agreement');
    }

    public function test_override_rejects_invalid_string_value_by_expected_format(): void
    {
        [$document, $template] = $this->makeDocumentAndTemplate([
            'type' => 'string',
            'expected_format' => '/^[A-Za-z ]+$/',
        ]);

        $response = $this->submitOverride($document->id, $template->id, 'MSA-123');

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['final_value']);
    }

    public function test_override_accepts_valid_number_value(): void
    {
        [$document, $template] = $this->makeDocumentAndTemplate([
            'type' => 'number',
            'normalization_rules' => ['min' => 1, 'max' => 60],
        ]);

        $response = $this->submitOverride($document->id, $template->id, '24');

        $response
            ->assertOk()
            ->assertJsonPath('review.reviewed_value', '24');
    }

    public function test_override_rejects_non_numeric_value_for_number_type(): void
    {
        [$document, $template] = $this->makeDocumentAndTemplate([
            'type' => 'number',
            'normalization_rules' => ['min' => 1, 'max' => 60],
        ]);

        $response = $this->submitOverride($document->id, $template->id, 'twenty-four');

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['final_value']);
    }

    public function test_override_accepts_valid_date_value(): void
    {
        [$document, $template] = $this->makeDocumentAndTemplate([
            'type' => 'date',
            'expected_format' => 'Y-m-d',
        ]);

        $response = $this->submitOverride($document->id, $template->id, '2026-02-05');

        $response
            ->assertOk()
            ->assertJsonPath('review.reviewed_value', '2026-02-05');
    }

    public function test_override_rejects_invalid_date_value_by_expected_format(): void
    {
        [$document, $template] = $this->makeDocumentAndTemplate([
            'type' => 'date',
            'expected_format' => 'Y-m-d',
        ]);

        $response = $this->submitOverride($document->id, $template->id, '02/05/2026');

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['final_value']);
    }

    public function test_override_accepts_valid_enum_value(): void
    {
        [$document, $template] = $this->makeDocumentAndTemplate([
            'type' => 'enum',
            'normalization_rules' => ['allowed_values' => ['annual', 'monthly']],
        ]);

        $response = $this->submitOverride($document->id, $template->id, 'annual');

        $response
            ->assertOk()
            ->assertJsonPath('review.reviewed_value', 'annual');
    }

    public function test_override_rejects_value_outside_enum_membership(): void
    {
        [$document, $template] = $this->makeDocumentAndTemplate([
            'type' => 'enum',
            'normalization_rules' => ['allowed_values' => ['annual', 'monthly']],
        ]);

        $response = $this->submitOverride($document->id, $template->id, 'weekly');

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['final_value']);
    }

    private function makeDocumentAndTemplate(array $templateAttributes): array
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Validation Doc',
            'original_filename' => 'validation.pdf',
            'storage_path' => 'docs/validation.pdf',
        ]);

        $template = FieldTemplate::query()->create(array_merge([
            'key' => 'test_key_'.Str::random(6),
            'field_key' => 'test_key_'.Str::random(6),
            'label' => 'Validation Field',
            'sort_order' => 1,
            'type' => 'string',
            'expected_format' => null,
            'normalization_rules' => null,
        ], $templateAttributes));

        return [$document, $template];
    }

    private function submitOverride(int $documentId, int $fieldTemplateId, string $finalValue)
    {
        return $this->postJson('/api/v1/ltr/reviews', [
            'document_id' => $documentId,
            'field_template_id' => $fieldTemplateId,
            'review_status' => 'overridden',
            'final_value' => $finalValue,
        ]);
    }
}
