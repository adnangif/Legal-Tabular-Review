<?php

use App\Models\Ltr\Document;
use App\Services\Ltr\ReingestDocumentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ltr:reingest-document {document : Document ID}', function (int $document, ReingestDocumentService $service): int {
    $record = Document::query()->find($document);

    if (!$record) {
        $this->error("Document {$document} not found.");

        return self::FAILURE;
    }

    try {
        $result = $service->reingest($record);
    } catch (\Throwable $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    }

    $this->info(sprintf(
        'Reingested document %d. needs_ocr=%s chunks_created=%d',
        $record->id,
        $result['needs_ocr'] ? 'true' : 'false',
        $result['chunks_created']
    ));

    if (!$result['needs_ocr'] && is_array($result['preview'])) {
        $preview = $result['preview'];

        $this->line(sprintf(
            'First chunk preview: trimmed_length=%d page=%d chunk_index=%d text="%s"',
            $preview['trimmed_length'],
            $preview['page_number'],
            $preview['chunk_index'],
            $preview['text_snippet']
        ));
    }

    return self::SUCCESS;
})->purpose('Reingest a document and persist chunks');
