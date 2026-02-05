<?php

namespace Database\Seeders;

use App\Models\Ltr\Document;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LtrDocumentSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $documents = [
            [
                'uuid' => (string) Str::uuid(),
                'title' => 'Master Service Agreement - Alpine Manufacturing',
                'original_filename' => 'alpine-msa.pdf',
                'mime_type' => 'application/pdf',
                'storage_disk' => 'private',
                'storage_path' => 'ltr/contracts/alpine-msa.pdf',
                'sha256' => hash('sha256', 'alpine-msa.pdf'),
                'source' => 'upload',
                'needs_ocr' => false,
                'meta' => [
                    'counterparty' => 'Alpine Manufacturing',
                    'document_type' => 'msa',
                    'effective_date' => '2025-01-10',
                ],
            ],
            [
                'uuid' => (string) Str::uuid(),
                'title' => 'NDA - Boreal Biotech',
                'original_filename' => 'boreal-nda.pdf',
                'mime_type' => 'application/pdf',
                'storage_disk' => 'private',
                'storage_path' => 'ltr/contracts/boreal-nda.pdf',
                'sha256' => hash('sha256', 'boreal-nda.pdf'),
                'source' => 'email',
                'needs_ocr' => true,
                'meta' => [
                    'counterparty' => 'Boreal Biotech',
                    'document_type' => 'nda',
                    'effective_date' => '2024-11-01',
                ],
            ],
            [
                'uuid' => (string) Str::uuid(),
                'title' => 'Statement of Work - Cedar Logistics',
                'original_filename' => 'cedar-sow-v2.pdf',
                'mime_type' => 'application/pdf',
                'storage_disk' => 'private',
                'storage_path' => 'ltr/contracts/cedar-sow-v2.pdf',
                'sha256' => hash('sha256', 'cedar-sow-v2.pdf'),
                'source' => 'api',
                'needs_ocr' => false,
                'meta' => [
                    'counterparty' => 'Cedar Logistics',
                    'document_type' => 'sow',
                    'effective_date' => '2025-03-15',
                ],
            ],
        ];

        foreach ($documents as $document) {
            Document::query()->updateOrCreate(
                ['original_filename' => $document['original_filename']],
                $document
            );
        }
    }
}
