<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE ltr_extraction_runs MODIFY status VARCHAR(255) NOT NULL DEFAULT 'created'");
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE ltr_extraction_runs ALTER COLUMN status SET DEFAULT 'created'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE ltr_extraction_runs MODIFY status VARCHAR(255) NOT NULL DEFAULT 'queued'");
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE ltr_extraction_runs ALTER COLUMN status SET DEFAULT 'queued'");
        }
    }
};
