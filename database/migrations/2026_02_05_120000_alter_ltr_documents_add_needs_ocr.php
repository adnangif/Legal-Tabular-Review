<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ltr_documents', function (Blueprint $table) {
            $table->boolean('needs_ocr')->default(false)->index()->after('meta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ltr_documents', function (Blueprint $table) {
            $table->dropIndex(['needs_ocr']);
            $table->dropColumn('needs_ocr');
        });
    }
};
