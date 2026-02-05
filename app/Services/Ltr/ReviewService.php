<?php

namespace App\Services\Ltr;

use App\Models\Ltr\ExtractedField;
use App\Models\Ltr\FieldTemplate;
use App\Models\Ltr\FieldReview;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function __construct(
        private readonly TemplateValueValidationService $templateValueValidationService,
    ) {
    }

    public function upsertCurrentReview(array $data, ?int $reviewerId)
    {
        return DB::transaction(function () use ($data, $reviewerId) {
            // Optional safety: ensure extracted_field matches document+field when provided
            if (!empty($data['extracted_field_id'])) {
                $ext = ExtractedField::query()->findOrFail($data['extracted_field_id']);

                if ((int) $ext->document_id !== (int) $data['document_id'] || (int) $ext->field_template_id !== (int) $data['field_template_id']) {
                    abort(422, 'extracted_field_id does not match document_id and field_template_id');
                }
            }

            if (in_array($data['review_status'], ['accepted', 'overridden'], true)) {
                $template = FieldTemplate::query()->findOrFail($data['field_template_id']);

                $this->templateValueValidationService->validateReviewValue(
                    $template,
                    $data['final_value'] ?? null,
                    $data['final_normalized_value'] ?? null,
                );
            }

            // Make any existing current review non-current
            FieldReview::query()
                ->where('document_id', $data['document_id'])
                ->where('field_template_id', $data['field_template_id'])
                ->where('is_current', true)
                ->update(['is_current' => false]);

            // Insert new current review
            return FieldReview::query()->create([
                'document_id' => $data['document_id'],
                'field_template_id' => $data['field_template_id'],
                'extracted_field_id' => $data['extracted_field_id'] ?? null,

                'reviewer_id' => $reviewerId,

                'final_value' => in_array($data['review_status'], ['rejected', 'pending'], true) ? null : ($data['final_value'] ?? null),
                'final_normalized_value' => in_array($data['review_status'], ['rejected', 'pending'], true) ? null : ($data['final_normalized_value'] ?? null),

                'review_status' => $data['review_status'],
                'decision' => $data['review_status'] === 'rejected' ? 'marked_missing' : $data['review_status'],
                'note' => $data['note'] ?? null,
                'reviewed_at' => now(),
                'is_current' => true,
            ]);
        });
    }
}
