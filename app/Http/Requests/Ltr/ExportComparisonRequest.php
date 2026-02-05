<?php

namespace App\Http\Requests\Ltr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['integer', 'distinct', 'exists:ltr_documents,id'],
            'run_id' => ['nullable', 'integer', 'exists:ltr_extraction_runs,id'],

            // Filters
            'field_keys' => ['nullable', 'array'],
            'field_keys.*' => ['string', 'max:255'],

            'status' => ['nullable', Rule::in(['missing', 'needs_review', 'ambiguous', 'extracted', 'approved'])],
            'source' => ['nullable', Rule::in(['review', 'extraction'])],

            'min_confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],

            'only_conflicts' => ['nullable', 'boolean'],
            'include_missing' => ['nullable', 'boolean'],
        ];
    }
}
