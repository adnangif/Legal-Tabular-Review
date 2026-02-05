<?php

namespace App\Http\Controllers\Ltr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ltr\ReviewCellRequest;
use App\Http\Requests\Ltr\StoreFieldReviewRequest;
use App\Models\Ltr\FieldReview;
use App\Services\Ltr\ReviewService;
use App\Support\Ltr\ReviewPayload;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(StoreFieldReviewRequest $request, ReviewService $service)
    {
        $review = $service->upsertCurrentReview(
            $request->validated(),
            $request->user()?->id
        );

        $payload = [
            'ok' => true,
            'review' => ReviewPayload::fromModel($review, [
                'id' => $review->id,
                'document_id' => $review->document_id,
                'field_template_id' => $review->field_template_id,
                'extracted_field_id' => $review->extracted_field_id,
                'is_current' => (bool) $review->is_current,
            ]),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return redirect()->back()->with('status', 'Review saved');
    }

    public function index(Request $request)
    {
        $docIds = collect($request->input('document_ids', []))
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        abort_if($docIds->isEmpty(), 422, 'document_ids is required');

        $rows = FieldReview::query()
            ->where('is_current', true)
            ->whereIn('document_id', $docIds)
            ->get();

        return response()->json([
            'reviews' => $rows->map(fn ($r) => ReviewPayload::fromModel($r, [
                'id' => $r->id,
                'document_id' => $r->document_id,
                'field_template_id' => $r->field_template_id,
                'extracted_field_id' => $r->extracted_field_id,
            ]))->values(),
        ]);
    }

    public function current(ReviewCellRequest $request)
    {
        $docId = (int) $request->validated('document_id');
        $fieldId = (int) $request->validated('field_template_id');

        $review = FieldReview::query()
            ->where('document_id', $docId)
            ->where('field_template_id', $fieldId)
            ->where('is_current', true)
            ->first();

        return response()->json([
            'review' => $review ? ReviewPayload::fromModel($review, [
                'id' => $review->id,
                'document_id' => $review->document_id,
                'field_template_id' => $review->field_template_id,
                'extracted_field_id' => $review->extracted_field_id,
                'is_current' => (bool) $review->is_current,
            ]) : null,
        ]);
    }

    public function history(ReviewCellRequest $request)
    {
        $docId = (int) $request->validated('document_id');
        $fieldId = (int) $request->validated('field_template_id');

        $rows = FieldReview::query()
            ->where('document_id', $docId)
            ->where('field_template_id', $fieldId)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'history' => $rows->map(fn ($r) => ReviewPayload::fromModel($r, [
                'id' => $r->id,
                'document_id' => $r->document_id,
                'field_template_id' => $r->field_template_id,
                'extracted_field_id' => $r->extracted_field_id,
                'is_current' => (bool) $r->is_current,
            ]))->values(),
        ]);
    }
}
