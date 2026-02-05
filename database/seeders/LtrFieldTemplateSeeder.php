<?php

namespace Database\Seeders;

use App\Models\Ltr\FieldTemplate;
use Illuminate\Database\Seeder;

class LtrFieldTemplateSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $templates = [
            ['key' => 'agreement_type', 'label' => 'Agreement Type', 'type' => 'string', 'expected_format' => null, 'is_required' => true, 'allow_multiple' => false, 'normalization_rules' => ['trim', 'title_case'], 'extraction_hints' => ['Look at title page and recitals.'], 'sort_order' => 10],
            ['key' => 'effective_date', 'label' => 'Effective Date', 'type' => 'date', 'expected_format' => 'YYYY-MM-DD', 'is_required' => true, 'allow_multiple' => false, 'normalization_rules' => ['date_iso8601'], 'extraction_hints' => ['Find date near opening paragraph.'], 'sort_order' => 20],
            ['key' => 'termination_date', 'label' => 'Termination Date', 'type' => 'date', 'expected_format' => 'YYYY-MM-DD', 'is_required' => false, 'allow_multiple' => false, 'normalization_rules' => ['date_iso8601'], 'extraction_hints' => ['May appear in term section.'], 'sort_order' => 30],
            ['key' => 'counterparty_name', 'label' => 'Counterparty Name', 'type' => 'string', 'expected_format' => null, 'is_required' => true, 'allow_multiple' => false, 'normalization_rules' => ['trim'], 'extraction_hints' => ['Usually appears in opening paragraph.'], 'sort_order' => 40],
            ['key' => 'governing_law', 'label' => 'Governing Law', 'type' => 'string', 'expected_format' => null, 'is_required' => false, 'allow_multiple' => false, 'normalization_rules' => ['trim', 'uppercase_state_codes'], 'extraction_hints' => ['Find in legal boilerplate section.'], 'sort_order' => 50],
            ['key' => 'notice_period_days', 'label' => 'Notice Period (Days)', 'type' => 'number', 'expected_format' => 'integer', 'is_required' => false, 'allow_multiple' => false, 'normalization_rules' => ['integer'], 'extraction_hints' => ['Search termination clause for notice period.'], 'sort_order' => 60],
            ['key' => 'auto_renewal', 'label' => 'Auto Renewal', 'type' => 'boolean', 'expected_format' => 'true|false', 'is_required' => false, 'allow_multiple' => false, 'normalization_rules' => ['boolean'], 'extraction_hints' => ['Look for evergreen language.'], 'sort_order' => 70],
            ['key' => 'payment_terms', 'label' => 'Payment Terms', 'type' => 'string', 'expected_format' => null, 'is_required' => false, 'allow_multiple' => false, 'normalization_rules' => ['trim'], 'extraction_hints' => ['Look in fee/payment section.'], 'sort_order' => 80],
            ['key' => 'liability_cap', 'label' => 'Liability Cap', 'type' => 'currency', 'expected_format' => 'USD', 'is_required' => false, 'allow_multiple' => false, 'normalization_rules' => ['currency_usd'], 'extraction_hints' => ['Locate limitation of liability clause.'], 'sort_order' => 90],
            ['key' => 'confidentiality_term_months', 'label' => 'Confidentiality Term (Months)', 'type' => 'number', 'expected_format' => 'integer', 'is_required' => false, 'allow_multiple' => false, 'normalization_rules' => ['integer'], 'extraction_hints' => ['NDA section often includes survival term.'], 'sort_order' => 100],
            ['key' => 'assignment_allowed', 'label' => 'Assignment Allowed', 'type' => 'boolean', 'expected_format' => 'true|false', 'is_required' => false, 'allow_multiple' => false, 'normalization_rules' => ['boolean'], 'extraction_hints' => ['Assignment clause near final terms.'], 'sort_order' => 110],
            ['key' => 'dispute_resolution', 'label' => 'Dispute Resolution', 'type' => 'string', 'expected_format' => null, 'is_required' => false, 'allow_multiple' => false, 'normalization_rules' => ['trim'], 'extraction_hints' => ['Arbitration/venue language in legal section.'], 'sort_order' => 120],
        ];

        foreach ($templates as $template) {
            FieldTemplate::query()->updateOrCreate(
                ['field_key' => $template['key']],
                $template
            );
        }
    }
}
