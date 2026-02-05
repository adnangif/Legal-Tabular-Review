<?php

namespace Tests\Unit\Ltr;

use App\Models\Ltr\ExtractedField;
use App\Models\Ltr\FieldReview;
use App\Services\Ltr\FieldValueResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FieldValueResolverTest extends TestCase
{
    #[DataProvider('reviewedValuePriorityProvider')]
    public function test_value_resolution_priority_reviewed_then_normalized_then_raw(
        string $reviewStatus,
        ?string $finalValue,
        mixed $normalizedValue,
        ?string $rawValue,
        mixed $expectedResolvedValue,
        ?string $expectedSource,
        ?string $expectedSourceDetail
    ): void {
        $resolver = new FieldValueResolver();

        $extracted = new ExtractedField([
            'normalized_value' => $normalizedValue,
            'raw_value' => $rawValue,
        ]);

        $review = new FieldReview([
            'review_status' => $reviewStatus,
            'final_value' => $finalValue,
            'is_current' => true,
        ]);

        $resolved = $resolver->resolve($extracted, $review);

        $this->assertSame($expectedResolvedValue, $resolved['resolved_value']);
        $this->assertSame($expectedSource, $resolved['source']);
        $this->assertSame($expectedSourceDetail, $resolved['source_detail']);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function reviewedValuePriorityProvider(): array
    {
        return [
            'accepted review overrides normalized and raw' => [
                'accepted',
                'Reviewed Value',
                ['Normalized Value'],
                'Raw Value',
                'Reviewed Value',
                'review',
                'reviewed_value',
            ],
            'overridden review overrides normalized and raw' => [
                'overridden',
                'Reviewed Override',
                ['Normalized Value'],
                'Raw Value',
                'Reviewed Override',
                'review',
                'reviewed_value',
            ],
            'rejected review falls back to normalized value' => [
                'rejected',
                'Ignored Reviewed Value',
                ['Normalized Value'],
                'Raw Value',
                'Normalized Value',
                'extraction',
                'normalized_value',
            ],
            'rejected review falls back to raw value when normalized missing' => [
                'rejected',
                'Ignored Reviewed Value',
                null,
                'Raw Value',
                'Raw Value',
                'extraction',
                'raw_value',
            ],
        ];
    }

    public function test_resolve_without_review_prefers_normalized_value(): void
    {
        $resolver = new FieldValueResolver();
        $extracted = new ExtractedField([
            'normalized_value' => ['2026-01-01'],
            'raw_value' => 'Jan 1, 2026',
            'confidence' => 0.91,
            'citation_page_number' => 2,
            'citation_quote' => 'Effective Date: Jan 1, 2026',
        ]);

        $resolved = $resolver->resolve($extracted, null);

        $this->assertSame('2026-01-01', $resolved['resolved_value']);
        $this->assertSame('normalized_value', $resolved['source_detail']);
        $this->assertSame('pending', $resolved['status']);
        $this->assertNull($resolved['review']);
    }

    public function test_rejected_review_does_not_use_reviewed_value(): void
    {
        $resolver = new FieldValueResolver();

        $extracted = new ExtractedField([
            'normalized_value' => ['california'],
            'raw_value' => 'California',
        ]);

        $review = new FieldReview([
            'review_status' => 'rejected',
            'final_value' => 'New York',
            'is_current' => true,
        ]);

        $resolved = $resolver->resolve($extracted, $review);

        $this->assertSame('california', $resolved['resolved_value']);
        $this->assertSame('extraction', $resolved['source']);
        $this->assertFalse($resolved['review']['used_reviewed_value']);
    }

    public function test_resolve_raw_only_value_when_normalized_missing(): void
    {
        $resolver = new FieldValueResolver();
        $extracted = new ExtractedField([
            'normalized_value' => null,
            'raw_value' => 'Net 30',
        ]);

        $resolved = $resolver->resolve($extracted, null);

        $this->assertSame('Net 30', $resolved['resolved_value']);
        $this->assertSame('raw_value', $resolved['source_detail']);
        $this->assertSame('pending', $resolved['status']);
    }

    public function test_resolve_missing_when_no_review_and_no_extracted_values(): void
    {
        $resolver = new FieldValueResolver();
        $extracted = new ExtractedField([
            'normalized_value' => null,
            'raw_value' => null,
        ]);

        $resolved = $resolver->resolve($extracted, null);

        $this->assertNull($resolved['resolved_value']);
        $this->assertNull($resolved['source']);
        $this->assertSame('missing', $resolved['status']);
    }
}
