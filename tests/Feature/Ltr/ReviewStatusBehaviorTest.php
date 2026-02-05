<?php

namespace Tests\Feature\Ltr;

use App\Models\Ltr\Document;
use App\Models\Ltr\ExtractedField;
use App\Models\Ltr\ExtractionRun;
use App\Models\Ltr\FieldReview;
use App\Models\Ltr\FieldTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewStatusBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_infer_pending_when_extracted_field_has_no_review_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $run = ExtractionRun::query()->create([
            'run_uid' => 'run-pending-default',
            'status' => 'completed',
            'model_name' => 'gpt-5.2-codex',
            'prompt_version' => 'phase4-v1',
        ]);

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Pending Doc',
            'original_filename' => 'pending.pdf',
            'storage_path' => 'docs/pending.pdf',
        ]);

        $template = FieldTemplate::query()->create([
            'key' => 'governing_law',
            'field_key' => 'governing_law',
            'label' => 'Governing Law',
            'sort_order' => 1,
        ]);

        ExtractedField::query()->create([
            'document_id' => $document->id,
            'field_template_id' => $template->id,
            'extraction_run_id' => $run->id,
            'raw_value' => 'California',
            'status' => 'extracted',
        ]);

        $response = $this->getJson('/api/v1/ltr/runs/'.$run->id.'/results?document_ids[]='.$document->id);

        $response
            ->assertOk()
            ->assertJsonPath('documents.0.results.0.status', 'pending')
            ->assertJsonPath('documents.0.results.0.value', 'California');
    }

    public function test_store_review_returns_consistent_review_payload_names(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Reviewed Doc',
            'original_filename' => 'reviewed.pdf',
            'storage_path' => 'docs/reviewed.pdf',
        ]);

        $template = FieldTemplate::query()->create([
            'key' => 'term_months',
            'field_key' => 'term_months',
            'label' => 'Term (Months)',
            'sort_order' => 1,
        ]);

        $response = $this->postJson('/api/v1/ltr/reviews', [
            'document_id' => $document->id,
            'field_template_id' => $template->id,
            'review_status' => 'accepted',
            'final_value' => '24',
            'note' => 'Verified against source',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('review.review_status', 'accepted')
            ->assertJsonPath('review.reviewed_value', '24')
            ->assertJsonPath('review.review_notes', 'Verified against source')
            ->assertJsonPath('review.reviewed_by', $user->id);
    }

    public function test_rejected_review_results_in_missing_resolution(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $run = ExtractionRun::query()->create([
            'run_uid' => 'run-rejected',
            'status' => 'completed',
            'model_name' => 'gpt-5.2-codex',
            'prompt_version' => 'phase4-v1',
        ]);

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Rejected Doc',
            'original_filename' => 'rejected.pdf',
            'storage_path' => 'docs/rejected.pdf',
        ]);

        $template = FieldTemplate::query()->create([
            'key' => 'counterparty_name',
            'field_key' => 'counterparty_name',
            'label' => 'Counterparty Name',
            'sort_order' => 1,
        ]);

        $extracted = ExtractedField::query()->create([
            'document_id' => $document->id,
            'field_template_id' => $template->id,
            'extraction_run_id' => $run->id,
            'raw_value' => 'Acme Inc.',
            'status' => 'needs_review',
        ]);

        FieldReview::query()->create([
            'document_id' => $document->id,
            'field_template_id' => $template->id,
            'extracted_field_id' => $extracted->id,
            'reviewer_id' => $user->id,
            'review_status' => 'rejected',
            'decision' => 'marked_missing',
            'is_current' => true,
        ]);

        $response = $this->getJson('/api/v1/ltr/runs/'.$run->id.'/results?document_ids[]='.$document->id);

        $response
            ->assertOk()
            ->assertJsonPath('documents.0.results.0.status', 'missing')
            ->assertJsonPath('documents.0.results.0.value', null);
    }
}
