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
         Schema::create('ltr_document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('ltr_documents')->cascadeOnDelete();

            $table->string('chunk_uid'); // DOC_001_P7_C1
            $table->unsignedInteger('page_number')->nullable();
            $table->unsignedInteger('chunk_index')->default(0);

            $table->longText('text');

            $table->unsignedInteger('char_start')->nullable();
            $table->unsignedInteger('char_end')->nullable();

            $table->timestamps();

            $table->unique(['document_id', 'chunk_uid']);
            $table->index(['document_id', 'page_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ltr_document_chunks');
    }
};
