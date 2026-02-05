<?php

namespace App\Http\Resources\Ltr;

use Illuminate\Http\Resources\Json\JsonResource;

class ComparisonResponse extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'meta' => [
                'run_id' => $this->run->id,
                'run_uid' => $this->run->run_uid,
                'documents' => $this->documents->map(fn ($d) => [
                    'id' => $d->id,
                    'uuid' => $d->uuid,
                    'title' => $d->title ?: $d->original_filename,
                ])->values(),
                'generated_at' => now()->toIso8601String(),
            ],
            'fields' => FieldRowResource::collection($this->fields),
        ];
    }
}
