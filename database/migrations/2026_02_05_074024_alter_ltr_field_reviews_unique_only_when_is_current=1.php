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
        // First drop the old unique constraint if it exists
        Schema::table('ltr_field_reviews', function (Blueprint $table) {
            $table->dropUnique('ltr_unique_current_review');
        });

        // Then add the virtual column and the new unique constraint
        Schema::table('ltr_field_reviews', function (Blueprint $table) {
            $table->string('current_key')
                ->nullable()
                ->virtualAs("CASE WHEN is_current = 1 THEN CONCAT(document_id,'-',field_template_id) ELSE NULL END");
            $table->unique('current_key', 'ltr_unique_current_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ltr_field_reviews', function (Blueprint $table) {
            $table->dropUnique('ltr_unique_current_review');
            $table->dropColumn('current_key');
        });

        Schema::table('ltr_field_reviews', function (Blueprint $table) {
            $table->unique(['document_id', 'field_template_id', 'is_current'], 'ltr_unique_current_review');
        });
    }
};
