<?php

namespace App\Http\Requests\Ltr;

use Illuminate\Foundation\Http\FormRequest;

class ListRunsRequest extends FormRequest
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
        ];
    }
}
