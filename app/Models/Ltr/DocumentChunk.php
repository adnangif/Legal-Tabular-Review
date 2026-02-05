<?php

namespace App\Models\Ltr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    protected $table = 'ltr_document_chunks';

    protected $fillable = [
        'document_id','chunk_uid','page_number','chunk_index',
        'text','char_start','char_end',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
