<?php

namespace App\Http\Resources\Ltr;

use Illuminate\Http\Resources\Json\JsonResource;

class FieldRowResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'field_id' => $this->field_id,
            'key' => $this->key,
            'field_key' => $this->field_key,
            'label' => $this->label,
            'type' => $this->type,
            'is_required' => (bool) $this->is_required,
            'cells' => $this->cells, 
        ];
    }
}
