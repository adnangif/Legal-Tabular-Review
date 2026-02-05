<?php

namespace App\Http\Requests\Ltr;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'run_id' => ['nullable', 'integer', 'exists:ltr_extraction_runs,id'],
            'model_name' => ['nullable', 'string', 'max:255'],
            'prompt_version' => ['nullable', 'string', 'max:255'],
            'overwrite' => ['nullable', 'boolean'],
            'cells' => ['required', 'array'],
            'cells.*.document_id' => ['required', 'integer', 'exists:ltr_documents,id'],
            'cells.*.field_template_id' => ['required', 'integer', 'exists:ltr_field_templates,id'],
            'cells.*.raw_value' => ['nullable'],
            'cells.*.normalized_value' => ['nullable', 'array'],
            'cells.*.confidence' => ['nullable', 'numeric'],
            'cells.*.citation_document_chunk_id' => ['nullable', 'integer', 'exists:ltr_document_chunks,id'],
            'cells.*.citation_page_number' => ['nullable', 'integer', 'min:1'],
            'cells.*.citation_quote' => ['nullable', 'string'],
            'cells.*.evidence_spans' => ['nullable', 'array'],
            'cells.*.status' => ['nullable', 'string', 'max:50'],
        ];
    }
}
