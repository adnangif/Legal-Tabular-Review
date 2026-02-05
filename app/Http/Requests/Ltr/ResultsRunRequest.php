<?php

namespace App\Http\Requests\Ltr;

use Illuminate\Foundation\Http\FormRequest;

class ResultsRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_ids' => ['nullable', 'array', 'min:1'],
            'document_ids.*' => ['integer', 'distinct', 'exists:ltr_documents,id'],
        ];
    }
}
