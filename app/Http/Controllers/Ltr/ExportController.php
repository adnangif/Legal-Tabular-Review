<?php

namespace App\Http\Controllers\Ltr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ltr\ExportComparisonRequest;
use App\Services\Ltr\ComparisonService;
use App\Services\Ltr\ExportService;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\SheetView;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ExportController extends Controller
{
    public function comparisonCsv(
        ExportComparisonRequest $request,
        ComparisonService $comparisonService,
        ExportService $exportService
        ): StreamedResponse {
            $docIds = collect($request->validated('document_ids'))->map(fn ($v) => (int) $v)->values();
            $runId = $request->validated('run_id');

            $filters = $request->only([
                'field_keys',
                'status',
                'source',
                'min_confidence',
                'only_conflicts',
                'include_missing',
            ]);

            $comparison = $comparisonService->build($docIds, $runId);
            $dataset = $exportService->buildCanonicalComparisonExport($comparison, $filters);

            $filename = 'comparison_' . now()->format('Ymd_His') . '.csv';

            return response()->streamDownload(function () use ($dataset) {
                $out = fopen('php://output', 'w');

                // UTF-8 BOM helps Excel open CSV correctly
                fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

                fputcsv($out, $dataset['header']);

                foreach ($dataset['rows'] as $row) {
                    fputcsv($out, $row);
                }

                fclose($out);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

    public function comparisonXlsx(
        ExportComparisonRequest $request,
        ComparisonService $comparisonService,
        ExportService $exportService
    ) {

    if (!class_exists(Spreadsheet::class)) {
            abort(500, 'PhpSpreadsheet is not installed. Run: composer require phpoffice/phpspreadsheet');
        }

        $docIds = collect($request->validated('document_ids'))->map(fn ($v) => (int) $v)->values();
        $runId = $request->validated('run_id');

        $filters = $request->only([
            'field_keys',
            'status',
            'source',
            'min_confidence',
            'only_conflicts',
            'include_missing',
        ]);

        $comparison = $comparisonService->build($docIds, $runId);
        $dataset = $exportService->buildCanonicalComparisonExport($comparison, $filters);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Comparison');

        // Header row
        $sheet->fromArray($dataset['header'], null, 'A1');

        // Data rows
        $i = 2;
        foreach ($dataset['rows'] as $row) {
            $sheet->fromArray($row, null, 'A' . $i);
            $i++;
        }

        // Basic autosize
        $colCount = count($dataset['header']);
        for ($col = 1; $col <= $colCount; $col++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        $filename = 'comparison_' . now()->format('Ymd_His') . '.xlsx';
        $tmpPath = storage_path('app/tmp_' . Str::random(20) . '.xlsx');

        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, $filename)->deleteFileAfterSend(true);
    }
    public function comparisonWideXlsx(
        ExportComparisonRequest $request,
        ComparisonService $comparisonService,
        ExportService $exportService
    ) {
        if (!class_exists(Spreadsheet::class)) {
            abort(500, 'PhpSpreadsheet is not installed. Run: composer require phpoffice/phpspreadsheet');
        }

        $docIds = collect($request->validated('document_ids'))->map(fn ($v) => (int) $v)->values();
        $runId = $request->validated('run_id');

        $filters = $request->only([
            'field_keys',
            'status',
            'source',
            'min_confidence',
            'only_conflicts',
            'include_missing',
        ]);

        $comparison = $comparisonService->build($docIds, $runId);

        // ✅ wide export payload
        $wide = $exportService->buildWideExport($comparison, $filters);

        $spreadsheet = new Spreadsheet();

        // Sheet 1: Comparison (wide)
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Comparison');

        // Header row
        $sheet1->fromArray($wide['comparison_header'], null, 'A1');

        // Data rows
        $r = 2;
        foreach ($wide['comparison_rows'] as $row) {
            $sheet1->fromArray($row, null, 'A' . $r);
            $r++;
        }

        // Autosize columns for sheet 1
        $colCount = count($wide['comparison_header']);
        for ($i = 1; $i <= $colCount; $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet1->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Sheet 2: Citations
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Citations');

        $sheet2->fromArray($wide['citations_header'], null, 'A1');

        $r = 2;
        foreach ($wide['citations_rows'] as $row) {
            $sheet2->fromArray($row, null, 'A' . $r);
            $r++;
        }

        $colCount2 = count($wide['citations_header']);
        for ($i = 1; $i <= $colCount2; $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet2->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $sheetMeta = $spreadsheet->createSheet();
        $sheetMeta->setTitle('Meta');

        // Write meta header + rows
        $sheetMeta->fromArray($wide['meta_header'], null, 'A1');

        $r = 2;
        foreach ($wide['meta_rows'] as $metaRow) {
            $sheetMeta->fromArray($metaRow, null, 'A' . $r);
            $r++;
        }

        // Hide sheet
        $sheetMeta->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $conflicts = $wide['row_conflicts'] ?? [];
        $comparisonRowCount = count($wide['comparison_rows']);
        $comparisonColCount = count($wide['comparison_header']);

        // Styles
        $styleConflictRow = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FFF2CC'], // light highlight
            ],
        ];

        $styleNeedsReviewCell = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FFE599'],
            ],
        ];

        $styleMissingCell = [
            'font' => [
                'color' => ['rgb' => '999999'],
                'italic' => true,
            ],
        ];

        // Iterate over data cells only (exclude header row 1)
        for ($ri = 0; $ri < $comparisonRowCount; $ri++) {
            $excelRow = $ri + 2; // data starts at row 2
            $isConflict = !empty($conflicts[$ri]);

            // If conflict, highlight whole row across all columns
            if ($isConflict) {
                $lastColLetter = Coordinate::stringFromColumnIndex($comparisonColCount);
                $sheet1->getStyle("A{$excelRow}:{$lastColLetter}{$excelRow}")->applyFromArray($styleConflictRow);
            }

            // Now cell-level styling based on meta status
            // Data columns start at C (1:A FieldKey, 2:B Field, 3+: documents)
            for ($ci = 3; $ci <= $comparisonColCount; $ci++) {
                $colLetter = Coordinate::stringFromColumnIndex($ci);

                // Read meta cell: status|source|confidence
                $metaVal = (string) $sheetMeta->getCell("{$colLetter}{$excelRow}")->getValue();
                $status = strtolower(trim(explode('|', $metaVal)[0] ?? ''));

                if ($status === 'needs_review') {
                    $sheet1->getStyle("{$colLetter}{$excelRow}")->applyFromArray($styleNeedsReviewCell);
                } elseif ($status === 'missing') {
                    $sheet1->getStyle("{$colLetter}{$excelRow}")->applyFromArray($styleMissingCell);
                }
            }
        }



        // Sheet 3: Summary
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Summary');

        $summary = $wide['summary'] ?? [];

        $lines = [
            ['Metric', 'Value'],
            ['Total Fields', $summary['total_fields'] ?? null],
            ['Total Documents', $summary['total_documents'] ?? null],
            ['Total Conflicts', $summary['total_conflicts'] ?? null],
            ['', ''],
            ['Status Counts', ''],
        ];

        $statusCounts = $summary['status_counts'] ?? [];
        foreach ($statusCounts as $k => $v) {
            $lines[] = [$k, $v];
        }

        $lines[] = ['', ''];
        $lines[] = ['Source Counts', ''];

        $sourceCounts = $summary['source_counts'] ?? [];
        foreach ($sourceCounts as $k => $v) {
            $lines[] = [$k, $v];
        }

        $lines[] = ['', ''];
        $lines[] = ['Filters', ''];

        $filters = $summary['filters'] ?? [];
        foreach ($filters as $k => $v) {
            if (is_array($v)) {
                $v = implode(', ', array_map('strval', $v));
            }
            $lines[] = [$k, $v];
        }

        $sheet3->fromArray($lines, null, 'A1');

        // Autosize columns for summary (A and B)
        foreach (['A', 'B'] as $col) {
            $sheet3->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'comparison_wide_' . now()->format('Ymd_His') . '.xlsx';
        $tmpPath = storage_path('app/tmp_' . Str::random(20) . '.xlsx');

        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, $filename)->deleteFileAfterSend(true);

        

    }
}
