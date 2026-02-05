<?php

namespace App\Services\Ltr;

use App\Models\Ltr\ExtractedField;
use App\Models\Ltr\FieldReview;
use App\Support\Ltr\ReviewPayload;

class FieldValueResolver
{
    public function resolve(?ExtractedField $extracted, ?FieldReview $review): array
    {
        $reviewStatus = $review?->review_status;
        $reviewCanOverride = in_array($reviewStatus, ['accepted', 'overridden'], true);
        $reviewMarksMissing = $reviewStatus === 'rejected' && $review?->decision === 'marked_missing';

        $normalizedValue = $this->normalizeExtractedValue($extracted?->normalized_value);
        $rawValue = $extracted?->raw_value;

        if ($reviewMarksMissing) {
            $resolvedValue = null;
            $source = null;
            $sourceDetail = null;
        } elseif ($reviewCanOverride) {
            $resolvedValue = $review->final_value;
            $source = 'review';
            $sourceDetail = 'reviewed_value';
        } elseif ($normalizedValue !== null) {
            $resolvedValue = $normalizedValue;
            $source = 'extraction';
            $sourceDetail = 'normalized_value';
        } elseif ($rawValue !== null && trim((string) $rawValue) !== '') {
            $resolvedValue = $rawValue;
            $source = 'extraction';
            $sourceDetail = 'raw_value';
        } else {
            $resolvedValue = null;
            $source = null;
            $sourceDetail = null;
        }

        return [
            'resolved_value' => $resolvedValue,
            'value' => $resolvedValue,
            'resolved_normalized_value' => $normalizedValue,
            'normalized_value' => $normalizedValue,
            'source' => $source,
            'source_detail' => $sourceDetail,
            'status' => $this->resolveStatus($reviewStatus, $resolvedValue),
            'citation' => $this->citationPayload($extracted),
            'confidence' => $extracted?->confidence !== null ? (float) $extracted->confidence : null,
            'review' => $review ? ReviewPayload::fromModel($review, [
                'decision' => $review->decision,
                'used_reviewed_value' => $reviewCanOverride,
            ]) : null,
        ];
    }

    private function normalizeExtractedValue(mixed $normalizedValue): mixed
    {
        if ($normalizedValue === null) {
            return null;
        }

        if (is_array($normalizedValue)) {
            if (array_is_list($normalizedValue) && count($normalizedValue) === 1) {
                return $normalizedValue[0];
            }

            return json_encode($normalizedValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $normalizedValue;
    }

    private function citationPayload(?ExtractedField $extracted): ?array
    {
        if (!$extracted) {
            return null;
        }

        if (!$extracted->citation_page_number && !$extracted->citation_quote && !$extracted->citationChunk) {
            return null;
        }

        return [
            'chunk_id' => $extracted->citationChunk?->id,
            'page' => $extracted->citation_page_number,
            'chunk_uid' => $extracted->citationChunk?->chunk_uid,
            'quote' => $extracted->citation_quote,
        ];
    }

    private function resolveStatus(?string $reviewStatus, mixed $resolvedValue): string
    {
        return match ($reviewStatus) {
            'accepted', 'overridden' => 'approved',
            default => $resolvedValue === null ? 'missing' : 'pending',
        };
    }
}
