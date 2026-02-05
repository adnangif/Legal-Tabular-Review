<?php

namespace Database\Seeders;

use App\Models\Ltr\ExtractionRun;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class LtrExtractionRunSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        ExtractionRun::query()->updateOrCreate(
            ['run_uid' => 'seed-run-2026-02-06'],
            [
                'status' => 'completed',
                'model_name' => 'gpt-4.1-mini',
                'prompt_version' => 'v1.4',
                'started_at' => Carbon::parse('2026-02-06 08:30:00'),
                'completed_at' => Carbon::parse('2026-02-06 08:32:15'),
                'meta' => [
                    'source' => 'database-seeder',
                    'notes' => 'Synthetic LTR extraction data for local development',
                ],
            ]
        );
    }
}
