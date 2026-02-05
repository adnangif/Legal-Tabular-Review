<?php

namespace App\Http\Requests\Ltr;

use Illuminate\Foundation\Http\FormRequest;

class ReviewCellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_id' => ['required', 'integer', 'exists:ltr_documents,id'],
            'field_template_id' => ['required', 'integer', 'exists:ltr_field_templates,id'],
        ];
    }
}
