<?php

namespace App\Models\Ltr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ExtractionRun extends Model
{
    protected $table = 'ltr_extraction_runs';

    protected $fillable = [
        'run_uid','status','model_name','prompt_version',
        'started_at','completed_at','meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $run): void {
            if (blank($run->run_uid)) {
                $run->run_uid = (string) Str::uuid();
            }

            if (blank($run->status)) {
                $run->status = 'created';
            }
        });
    }

    public function extractedFields(): HasMany
    {
        return $this->hasMany(ExtractedField::class, 'extraction_run_id');
    }
}
