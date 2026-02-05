<?php

namespace App\Support\Ltr;

use App\Models\Ltr\FieldReview;

class ReviewPayload
{
    public const KEY_REVIEW_STATUS = 'review_status';
    public const KEY_REVIEWED_VALUE = 'reviewed_value';
    public const KEY_REVIEW_NOTES = 'review_notes';
    public const KEY_REVIEWED_BY = 'reviewed_by';
    public const KEY_REVIEWED_AT = 'reviewed_at';

    public static function fromModel(FieldReview $review, array $extra = [], bool $includeLegacyAliases = true): array
    {
        $payload = [
            self::KEY_REVIEW_STATUS => $review->review_status,
            self::KEY_REVIEWED_VALUE => $review->final_value,
            'reviewed_normalized_value' => $review->final_normalized_value,
            self::KEY_REVIEW_NOTES => $review->note,
            self::KEY_REVIEWED_BY => $review->reviewer_id,
            self::KEY_REVIEWED_AT => optional($review->reviewed_at)->toIso8601String(),
            'reviewed_by_null_reason' => $review->reviewer_id === null
                ? 'review created without an authenticated reviewer context'
                : null,
        ];

        if ($includeLegacyAliases) {
            $payload['reviewer_id'] = $review->reviewer_id;
            $payload['note'] = $review->note;
        }

        return array_merge($payload, $extra);
    }
}
