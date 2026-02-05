<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No-op: needs_ocr is owned by
        // 2026_02_05_120000_alter_ltr_documents_add_needs_ocr.php.
    }

    public function down(): void
    {
        // No-op to match up().
    }
};
