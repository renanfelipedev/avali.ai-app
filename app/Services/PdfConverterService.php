<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use Exception;

class PdfConverterService
{
    public function __construct()
    {
        // Configure PHPWord to use Dompdf as PDF renderer
        Settings::setPdfRendererName(Settings::PDF_RENDERER_DOMPDF);
        Settings::setPdfRendererPath(base_path('vendor/dompdf/dompdf'));
    }

    /**
     * Converts various file types to PDF.
     * Returns the relative path to the generated PDF.
     */
    public function convertToPdf(string $sourceRelativePath, string $disk = 'public'): string
    {
        $fullPath = Storage::disk($disk)->path($sourceRelativePath);
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $directory = pathinfo($sourceRelativePath, PATHINFO_DIRNAME);
        $filename = pathinfo($sourceRelativePath, PATHINFO_FILENAME);
        $pdfRelativePath = ($directory === '.' ? '' : $directory . '/') . $filename . '.pdf';

        if ($extension === 'pdf') {
            return $sourceRelativePath;
        }

        $pdfContent = match ($extension) {
            'jpg', 'jpeg', 'png', 'webp' => $this->convertImageToPdf($fullPath),
            'txt' => $this->convertTextToPdf($fullPath),
            'doc', 'docx' => $this->convertDocToPdf($fullPath),
            default => throw new Exception("Extensão de arquivo não suportada para conversão em PDF: {$extension}"),
        };

        Storage::disk($disk)->put($pdfRelativePath, $pdfContent);

        return $pdfRelativePath;
    }

    private function convertImageToPdf(string $fullPath): string
    {
        $imageData = base64_encode(file_get_contents($fullPath));
        $mimeType = mime_content_type($fullPath);
        
        $html = "
            <html>
            <body style='margin:0; padding:0; text-align:center;'>
                <img src='data:{$mimeType};base64,{$imageData}' style='max-width:100%; height:auto;'>
            </body>
            </html>
        ";

        return $this->renderHtmlToPdf($html);
    }

    private function convertTextToPdf(string $fullPath): string
    {
        $text = e(file_get_contents($fullPath));
        $html = "
            <html>
            <body style='font-family: monospace; white-space: pre-wrap; font-size: 12px;'>
                {$text}
            </body>
            </html>
        ";

        return $this->renderHtmlToPdf($html);
    }

    private function convertDocToPdf(string $fullPath): string
    {
        try {
            $phpWord = IOFactory::load($fullPath);
            
            // Temporary file to save PDF
            $tempPdf = tempnam(sys_get_temp_dir(), 'pdf_');
            $writer = IOFactory::createWriter($phpWord, 'PDF');
            $writer->save($tempPdf);
            
            $content = file_get_contents($tempPdf);
            unlink($tempPdf);
            
            return $content;
        } catch (Exception $e) {
            throw new Exception("Erro ao converter DOC/DOCX para PDF: " . $e->getMessage());
        }
    }

    private function renderHtmlToPdf(string $html): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
