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
         Schema::create('ltr_extracted_fields', function (Blueprint $table) {
            $table->id();

            $table->foreignId('document_id')->constrained('ltr_documents')->cascadeOnDelete();
            $table->foreignId('field_template_id')->constrained('ltr_field_templates')->cascadeOnDelete();
            $table->foreignId('extraction_run_id')->constrained('ltr_extraction_runs')->cascadeOnDelete();

            $table->longText('raw_value')->nullable();
            $table->json('normalized_value')->nullable();

            $table->decimal('confidence', 5, 4)->nullable(); // 0.0000 - 1.0000

            $table->foreignId('citation_document_chunk_id')
                ->nullable()
                ->constrained('ltr_document_chunks')
                ->nullOnDelete();

            $table->unsignedInteger('citation_page_number')->nullable();
            $table->text('citation_quote')->nullable();

            $table->json('evidence_spans')->nullable(); // multiple spans, offsets, extra quotes

            $table->string('status')->default('extracted'); // extracted, needs_review, ambiguous, missing

            $table->timestamps();

            $table->unique(['document_id', 'field_template_id', 'extraction_run_id'], 'ltr_unique_extract');
            $table->index(['document_id', 'field_template_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ltr_extracted_fields');
    }
};
