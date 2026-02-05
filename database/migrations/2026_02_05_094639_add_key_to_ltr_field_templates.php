<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ltr_field_templates', function (Blueprint $table) {
            $table->string('key')->nullable()->after('field_key');
        });

        DB::table('ltr_field_templates')
            ->whereNull('key')
            ->update(['key' => DB::raw('field_key')]);

        Schema::table('ltr_field_templates', function (Blueprint $table) {
            $table->unique('key', 'ltr_field_templates_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ltr_field_templates', function (Blueprint $table) {
            $table->dropUnique('ltr_field_templates_key_unique');
            $table->dropColumn('key');
        });
    }
};
