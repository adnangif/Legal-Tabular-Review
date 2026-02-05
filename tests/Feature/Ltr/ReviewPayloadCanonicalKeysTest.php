<?php

namespace Tests\Feature\Ltr;

use App\Models\Ltr\Document;
use App\Models\Ltr\FieldReview;
use App\Models\Ltr\FieldTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewPayloadCanonicalKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_and_history_responses_return_canonical_keys_with_null_reviewer_reason(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Legacy Reviewer Doc',
            'original_filename' => 'legacy-reviewer.pdf',
            'storage_path' => 'docs/legacy-reviewer.pdf',
        ]);

        $template = FieldTemplate::query()->create([
            'key' => 'renewal_term',
            'field_key' => 'renewal_term',
            'label' => 'Renewal Term',
            'sort_order' => 1,
        ]);

        FieldReview::query()->create([
            'document_id' => $document->id,
            'field_template_id' => $template->id,
            'review_status' => 'accepted',
            'decision' => 'accepted',
            'final_value' => '12 months',
            'note' => 'Imported historical review',
            'reviewer_id' => null,
            'is_current' => true,
            'reviewed_at' => now(),
        ]);

        $query = [
            'document_id' => $document->id,
            'field_template_id' => $template->id,
        ];

        $currentResponse = $this->getJson('/api/v1/ltr/reviews/current?'.http_build_query($query));
        $currentResponse->assertOk();

        $historyResponse = $this->getJson('/api/v1/ltr/reviews/history?'.http_build_query($query));
        $historyResponse->assertOk();

        $currentReview = $currentResponse->json('review');
        $historyReview = $historyResponse->json('history.0');

        foreach ([$currentReview, $historyReview] as $review) {
            $this->assertArrayHasKey('review_status', $review);
            $this->assertArrayHasKey('reviewed_value', $review);
            $this->assertArrayHasKey('review_notes', $review);
            $this->assertArrayHasKey('reviewed_by', $review);
            $this->assertArrayHasKey('reviewed_at', $review);
            $this->assertArrayHasKey('reviewed_by_null_reason', $review);
            $this->assertArrayHasKey('reviewer_id', $review);
            $this->assertArrayHasKey('note', $review);
        }

        $currentResponse
            ->assertJsonPath('review.reviewed_by', null)
            ->assertJsonPath('review.reviewed_by_null_reason', 'review created without an authenticated reviewer context');

        $historyResponse
            ->assertJsonPath('history.0.reviewed_by', null)
            ->assertJsonPath('history.0.reviewed_by_null_reason', 'review created without an authenticated reviewer context');
    }
}
