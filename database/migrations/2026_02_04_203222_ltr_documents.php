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
         Schema::create('ltr_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('title')->nullable();
            $table->string('original_filename');
            $table->string('mime_type')->nullable();

            $table->string('storage_disk')->default('private');
            $table->string('storage_path');

            $table->string('sha256', 64)->nullable()->index();
            $table->string('source')->default('upload'); // upload, email, api

            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ltr_documents');
    }
};
