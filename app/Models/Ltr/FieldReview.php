<?php

namespace App\Models\Ltr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldReview extends Model
{
    protected $table = 'ltr_field_reviews';

    protected $fillable = [
        'document_id','field_template_id','extracted_field_id',
        'reviewer_id','final_value','final_normalized_value',
        'review_status','decision','note','reviewed_at','is_current',
    ];

    protected $casts = [
        'final_normalized_value' => 'array',
        'reviewed_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function fieldTemplate(): BelongsTo
    {
        return $this->belongsTo(FieldTemplate::class, 'field_template_id');
    }

    public function extractedField(): BelongsTo
    {
        return $this->belongsTo(ExtractedField::class, 'extracted_field_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
