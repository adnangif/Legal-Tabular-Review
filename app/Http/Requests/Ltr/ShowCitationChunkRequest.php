<?php

namespace App\Http\Requests\Ltr;

use Illuminate\Foundation\Http\FormRequest;

class ShowCitationChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // add policy later
    }

    public function rules(): array
    {
        return [
            'document_id' => ['required', 'integer', 'exists:ltr_documents,id'],
            'chunk_uid' => ['required', 'string', 'max:255'],
            'context' => ['nullable', 'integer', 'min:0', 'max:5'],
        ];
    }
}
