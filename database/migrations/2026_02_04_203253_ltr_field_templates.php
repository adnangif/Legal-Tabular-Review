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
        Schema::create('ltr_field_templates', function (Blueprint $table) {
            $table->id();

            $table->string('field_key')->unique(); // governing_law
            $table->string('label');              // Governing Law
            $table->string('type')->default('string');

            $table->string('expected_format')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('allow_multiple')->default(false);

            $table->json('normalization_rules')->nullable();
            $table->json('extraction_hints')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ltr_field_templates');
    }
};
