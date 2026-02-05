<?php

namespace Tests\Feature\Ltr;

use App\Models\Ltr\Document;
use App\Models\Ltr\ExtractionRun;
use App\Models\Ltr\FieldTemplate;
use App\Services\Ltr\RunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunServiceStatsMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_stats_to_run_meta_while_preserving_other_meta_keys(): void
    {
        $run = ExtractionRun::query()->create([
            'meta' => [
                'source' => 'initial-seed',
            ],
        ]);

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'original_filename' => 'contract.pdf',
            'storage_path' => 'contracts/contract.pdf',
        ]);

        $templateA = FieldTemplate::query()->create([
            'field_key' => 'governing_law',
            'label' => 'Governing Law',
        ]);

        $templateB = FieldTemplate::query()->create([
            'field_key' => 'effective_date',
            'label' => 'Effective Date',
        ]);

        app(RunService::class)->persistExtractedFields($run->id, [
            [
                'document_id' => $document->id,
                'field_template_id' => $templateA->id,
                'raw_value' => null,
                'status' => 'missing',
            ],
            [
                'document_id' => $document->id,
                'field_template_id' => $templateB->id,
                'raw_value' => '2026-01-01',
                'status' => 'extracted',
            ],
        ]);

        $run = $run->fresh();

        $this->assertSame('initial-seed', $run->meta['source']);
        $this->assertIsArray($run->meta['stats']);
        $this->assertArrayHasKey('stats_updated_at', $run->meta);
        $this->assertSame([
            'total_fields' => 2,
            'missing' => 1,
            'needs_review' => 0,
            'ambiguous' => 0,
            'extracted' => 1,
            'documents_covered' => 1,
        ], $run->meta['stats']);
    }

    public function test_it_recomputes_meta_stats_on_overwrite_rerun_idempotently(): void
    {
        $run = ExtractionRun::query()->create([
            'meta' => [
                'source' => 'rerun-test',
                'stats' => ['total_fields' => 99],
            ],
        ]);

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'original_filename' => 'contract.pdf',
            'storage_path' => 'contracts/contract.pdf',
        ]);

        $template = FieldTemplate::query()->create([
            'field_key' => 'governing_law',
            'label' => 'Governing Law',
        ]);

        $service = app(RunService::class);

        $service->persistExtractedFields($run->id, [
            [
                'document_id' => $document->id,
                'field_template_id' => $template->id,
                'raw_value' => null,
                'status' => 'missing',
            ],
        ], overwrite: true);

        $firstStats = $run->fresh()->meta['stats'];

        $service->persistExtractedFields($run->id, [
            [
                'document_id' => $document->id,
                'field_template_id' => $template->id,
                'raw_value' => 'New York',
                'status' => 'extracted',
            ],
        ], overwrite: true);

        $run = $run->fresh();

        $this->assertSame('rerun-test', $run->meta['source']);
        $this->assertNotSame($firstStats, $run->meta['stats']);
        $this->assertSame([
            'total_fields' => 1,
            'missing' => 0,
            'needs_review' => 0,
            'ambiguous' => 0,
            'extracted' => 1,
            'documents_covered' => 1,
        ], $run->meta['stats']);
    }
}
