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
         Schema::create('ltr_field_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('ltr_documents')->cascadeOnDelete();
            $table->foreignId('field_template_id')->constrained('ltr_field_templates')->cascadeOnDelete();
            $table->foreignId('extracted_field_id')->nullable()->constrained('ltr_extracted_fields')->nullOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->longText('final_value')->nullable();
            $table->json('final_normalized_value')->nullable();
            $table->string('decision'); // three value-> accepted, overridden, marked_missing
            $table->text('note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->index(['document_id', 'field_template_id']);
            $table->index(['reviewer_id']);
            $table->unique(['document_id', 'field_template_id', 'is_current'], 'ltr_unique_current_review');
            $table->timestamps();
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
