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
        Schema::create('ltr_extraction_runs', function (Blueprint $table) {
            $table->id();

            $table->string('run_uid')->unique(); // stable id for a run
            $table->string('status')->default('queued'); // queued, running, completed, failed

            $table->string('model_name')->nullable();
            $table->string('prompt_version')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ltr_extraction_runs');
    }
};
