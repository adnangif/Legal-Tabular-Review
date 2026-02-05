<?php

namespace App\Http\Resources\Ltr;

use Illuminate\Http\Resources\Json\JsonResource;

class CitationChunkResponse extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'document' => [
                'id' => $this->document['id'],
                'uuid' => $this->document['uuid'],
                'title' => $this->document['title'],
            ],
            'chunk' => [
                'id' => $this->chunk['id'],
                'chunk_uid' => $this->chunk['chunk_uid'],
                'page_number' => $this->chunk['page_number'],
                'chunk_index' => $this->chunk['chunk_index'],
                'text' => $this->chunk['text'],
            ],
            'context' => [
                'before' => $this->contextBefore,
                'after' => $this->contextAfter,
            ],
        ];
    }
}
