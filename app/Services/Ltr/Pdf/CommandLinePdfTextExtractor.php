<?php

namespace App\Services\Ltr\Pdf;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class CommandLinePdfTextExtractor implements PdfTextExtractor
{
    public function extract(string $pdfBytes): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'ltr_pdf_');

        if ($tempFile === false) {
            throw new RuntimeException('Unable to create temporary file for PDF extraction.');
        }

        file_put_contents($tempFile, $pdfBytes);

        try {
            $result = Process::run([
                'pdftotext',
                '-layout',
                '-q',
                $tempFile,
                '-',
            ]);

            if ($result->failed()) {
                throw new RuntimeException('PDF extraction failed: '.$result->errorOutput());
            }

            return trim($result->output());
        } finally {
            @unlink($tempFile);
        }
    }
}
