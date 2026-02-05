<?php

namespace Tests\Feature\Ltr;

use App\Models\Ltr\Document;
use App\Models\Ltr\ExtractedField;
use App\Models\Ltr\ExtractionRun;
use App\Models\Ltr\FieldReview;
use App\Models\Ltr\FieldTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewWebUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_review_submission_redirects_back_with_flash_and_persists_review(): void
    {
        $run = ExtractionRun::query()->create([
            'run_uid' => 'review-web-run',
            'status' => 'completed',
            'model_name' => 'gpt-5.2-codex',
            'prompt_version' => 'phase4-v1',
        ]);

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Review Doc',
            'original_filename' => 'review-doc.pdf',
            'storage_path' => 'docs/review-doc.pdf',
        ]);

        $template = FieldTemplate::query()->create([
            'key' => 'renewal_term',
            'field_key' => 'renewal_term',
            'label' => 'Renewal Term',
            'sort_order' => 1,
        ]);

        $extracted = ExtractedField::query()->create([
            'document_id' => $document->id,
            'field_template_id' => $template->id,
            'extraction_run_id' => $run->id,
            'raw_value' => '12 months',
            'confidence' => 0.91,
            'status' => 'extracted',
        ]);

        $response = $this->from('/ltr/table?document_ids[]='.$document->id.'&run_id='.$run->id)
            ->post('/ltr/reviews', [
                'document_id' => $document->id,
                'field_template_id' => $template->id,
                'extracted_field_id' => $extracted->id,
                'review_status' => 'accepted',
                'final_value' => '12 months',
                'note' => 'Looks correct',
            ]);

        $response->assertRedirect('/ltr/table?document_ids[]='.$document->id.'&run_id='.$run->id);

        $followed = $this->get('/ltr/table?document_ids[]='.$document->id.'&run_id='.$run->id);
        $followed->assertOk();
        $followed->assertSee('Review saved');

        $this->assertDatabaseHas((new FieldReview())->getTable(), [
            'document_id' => $document->id,
            'field_template_id' => $template->id,
            'review_status' => 'accepted',
            'final_value' => '12 months',
            'is_current' => 1,
        ]);
    }
}
