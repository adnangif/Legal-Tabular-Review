<?php

namespace Database\Seeders;

use App\Models\Ltr\Document;
use App\Models\Ltr\ExtractedField;
use App\Models\Ltr\FieldReview;
use App\Models\Ltr\FieldTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class LtrFieldReviewSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $reviewer = User::query()->firstOrCreate(
            ['email' => 'reviewer@example.com'],
            ['name' => 'LTR Reviewer', 'password' => bcrypt('password')]
        );

        $documents = Document::query()->get()->keyBy('original_filename');
        $templates = FieldTemplate::query()->get()->keyBy('field_key');

        $reviews = [
            [
                'document' => 'alpine-msa.pdf',
                'field' => 'governing_law',
                'review_status' => 'overridden',
                'decision' => 'overridden',
                'final_value' => 'Delaware',
                'final_normalized_value' => ['value' => 'DE'],
                'note' => 'Extractor had long-form value; normalized to state code.',
                'reviewed_at' => Carbon::parse('2026-02-06 09:10:00'),
                'is_current' => true,
            ],
            [
                'document' => 'boreal-nda.pdf',
                'field' => 'confidentiality_term_months',
                'review_status' => 'accepted',
                'decision' => 'accepted',
                'final_value' => '36 months',
                'final_normalized_value' => ['value' => 36],
                'note' => 'Value matches clause text.',
                'reviewed_at' => Carbon::parse('2026-02-06 09:12:00'),
                'is_current' => true,
            ],
            [
                'document' => 'cedar-sow-v2.pdf',
                'field' => 'liability_cap',
                'review_status' => 'rejected',
                'decision' => 'marked_missing',
                'final_value' => null,
                'final_normalized_value' => null,
                'note' => 'Cap clause references MSA; this SOW does not define a standalone cap.',
                'reviewed_at' => Carbon::parse('2026-02-06 09:15:00'),
                'is_current' => true,
            ],
            [
                'document' => 'cedar-sow-v2.pdf',
                'field' => 'auto_renewal',
                'review_status' => 'pending',
                'decision' => 'accepted',
                'final_value' => 'renews annually unless terminated',
                'final_normalized_value' => ['value' => true],
                'note' => 'Needs legal sign-off before final acceptance.',
                'reviewed_at' => null,
                'is_current' => true,
            ],
            [
                'document' => 'alpine-msa.pdf',
                'field' => 'governing_law',
                'review_status' => 'accepted',
                'decision' => 'accepted',
                'final_value' => 'State of Delaware',
                'final_normalized_value' => ['value' => 'DE'],
                'note' => 'Superseded by newer normalization guidance.',
                'reviewed_at' => Carbon::parse('2026-02-05 13:00:00'),
                'is_current' => false,
            ],
        ];

        foreach ($reviews as $review) {
            $document = $documents[$review['document']] ?? null;
            $template = $templates[$review['field']] ?? null;

            if (! $document || ! $template) {
                continue;
            }

            $extractedField = ExtractedField::query()
                ->where('document_id', $document->id)
                ->where('field_template_id', $template->id)
                ->latest('id')
                ->first();

            FieldReview::query()->updateOrCreate(
                [
                    'document_id' => $document->id,
                    'field_template_id' => $template->id,
                    'is_current' => $review['is_current'],
                ],
                [
                    'extracted_field_id' => $extractedField?->id,
                    'reviewer_id' => $reviewer->id,
                    'final_value' => $review['final_value'],
                    'final_normalized_value' => $review['final_normalized_value'],
                    'review_status' => $review['review_status'],
                    'decision' => $review['decision'],
                    'note' => $review['note'],
                    'reviewed_at' => $review['reviewed_at'],
                ]
            );
        }
    }
}
