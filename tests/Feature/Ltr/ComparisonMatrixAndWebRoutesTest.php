<?php

namespace Tests\Feature\Ltr;

use App\Models\Ltr\Document;
use App\Models\Ltr\DocumentChunk;
use App\Models\Ltr\ExtractedField;
use App\Models\Ltr\ExtractionRun;
use App\Models\Ltr\FieldReview;
use App\Models\Ltr\FieldTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComparisonMatrixAndWebRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_matrix_cells_expose_required_rendering_contract_keys(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $run = ExtractionRun::query()->create([
            'run_uid' => 'comparison-matrix-run',
            'status' => 'completed',
            'model_name' => 'gpt-5.2-codex',
            'prompt_version' => 'phase4-v1',
        ]);

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Contract A',
            'original_filename' => 'contract-a.pdf',
            'storage_path' => 'docs/contract-a.pdf',
        ]);

        $template = FieldTemplate::query()->create([
            'key' => 'governing_law',
            'field_key' => 'governing_law',
            'label' => 'Governing Law',
            'sort_order' => 1,
        ]);

        $chunk = DocumentChunk::query()->create([
            'document_id' => $document->id,
            'chunk_uid' => 'CONTRACT_A_CHUNK_1',
            'chunk_index' => 0,
            'page_number' => 2,
            'text' => 'This agreement is governed by California law.',
        ]);

        $extracted = ExtractedField::query()->create([
            'document_id' => $document->id,
            'field_template_id' => $template->id,
            'extraction_run_id' => $run->id,
            'raw_value' => 'California',
            'confidence' => 0.88,
            'citation_document_chunk_id' => $chunk->id,
            'citation_page_number' => 2,
            'citation_quote' => 'governed by California law',
            'status' => 'extracted',
        ]);

        FieldReview::query()->create([
            'document_id' => $document->id,
            'field_template_id' => $template->id,
            'extracted_field_id' => $extracted->id,
            'review_status' => 'accepted',
            'decision' => 'accepted',
            'final_value' => 'California',
            'is_current' => true,
        ]);

        $response = $this->getJson('/api/v1/ltr/comparison?document_ids[]='.$document->id.'&run_id='.$run->id);

        $response->assertOk();

        $cell = $response->json('fields.0.cells.'.$document->id);
        $this->assertIsArray($cell);

        foreach (['value', 'resolved_value', 'confidence', 'citation', 'status', 'review'] as $key) {
            $this->assertArrayHasKey($key, $cell);
        }

        $this->assertSame(['chunk_id', 'page', 'chunk_uid', 'quote'], array_keys($cell['citation']));
        $this->assertArrayHasKey('review_status', $cell['review']);
        $this->assertSame('California', $cell['value']);
        $this->assertSame('California', $cell['resolved_value']);
        $this->assertSame('approved', $cell['status']);
    }

    public function test_web_export_routes_return_downloadable_csv_and_xlsx_content_types(): void
    {
        $run = ExtractionRun::query()->create([
            'run_uid' => 'web-export-run',
            'status' => 'completed',
            'model_name' => 'gpt-5.2-codex',
            'prompt_version' => 'phase4-v1',
        ]);

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Export Source Doc',
            'original_filename' => 'export-source.pdf',
            'storage_path' => 'docs/export-source.pdf',
        ]);

        $template = FieldTemplate::query()->create([
            'key' => 'payment_terms',
            'field_key' => 'payment_terms',
            'label' => 'Payment Terms',
            'sort_order' => 1,
        ]);

        ExtractedField::query()->create([
            'document_id' => $document->id,
            'field_template_id' => $template->id,
            'extraction_run_id' => $run->id,
            'raw_value' => 'Net 30',
            'confidence' => 0.91,
            'status' => 'extracted',
        ]);

        $query = '?document_ids[]='.$document->id.'&run_id='.$run->id;

        $csv = $this->get('/ltr/exports/comparison.csv'.$query);
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $csv->headers->get('Content-Disposition'));

        $xlsx = $this->get('/ltr/exports/comparison.xlsx'.$query);
        $xlsx->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $xlsx->headers->get('Content-Type')
        );

        $wideXlsx = $this->get('/ltr/exports/wide.xlsx'.$query);
        $wideXlsx->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $wideXlsx->headers->get('Content-Type')
        );
    }



    public function test_citation_link_from_table_resolves_to_chunk_detail_page(): void
    {
        $run = ExtractionRun::query()->create([
            'run_uid' => 'citation-link-run',
            'status' => 'completed',
            'model_name' => 'gpt-5.2-codex',
            'prompt_version' => 'phase4-v1',
        ]);

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Citation Doc',
            'original_filename' => 'citation-doc.pdf',
            'storage_path' => 'docs/citation-doc.pdf',
        ]);

        $template = FieldTemplate::query()->create([
            'key' => 'effective_date',
            'field_key' => 'effective_date',
            'label' => 'Effective Date',
            'sort_order' => 1,
        ]);

        $chunk = DocumentChunk::query()->create([
            'document_id' => $document->id,
            'chunk_uid' => 'CITATION_DOC_CHUNK_1',
            'chunk_index' => 0,
            'page_number' => 1,
            'text' => 'Effective date is January 1, 2026.',
        ]);

        ExtractedField::query()->create([
            'document_id' => $document->id,
            'field_template_id' => $template->id,
            'extraction_run_id' => $run->id,
            'raw_value' => '2026-01-01',
            'confidence' => 0.95,
            'citation_document_chunk_id' => $chunk->id,
            'citation_page_number' => 1,
            'citation_quote' => 'Effective date is January 1, 2026.',
            'status' => 'extracted',
        ]);

        $tableResponse = $this->get('/ltr/table?document_ids[]='.$document->id.'&run_id='.$run->id);
        $tableResponse->assertOk();

        $tableResponse->assertSee('/ltr/citations/chunk/'.$chunk->id, false);

        $this->get('/ltr/citations/chunk/'.$chunk->id)->assertOk();
    }

    public function test_optional_web_route_smoke_checks_for_ltr_pages_and_chunk_route(): void
    {
        $run = ExtractionRun::query()->create([
            'run_uid' => 'web-smoke-run',
            'status' => 'completed',
            'model_name' => 'gpt-5.2-codex',
            'prompt_version' => 'phase4-v1',
        ]);

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Web Smoke Doc',
            'original_filename' => 'web-smoke.pdf',
            'storage_path' => 'docs/web-smoke.pdf',
        ]);

        $template = FieldTemplate::query()->create([
            'key' => 'term_months',
            'field_key' => 'term_months',
            'label' => 'Term (Months)',
            'sort_order' => 1,
        ]);

        ExtractedField::query()->create([
            'document_id' => $document->id,
            'field_template_id' => $template->id,
            'extraction_run_id' => $run->id,
            'raw_value' => '24',
            'confidence' => 0.77,
            'status' => 'extracted',
        ]);

        $chunk = DocumentChunk::query()->create([
            'document_id' => $document->id,
            'chunk_uid' => 'WEB_SMOKE_CHUNK_1',
            'chunk_index' => 0,
            'page_number' => 1,
            'text' => 'Term is 24 months.',
        ]);

        FieldReview::query()->create([
            'document_id' => $document->id,
            'field_template_id' => $template->id,
            'review_status' => 'accepted',
            'decision' => 'accepted',
            'final_value' => '24',
            'is_current' => true,
        ]);

        $this->get('/ltr')->assertOk();
        $this->get('/ltr/table?document_ids[]='.$document->id.'&run_id='.$run->id)->assertOk();
        $this->get('/ltr/reviews/current?document_id='.$document->id.'&field_template_id='.$template->id)->assertOk();
        $this->get('/ltr/reviews/history?document_id='.$document->id.'&field_template_id='.$template->id)->assertOk();
        $this->get('/ltr/citations/chunk/'.$chunk->id)->assertOk();
    }
}
