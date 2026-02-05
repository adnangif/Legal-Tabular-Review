<?php

namespace App\Http\Requests\Ltr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFieldReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // add policy later
    }

    public function rules(): array
    {
        return [
            'document_id' => ['required', 'integer', 'exists:ltr_documents,id'],
            'field_template_id' => ['required', 'integer', 'exists:ltr_field_templates,id'],
            'extracted_field_id' => ['nullable', 'integer', 'exists:ltr_extracted_fields,id'],
            'review_status' => ['required', Rule::in(['pending', 'accepted', 'overridden', 'rejected'])],
            'final_value' => ['nullable', 'string'],
            'final_normalized_value' => ['nullable', 'array'],

            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $reviewStatus = $this->input('review_status');

            if (in_array($reviewStatus, ['rejected', 'pending'], true)) {
                return; // reviewed value may be null
            }

            if (!filled($this->input('final_value'))) {
                $validator->errors()->add('final_value', 'final_value is required when review_status is accepted or overridden.');
            }
        });
    }
}
