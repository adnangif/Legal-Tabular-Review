<?php

namespace Tests\Unit\Ltr;

use App\Models\Ltr\ExtractedField;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExtractedFieldSoftDeletePolicyTest extends TestCase
{
    public function test_extracted_field_does_not_enable_soft_deletes_for_mvp_idempotency(): void
    {
        $traits = class_uses_recursive(ExtractedField::class);

        $this->assertArrayNotHasKey(
            SoftDeletes::class,
            $traits,
            'ExtractedField should remain hard-delete only unless explicit audit requirements demand soft-deletes.'
        );
    }

    public function test_non_visible_modules_do_not_use_with_trashed_for_extracted_fields(): void
    {
        $directories = [
            app_path('Services/Ltr'),
            app_path('Http/Controllers/Ltr'),
        ];

        foreach ($directories as $directory) {
            foreach (glob($directory . '/*.php') ?: [] as $path) {
                $contents = file_get_contents($path);

                $this->assertFalse(
                    Str::contains($contents, 'withTrashed('),
                    sprintf('Unexpected withTrashed usage found in %s. Prefer hard-delete + upsert for MVP idempotency.', $path)
                );
            }
        }
    }
}
