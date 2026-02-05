<?php

namespace App\Models\Ltr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $table = 'ltr_documents';

    protected $fillable = [
        'uuid','title','original_filename','mime_type',
        'storage_disk','storage_path','sha256','source','needs_ocr','meta',
    ];

    protected $casts = [
        'needs_ocr' => 'boolean',
        'meta' => 'array',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class, 'document_id');
    }

    public function extractedFields(): HasMany
    {
        return $this->hasMany(ExtractedField::class, 'document_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(FieldReview::class, 'document_id');
    }
}
