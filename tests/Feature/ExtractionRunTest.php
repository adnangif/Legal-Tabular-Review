<?php

namespace Tests\Feature;

use App\Models\Ltr\ExtractionRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtractionRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_unique_run_uid_when_missing_on_create(): void
    {
        $firstRun = ExtractionRun::create();
        $secondRun = ExtractionRun::create();

        $this->assertNotEmpty($firstRun->run_uid);
        $this->assertNotEmpty($secondRun->run_uid);
        $this->assertNotSame($firstRun->run_uid, $secondRun->run_uid);
    }


    public function test_it_defaults_status_to_created_on_create(): void
    {
        $run = ExtractionRun::create();

        $this->assertSame('created', $run->status);
    }

    public function test_it_keeps_explicit_run_uid_on_create(): void
    {
        $explicitRunUid = 'replay-import-run-001';

        $run = ExtractionRun::create([
            'run_uid' => $explicitRunUid,
        ]);

        $this->assertSame($explicitRunUid, $run->run_uid);
    }
}
