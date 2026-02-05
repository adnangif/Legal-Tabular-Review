<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LTR Comparison</title>
</head>
<body>
    <main>
        <h1>LTR Comparison</h1>

        <form method="GET" action="{{ url('/ltr/table') }}">
            <div>
                <label for="document_ids">Documents</label><br>
                <select id="document_ids" name="document_ids[]" multiple size="12" required>
                    @foreach ($documents as $document)
                        @php
                            $displayTitle = $document->title ?: $document->original_filename;
                        @endphp
                        <option value="{{ $document->id }}" @selected(in_array($document->id, $defaults['document_ids'], true))>
                            #{{ $document->id }} — {{ $displayTitle }}
                        </option>
                    @endforeach
                </select>
                <p>Select one or more documents.</p>
            </div>

            <fieldset>
                <legend>Optional Filters</legend>

                <div>
                    <label for="min_confidence">Min confidence</label><br>
                    <input id="min_confidence" type="number" name="min_confidence" min="0" max="1" step="0.01" value="{{ $defaults['min_confidence'] }}">
                </div>

                <div>
                    <label for="status">Status</label><br>
                    <input id="status" type="text" name="status" value="{{ $defaults['status'] }}">
                </div>

                <div>
                    <label>
                        <input type="checkbox" name="include_missing" value="1" @checked($defaults['include_missing'])>
                        Include missing
                    </label>
                </div>

                <div>
                    <label>
                        <input type="checkbox" name="conflicts_only" value="1" @checked($defaults['conflicts_only'])>
                        Conflicts only
                    </label>
                </div>
            </fieldset>

            <button type="submit">Open comparison table</button>
        </form>
    </main>
</body>
</html>
