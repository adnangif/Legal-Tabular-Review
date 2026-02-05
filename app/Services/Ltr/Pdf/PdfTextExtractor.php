<?php

namespace App\Services\Ltr\Pdf;

interface PdfTextExtractor
{
    public function extract(string $pdfBytes): string;
}
