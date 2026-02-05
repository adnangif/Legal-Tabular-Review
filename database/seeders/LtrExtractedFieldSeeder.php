<?php

namespace Database\Seeders;

use App\Models\Ltr\Document;
use App\Models\Ltr\ExtractedField;
use App\Models\Ltr\ExtractionRun;
use App\Models\Ltr\FieldTemplate;
use Illuminate\Database\Seeder;

class LtrExtractedFieldSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $run = ExtractionRun::query()->where('run_uid', 'seed-run-2026-02-06')->firstOrFail();

        $documents = Document::query()
            ->whereIn('original_filename', ['alpine-msa.pdf', 'boreal-nda.pdf', 'cedar-sow-v2.pdf'])
            ->get()
            ->keyBy('original_filename');

        $templates = FieldTemplate::query()->get()->keyBy('field_key');

        $dataset = [
            'alpine-msa.pdf' => [
                'agreement_type' => ['raw' => 'Master Service Agreement', 'normalized' => ['value' => 'MSA'], 'confidence' => 0.9811, 'status' => 'extracted', 'page' => 1, 'quote' => 'This Master Service Agreement ("Agreement") is effective as of January 10, 2025.'],
                'effective_date' => ['raw' => 'January 10, 2025', 'normalized' => ['value' => '2025-01-10'], 'confidence' => 0.9722, 'status' => 'extracted', 'page' => 1, 'quote' => 'effective as of January 10, 2025'],
                'termination_date' => ['raw' => null, 'normalized' => null, 'confidence' => 0.2234, 'status' => 'missing', 'page' => null, 'quote' => null],
                'counterparty_name' => ['raw' => 'Alpine Manufacturing, LLC', 'normalized' => ['value' => 'Alpine Manufacturing, LLC'], 'confidence' => 0.9543, 'status' => 'extracted', 'page' => 1, 'quote' => 'between Acme Corp and Alpine Manufacturing, LLC'],
                'governing_law' => ['raw' => 'State of Delaware', 'normalized' => ['value' => 'DE'], 'confidence' => 0.8410, 'status' => 'needs_review', 'page' => 14, 'quote' => 'This Agreement shall be governed by the laws of the State of Delaware.'],
                'notice_period_days' => ['raw' => 'thirty (30) days', 'normalized' => ['value' => 30], 'confidence' => 0.8933, 'status' => 'extracted', 'page' => 8, 'quote' => 'either party may terminate with thirty (30) days prior written notice'],
            ],
            'boreal-nda.pdf' => [
                'agreement_type' => ['raw' => 'Mutual Non-Disclosure Agreement', 'normalized' => ['value' => 'NDA'], 'confidence' => 0.9650, 'status' => 'extracted', 'page' => 1, 'quote' => 'This Mutual Non-Disclosure Agreement is entered into...'],
                'effective_date' => ['raw' => '11/01/2024', 'normalized' => ['value' => '2024-11-01'], 'confidence' => 0.8892, 'status' => 'extracted', 'page' => 1, 'quote' => 'Effective Date: 11/01/2024'],
                'counterparty_name' => ['raw' => 'Boreal Biotech, Inc.', 'normalized' => ['value' => 'Boreal Biotech, Inc.'], 'confidence' => 0.9394, 'status' => 'extracted', 'page' => 1, 'quote' => 'between Acme Corp and Boreal Biotech, Inc.'],
                'confidentiality_term_months' => ['raw' => '36 months', 'normalized' => ['value' => 36], 'confidence' => 0.7775, 'status' => 'needs_review', 'page' => 5, 'quote' => 'obligations survive for thirty-six (36) months after termination'],
                'dispute_resolution' => ['raw' => 'Binding arbitration in San Francisco, California', 'normalized' => ['value' => 'arbitration_sf_ca'], 'confidence' => 0.8012, 'status' => 'ambiguous', 'page' => 7, 'quote' => 'Any dispute shall be resolved by binding arbitration in San Francisco, California.'],
                'assignment_allowed' => ['raw' => null, 'normalized' => null, 'confidence' => 0.3100, 'status' => 'missing', 'page' => null, 'quote' => null],
            ],
            'cedar-sow-v2.pdf' => [
                'agreement_type' => ['raw' => 'Statement of Work', 'normalized' => ['value' => 'SOW'], 'confidence' => 0.9566, 'status' => 'extracted', 'page' => 1, 'quote' => 'This Statement of Work ("SOW") is issued under the Master Agreement.'],
                'effective_date' => ['raw' => 'March 15, 2025', 'normalized' => ['value' => '2025-03-15'], 'confidence' => 0.9441, 'status' => 'extracted', 'page' => 1, 'quote' => 'effective on March 15, 2025'],
                'counterparty_name' => ['raw' => 'Cedar Logistics Co.', 'normalized' => ['value' => 'Cedar Logistics Co.'], 'confidence' => 0.9302, 'status' => 'extracted', 'page' => 1, 'quote' => 'between Acme Corp and Cedar Logistics Co.'],
                'payment_terms' => ['raw' => 'Net 45 from receipt of invoice', 'normalized' => ['value' => 'NET_45'], 'confidence' => 0.8588, 'status' => 'needs_review', 'page' => 4, 'quote' => 'Client shall remit payment net forty-five (45) days from invoice receipt.'],
                'liability_cap' => ['raw' => '$250,000', 'normalized' => ['value' => 250000, 'currency' => 'USD'], 'confidence' => 0.6833, 'status' => 'ambiguous', 'page' => 10, 'quote' => 'aggregate liability shall not exceed two hundred fifty thousand U.S. dollars ($250,000).'],
                'auto_renewal' => ['raw' => 'renews annually unless terminated', 'normalized' => ['value' => true], 'confidence' => 0.6215, 'status' => 'needs_review', 'page' => 6, 'quote' => 'This SOW renews automatically for successive one-year terms unless either party gives notice.'],
            ],
        ];

        foreach ($dataset as $filename => $fields) {
            $document = $documents[$filename] ?? null;

            if (! $document) {
                continue;
            }

            foreach ($fields as $fieldKey => $value) {
                $template = $templates[$fieldKey] ?? null;

                if (! $template) {
                    continue;
                }

                ExtractedField::query()->updateOrCreate(
                    [
                        'document_id' => $document->id,
                        'field_template_id' => $template->id,
                        'extraction_run_id' => $run->id,
                    ],
                    [
                        'raw_value' => $value['raw'],
                        'normalized_value' => $value['normalized'],
                        'confidence' => $value['confidence'],
                        'citation_page_number' => $value['page'],
                        'citation_quote' => $value['quote'],
                        'status' => $value['status'],
                        'evidence_spans' => $value['page']
                            ? [[
                                'page' => $value['page'],
                                'quote' => $value['quote'],
                                'start_char' => 0,
                                'end_char' => min(strlen((string) $value['quote']), 120),
                            ]]
                            : null,
                    ]
                );
            }
        }
    }
}
