<?php

namespace App\Models\Ltr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldTemplate extends Model
{
    protected $table = 'ltr_field_templates';

    protected $fillable = [
        'key','field_key','label','type','expected_format','is_required',
        'allow_multiple','normalization_rules','extraction_hints','sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'allow_multiple' => 'boolean',
        'normalization_rules' => 'array',
        'extraction_hints' => 'array',
    ];

    public function extractedFields(): HasMany
    {
        return $this->hasMany(ExtractedField::class, 'field_template_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(FieldReview::class, 'field_template_id');
    }

    public function getKeyAttribute(?string $value): ?string
    {
        return $value ?? ($this->attributes['field_key'] ?? null);
    }

    public function setKeyAttribute(?string $value): void
    {
        $this->attributes['key'] = $value;
        $this->attributes['field_key'] = $value;
    }

    public function setFieldKeyAttribute(?string $value): void
    {
        $this->attributes['field_key'] = $value;

        if (!array_key_exists('key', $this->attributes) || $this->attributes['key'] === null) {
            $this->attributes['key'] = $value;
        }
    }
}
