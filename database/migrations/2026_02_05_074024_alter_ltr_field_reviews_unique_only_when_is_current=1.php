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
        Schema::table('ltr_field_reviews', function (Blueprint $table) {
            $table->string('current_key')
                ->nullable()
                ->storedAs("CASE WHEN is_current = 1 THEN CONCAT(document_id,'-',field_template_id) ELSE NULL END");
            $table->dropUnique('ltr_unique_current_review');
            $table->unique('current_key', 'ltr_unique_current_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
