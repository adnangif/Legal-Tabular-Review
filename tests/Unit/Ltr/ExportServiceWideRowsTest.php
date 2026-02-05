<?php

namespace Tests\Unit\Ltr;

use App\Services\Ltr\ExportService;
use Tests\TestCase;

class ExportServiceWideRowsTest extends TestCase
{
    public function test_build_wide_export_emits_exactly_one_aligned_row_per_field(): void
    {
        $service = new ExportService();

        $comparisonPayload = [
            'documents' => [
                (object) ['id' => 101, 'title' => 'Doc Alpha', 'original_filename' => 'alpha.pdf'],
                (object) ['id' => 202, 'title' => 'Doc Beta', 'original_filename' => 'beta.pdf'],
                (object) ['id' => 303, 'title' => null, 'original_filename' => 'gamma.pdf'],
            ],
            'fields' => [
                (object) [
                    'field_key' => 'governing_law',
                    'label' => 'Governing Law',
                    'cells' => [
                        '101' => [
                            'value' => 'California',
                            'status' => 'approved',
                            'source' => 'review',
                            'confidence' => 0.98,
                        ],
                        '202' => [
                            'value' => 'Nevada',
                            'status' => 'needs_review',
                            'source' => 'extraction',
                            'confidence' => 0.55,
                        ],
                        '303' => [
                            'value' => null,
                            'status' => 'missing',
                            'source' => 'extraction',
                            'confidence' => null,
                        ],
                    ],
                ],
                (object) [
                    'field_key' => 'term_months',
                    'label' => 'Term (Months)',
                    'cells' => [
                        '101' => [
                            'value' => '12',
                            'status' => 'approved',
                            'source' => 'review',
                            'confidence' => 0.92,
                        ],
                        '202' => [
                            'value' => '12',
                            'status' => 'approved',
                            'source' => 'review',
                            'confidence' => 0.89,
                        ],
                        '303' => [
                            'value' => '12',
                            'status' => 'approved',
                            'source' => 'review',
                            'confidence' => 0.90,
                        ],
                    ],
                ],
            ],
        ];

        $wide = $service->buildWideExport($comparisonPayload);

        $this->assertCount(2, $wide['comparison_rows']);
        $this->assertCount(2, $wide['meta_rows']);
        $this->assertCount(2, $wide['row_conflicts']);

        foreach ($wide['comparison_rows'] as $index => $comparisonRow) {
            $this->assertSame(
                $wide['comparison_rows'][$index][0],
                $wide['meta_rows'][$index][0],
                'Field key should align between comparison and meta rows at index '.$index
            );
            $this->assertSame(
                $wide['comparison_rows'][$index][1],
                $wide['meta_rows'][$index][1],
                'Field label should align between comparison and meta rows at index '.$index
            );
            $this->assertCount(5, $comparisonRow);
            $this->assertCount(5, $wide['meta_rows'][$index]);
        }

        $this->assertSame([true, false], $wide['row_conflicts']);
        $this->assertSame('California', $wide['comparison_rows'][0][2]);
        $this->assertStringStartsWith('status:needs_review', $wide['meta_rows'][0][3]);
        $this->assertStringStartsWith('status:missing', $wide['meta_rows'][0][4]);
    }

    public function test_build_wide_export_row_alignment_is_stable_when_filters_blank_values(): void
    {
        $service = new ExportService();

        $comparisonPayload = [
            'documents' => [
                (object) ['id' => 1, 'title' => 'Doc One', 'original_filename' => 'one.pdf'],
                (object) ['id' => 2, 'title' => 'Doc Two', 'original_filename' => 'two.pdf'],
            ],
            'fields' => [
                (object) [
                    'field_key' => 'amount',
                    'label' => 'Amount',
                    'cells' => [
                        '1' => [
                            'value' => '$1,000',
                            'status' => 'approved',
                            'source' => 'review',
                            'confidence' => 0.95,
                        ],
                        '2' => [
                            'value' => '$900',
                            'status' => 'needs_review',
                            'source' => 'extraction',
                            'confidence' => 0.49,
                        ],
                    ],
                ],
                (object) [
                    'field_key' => 'currency',
                    'label' => 'Currency',
                    'cells' => [
                        '1' => [
                            'value' => 'USD',
                            'status' => 'approved',
                            'source' => 'review',
                            'confidence' => 0.99,
                        ],
                        '2' => [
                            'value' => 'USD',
                            'status' => 'approved',
                            'source' => 'review',
                            'confidence' => 0.99,
                        ],
                    ],
                ],
            ],
        ];

        $wide = $service->buildWideExport($comparisonPayload, [
            'status' => 'approved',
            'source' => 'review',
            'min_confidence' => 0.9,
        ]);

        $this->assertCount(2, $wide['comparison_rows']);
        $this->assertCount(2, $wide['meta_rows']);
        $this->assertCount(2, $wide['row_conflicts']);

        // Row 0 is still the first field and remains conflict-marked even if one doc value is filtered to null.
        $this->assertSame('amount', $wide['comparison_rows'][0][0]);
        $this->assertSame('Amount', $wide['meta_rows'][0][1]);
        $this->assertTrue($wide['row_conflicts'][0]);
        $this->assertNull($wide['comparison_rows'][0][3]);
        $this->assertStringStartsWith('status:needs_review', $wide['meta_rows'][0][3]);

        // Row 1 alignment and conflict parity
        $this->assertSame('currency', $wide['comparison_rows'][1][0]);
        $this->assertSame('Currency', $wide['meta_rows'][1][1]);
        $this->assertFalse($wide['row_conflicts'][1]);
    }
}
