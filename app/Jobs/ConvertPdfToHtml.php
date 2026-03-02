<?php

namespace App\Jobs;

use App\Models\DocumentVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ConvertPdfToHtml implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        private readonly int $documentId
    ) {}

    public function handle(): void
    {
        $document = DocumentVersion::find($this->documentId);

        if (! $document) {
            Log::error("ConvertPdfToHtml: DocumentVersion {$this->documentId} not found.");

            return;
        }

        $pdfPath = Storage::disk('pdfs')->path($document->pdf_file_path);
        $htmlDir = storage_path('app/html');
        $htmlBase = $htmlDir.'/'.pathinfo($document->pdf_file_path, PATHINFO_FILENAME);

        if (! file_exists($pdfPath)) {
            Log::error("ConvertPdfToHtml: PDF not found at {$pdfPath}");

            return;
        }

        if (! is_dir($htmlDir)) {
            mkdir($htmlDir, 0755, true);
        }

        // Call pdftohtml from poppler-utils
        // -c = complex layout, -noframes = single HTML file, -zoom 1.5 = better resolution
        $command = sprintf(
            'pdftohtml -c -noframes -zoom 1.5 %s %s 2>&1',
            escapeshellarg($pdfPath),
            escapeshellarg($htmlBase)
        );

        $output = shell_exec($command);
        $htmlFile = $htmlBase.'.html';

        if (! file_exists($htmlFile)) {
            Log::error("ConvertPdfToHtml: pdftohtml failed for document {$this->documentId}. Output: {$output}");

            return;
        }

        $htmlContent = file_get_contents($htmlFile);

        // Clean up the temporary HTML file
        @unlink($htmlFile);

        // Store HTML and mark as ready
        $document->html_content = $htmlContent;
        $document->html_ready = true;
        $document->save();

        Log::info("ConvertPdfToHtml: Document {$this->documentId} converted successfully.");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ConvertPdfToHtml job permanently failed for document {$this->documentId}: ".$exception->getMessage());

        // html_ready stays false — frontend will keep waiting / show an error state
    }
}
