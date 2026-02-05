<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LTR Comparison Table</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 1.5rem; }
        h1 { margin-bottom: 0.5rem; }
        .meta { margin-bottom: 1rem; color: #444; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; vertical-align: top; padding: 0.5rem; }
        th { background: #f5f5f5; position: sticky; top: 0; }
        .cell-value { font-weight: 600; margin-bottom: 0.25rem; }
        .cell-meta { font-size: 0.875rem; color: #444; margin-bottom: 0.5rem; }
        .cell-meta div { margin-bottom: 0.15rem; }
        .review-form { border-top: 1px dashed #ddd; padding-top: 0.5rem; }
        .review-form label { display: block; font-size: 0.85rem; margin-top: 0.35rem; }
        .review-form input[type="text"], .review-form textarea, .review-form select { width: 100%; box-sizing: border-box; }
        .review-form button { margin-top: 0.5rem; }
        .citation { margin-bottom: 0.4rem; }
        .field-label { min-width: 220px; }
    </style>
</head>
<body>
    <h1>LTR Comparison Table</h1>
    <div class="meta">
        <div><strong>Run:</strong> {{ $run->run_uid }} (ID {{ $run->id }})</div>
        <div><strong>Documents:</strong> {{ $documents->count() }}</div>
    </div>

    @if (session('status'))
        <div style="margin-bottom: 1rem; color: #0f5132; background: #d1e7dd; border: 1px solid #badbcc; padding: 0.75rem;">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div style="margin-bottom: 1rem; color: #842029; background: #f8d7da; border: 1px solid #f5c2c7; padding: 0.75rem;">
            <strong>Unable to save review:</strong>
            <ul style="margin: 0.5rem 0 0 1.25rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th class="field-label">Field Template</th>
                @foreach ($documents as $document)
                    <th>
                        <div>{{ $document->title ?: $document->original_filename }}</div>
                        <small>ID {{ $document->id }} · {{ $document->uuid }}</small>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($fields as $field)
                <tr>
                    <td>
                        <div><strong>{{ $field->label }}</strong></div>
                        <div><code>{{ $field->field_key }}</code></div>
                        <div>Type: {{ $field->type }}</div>
                        <div>Required: {{ $field->is_required ? 'yes' : 'no' }}</div>
                    </td>
                    @foreach ($documents as $document)
                        @php
                            $cell = $field->cells[(string) $document->id] ?? null;
                            $review = $cell['review'] ?? null;
                            $citation = $cell['citation'] ?? null;
                        @endphp
                        <td>
                            <div class="cell-value">{{ $cell['value'] ?? '—' }}</div>
                            <div class="cell-meta">
                                <div><strong>Confidence:</strong> {{ isset($cell['confidence']) ? number_format((float) $cell['confidence'], 4) : '—' }}</div>
                                <div><strong>Review status:</strong> {{ $review['review_status'] ?? 'pending' }}</div>
                                <div><strong>Cell status:</strong> {{ $cell['status'] ?? 'missing' }}</div>
                                <div><strong>Source:</strong> {{ $cell['source'] ?? '—' }}</div>
                            </div>

                            @if ($citation)
                                <div class="citation">
                                    @if (!empty($citation['quote']))
                                        <div><strong>Snippet:</strong> {{ $citation['quote'] }}</div>
                                    @endif
                                    @if (!empty($citation['chunk_id']))
                                        <a href="{{ route('ltr.citations.chunk.show', ['chunk' => $citation['chunk_id']]) }}" target="_blank" rel="noreferrer">View citation chunk</a>
                                    @endif
                                </div>
                            @endif

                            <form class="review-form" method="POST" action="{{ route('ltr.reviews.store') }}">
                                @csrf
                                <input type="hidden" name="document_id" value="{{ $document->id }}">
                                <input type="hidden" name="field_template_id" value="{{ $field->field_id }}">
                                @if (!empty($review['extracted_field_id'] ?? $cell['extracted_field_id'] ?? null))
                                    <input type="hidden" name="extracted_field_id" value="{{ $review['extracted_field_id'] ?? $cell['extracted_field_id'] }}">
                                @elseif (!empty($cell['extraction_run_id']))
                                    <input type="hidden" name="extraction_run_id" value="{{ $cell['extraction_run_id'] }}">
                                @endif

                                <label>
                                    Review status
                                    @php $selectedStatus = $review['review_status'] ?? 'pending'; @endphp
                                    <select name="review_status" required>
                                        @foreach (['pending', 'accepted', 'overridden', 'rejected'] as $statusOption)
                                            <option value="{{ $statusOption }}" @selected($selectedStatus === $statusOption)>{{ $statusOption }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label>
                                    reviewed_value
                                    <input type="text" name="final_value" value="{{ $review['reviewed_value'] ?? ($cell['value'] ?? '') }}">
                                </label>

                                <label>
                                    review_notes
                                    <textarea name="note" rows="2">{{ $review['review_notes'] ?? '' }}</textarea>
                                </label>

                                <button type="submit">Save review</button>
                            </form>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
