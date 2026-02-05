<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ltr_field_reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('ltr_field_reviews', 'review_status')) {
                $table->enum('review_status', ['pending', 'accepted', 'overridden', 'rejected'])
                    ->default('pending')
                    ->after('final_normalized_value');
            }
        });

        if (Schema::hasColumn('ltr_field_reviews', 'decision')) {
            DB::table('ltr_field_reviews')
                ->where('decision', 'accepted')
                ->update(['review_status' => 'accepted']);

            DB::table('ltr_field_reviews')
                ->where('decision', 'overridden')
                ->update(['review_status' => 'overridden']);

            DB::table('ltr_field_reviews')
                ->where('decision', 'marked_missing')
                ->update(['review_status' => 'rejected']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ltr_field_reviews', function (Blueprint $table) {
            if (Schema::hasColumn('ltr_field_reviews', 'review_status')) {
                $table->dropColumn('review_status');
            }
        });
    }
};
