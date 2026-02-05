<?php

namespace App\Models\Ltr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractedField extends Model
{
    protected $table = 'ltr_extracted_fields';

    protected $fillable = [
        'document_id','field_template_id','extraction_run_id',
        'raw_value','normalized_value','confidence',
        'citation_document_chunk_id','citation_page_number','citation_quote',
        'evidence_spans','status',
    ];

    protected $casts = [
        'normalized_value' => 'array',
        'evidence_spans' => 'array',
        'confidence' => 'decimal:4',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function fieldTemplate(): BelongsTo
    {
        return $this->belongsTo(FieldTemplate::class, 'field_template_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ExtractionRun::class, 'extraction_run_id');
    }

    public function citationChunk(): BelongsTo
    {
        return $this->belongsTo(DocumentChunk::class, 'citation_document_chunk_id');
    }
}
