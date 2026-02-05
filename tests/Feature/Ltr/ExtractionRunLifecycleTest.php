<?php

namespace Tests\Feature\Ltr;

use App\Models\Ltr\ExtractionRun;
use App\Services\Ltr\ExtractionRunExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ExtractionRunLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_transitions_run_through_created_running_and_completed_states(): void
    {
        $run = app(ExtractionRunExecutor::class)->execute([
            'model_name' => 'gpt-5.2-codex',
            'prompt_version' => 'phase4-v1',
        ], function (ExtractionRun $run): array {
            $this->assertSame('running', $run->fresh()->status);
            $this->assertNotNull($run->fresh()->started_at);

            return [
                'processed_cells' => 12,
            ];
        });

        $this->assertSame('completed', $run->status);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->completed_at);
        $this->assertSame(12, $run->meta['processed_cells']);
    }

    public function test_it_marks_run_as_failed_and_records_failure_context_when_processing_throws(): void
    {
        $executor = app(ExtractionRunExecutor::class);

        try {
            $executor->execute([], function (): array {
                throw new RuntimeException('Structured extraction failed.');
            });
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Structured extraction failed.', $exception->getMessage());
        }

        $run = ExtractionRun::query()->latest('id')->firstOrFail();

        $this->assertSame('failed', $run->status);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->completed_at);
        $this->assertSame(RuntimeException::class, $run->meta['failure']['exception']);
        $this->assertSame('Structured extraction failed.', $run->meta['failure']['message']);
    }
}
