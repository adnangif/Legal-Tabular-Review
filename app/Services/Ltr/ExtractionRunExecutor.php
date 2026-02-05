<?php

namespace App\Services\Ltr;

use App\Models\Ltr\ExtractionRun;
use Throwable;

class ExtractionRunExecutor
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  callable(ExtractionRun):array<string, mixed>|null  $processor
     */
    public function execute(array $attributes, callable $processor): ExtractionRun
    {
        $run = ExtractionRun::query()->create(array_merge([
            'status' => 'created',
        ], $attributes));

        $run->forceFill([
            'status' => 'running',
            'started_at' => now(),
        ])->save();

        try {
            $resultMeta = $processor($run);

            $run->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'meta' => $this->completedMeta($run, $resultMeta),
            ])->save();
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'completed_at' => now(),
                'meta' => $this->failureMeta($run, $exception),
            ])->save();

            throw $exception;
        }

        return $run->fresh();
    }

    /**
     * @param  array<string, mixed>|null  $resultMeta
     * @return array<string, mixed>
     */
    private function completedMeta(ExtractionRun $run, ?array $resultMeta): array
    {
        return array_merge($run->meta ?? [], $resultMeta ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function failureMeta(ExtractionRun $run, Throwable $exception): array
    {
        return array_merge($run->meta ?? [], [
            'failure' => [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ],
        ]);
    }
}
