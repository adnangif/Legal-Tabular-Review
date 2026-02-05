<?php

namespace Tests\Feature\Ltr;

use App\Models\Ltr\Document;
use App\Models\Ltr\ExtractedField;
use App\Models\Ltr\ExtractionRun;
use App\Models\Ltr\FieldReview;
use App\Models\Ltr\FieldTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExportComparisonParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_and_xlsx_exports_share_identical_canonical_shape_and_filters(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $run = ExtractionRun::query()->create([
            'run_uid' => 'export-parity-run',
            'status' => 'completed',
            'model_name' => 'gpt-5.2-codex',
            'prompt_version' => 'phase4-v1',
        ]);

        $docOne = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Master Agreement',
            'original_filename' => 'master-agreement.pdf',
            'storage_path' => 'docs/master-agreement.pdf',
        ]);

        $docTwo = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => null,
            'original_filename' => 'appendix.pdf',
            'storage_path' => 'docs/appendix.pdf',
        ]);

        $governingLaw = FieldTemplate::query()->create([
            'key' => 'governing_law',
            'field_key' => 'governing_law',
            'label' => 'Governing Law',
            'sort_order' => 1,
        ]);

        FieldTemplate::query()->create([
            'key' => 'payment_terms',
            'field_key' => 'payment_terms',
            'label' => 'Payment Terms',
            'sort_order' => 2,
        ]);

        $docOneExtraction = ExtractedField::query()->create([
            'document_id' => $docOne->id,
            'field_template_id' => $governingLaw->id,
            'extraction_run_id' => $run->id,
            'raw_value' => 'California',
            'confidence' => 0.9500,
            'citation_page_number' => 3,
            'citation_quote' => 'governed by California law',
            'status' => 'extracted',
        ]);

        ExtractedField::query()->create([
            'document_id' => $docTwo->id,
            'field_template_id' => $governingLaw->id,
            'extraction_run_id' => $run->id,
            'raw_value' => 'Nevada',
            'confidence' => 0.4000,
            'citation_page_number' => 7,
            'citation_quote' => 'governed by Nevada law',
            'status' => 'needs_review',
        ]);

        FieldReview::query()->create([
            'document_id' => $docOne->id,
            'field_template_id' => $governingLaw->id,
            'extracted_field_id' => $docOneExtraction->id,
            'reviewer_id' => $user->id,
            'review_status' => 'accepted',
            'decision' => 'accepted',
            'final_value' => 'California',
            'is_current' => true,
        ]);

        $query = http_build_query([
            'document_ids' => [$docOne->id, $docTwo->id],
            'run_id' => $run->id,
            'field_keys' => ['governing_law'],
            'source' => 'review',
            'min_confidence' => 0.8,
            'include_missing' => 0,
        ]);

        $csvResponse = $this->get('/api/v1/ltr/exports/comparison.csv?' . $query);
        $csvResponse->assertOk();

        $xlsxResponse = $this->get('/api/v1/ltr/exports/comparison.xlsx?' . $query);
        $xlsxResponse->assertOk();

        $csvRows = $this->parseCsvRows($csvResponse->streamedContent());
        $xlsxRows = $this->parseXlsxRows($xlsxResponse->baseResponse->getFile()->getPathname());

        $this->assertSame($csvRows[0], $xlsxRows[0]);
        $this->assertSame([
            'Field Key',
            'Field',
            'Conflict',
            'Master Agreement Value',
            'Master Agreement Confidence',
            'Master Agreement Citation Page',
            'Master Agreement Citation Quote',
            'appendix.pdf Value',
            'appendix.pdf Confidence',
            'appendix.pdf Citation Page',
            'appendix.pdf Citation Quote',
        ], $csvRows[0]);

        $this->assertCount(2, $csvRows);
        $this->assertCount(2, $xlsxRows);

        $this->assertSame($this->normalizeTable($csvRows), $this->normalizeTable($xlsxRows));

        $this->assertSame('governing_law', $csvRows[1][0]);
        $this->assertSame('California', $csvRows[1][3]);
        $this->assertSame('0.95', $csvRows[1][4]);
        $this->assertSame('3', $csvRows[1][5]);
        $this->assertSame('governed by California law', $csvRows[1][6]);
        $this->assertSame('', $csvRows[1][7]);
        $this->assertSame('', $csvRows[1][10]);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseCsvRows(string $csvContent): array
    {
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent) ?? $csvContent;

        $rows = [];
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $clean);
        rewind($stream);

        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function parseXlsxRows(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);

        return $sheet->toArray(null, true, true, false);
    }

    /**
     * @param  array<int, array<int, string|null>>  $rows
     * @return array<int, array<int, string>>
     */
    private function normalizeTable(array $rows): array
    {
        return array_map(function (array $row): array {
            return array_map(function ($value): string {
                if ($value === null) {
                    return '';
                }

                return trim((string) $value);
            }, $row);
        }, $rows);
    }
}
