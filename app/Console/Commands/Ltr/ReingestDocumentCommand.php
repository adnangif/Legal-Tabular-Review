<?php

namespace App\Console\Commands\Ltr;

use App\Models\Ltr\Document;
use App\Services\Ltr\DocumentIngestionService;
use Illuminate\Console\Command;
use Throwable;

class ReingestDocumentCommand extends Command
{
    protected $signature = 'ltr:documents:reingest {document_id : Numeric ID or UUID of the document}';

    protected $description = 'Re-ingest an LTR document and rebuild its text chunks';

    public function handle(DocumentIngestionService $ingestionService): int
    {
        $identifier = (string) $this->argument('document_id');

        $document = Document::query()
            ->when(is_numeric($identifier), fn ($q) => $q->where('id', (int) $identifier))
            ->orWhere('uuid', $identifier)
            ->first();

        if (!$document) {
            $this->error("Document not found for identifier [{$identifier}].");

            return self::FAILURE;
        }

        try {
            $result = $ingestionService->reingest($document);
        } catch (Throwable $e) {
            $this->table(['Field', 'Value'], [
                ['Document ID', (string) $document->id],
                ['Extraction outcome', 'failed'],
                ['Chunks inserted', '0'],
                ['First chunk preview', '(none)'],
            ]);
            $this->newLine();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Document re-ingestion complete:');
        $this->table(['Field', 'Value'], [
            ['Document ID', (string) $result['document_id']],
            ['Extraction outcome', $result['outcome']],
            ['Chunks inserted', (string) $result['chunks_inserted']],
            ['First chunk preview', $result['first_chunk_preview'] ?? '(none)'],
        ]);

        return self::SUCCESS;
    }
}
