<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;
use Smalot\PdfParser\Parser;

/**
 * Service for processing PDF files and extracting content
 * Uses smalot/pdfparser library for text extraction
 * 
 * @package App\Services
 */
class PDFProcessingService
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Extract text content from PDF file
     *
     * @param string $filePath Absolute path to the PDF file
     * @return string Extracted text content
     * @throws Exception
     */
    public function extractTextFromPDF(string $filePath): string
    {
        try {
            if (!file_exists($filePath)) {
                throw new Exception("PDF file not found: {$filePath}");
            }

            if (!is_readable($filePath)) {
                throw new Exception("PDF file is not readable: {$filePath}");
            }

            Log::info('Starting PDF text extraction', ['file' => $filePath]);

            // Parse the PDF document
            $pdf = $this->parser->parseFile($filePath);
            
            // Extract text from all pages
            $text = $pdf->getText();
            
            if (empty(trim($text))) {
                // Try alternative extraction method
                $text = $this->extractTextAlternativeMethod($pdf);
            }

            if (empty(trim($text))) {
                throw new Exception('No extractable text found in PDF. The document might be image-based or encrypted.');
            }

            // Clean and process the extracted text
            $cleanedText = $this->cleanExtractedText($text);
            
            Log::info('PDF text extraction completed', [
                'file' => $filePath,
                'text_length' => strlen($cleanedText),
                'word_count' => str_word_count($cleanedText)
            ]);

            return $cleanedText;

        } catch (Exception $e) {
            Log::error('PDF text extraction failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new Exception("Failed to extract text from PDF: " . $e->getMessage());
        }
    }

    /**
     * Get the number of pages in the PDF
     *
     * @param string $filePath Absolute path to the PDF file
     * @return int Number of pages
     * @throws Exception
     */
    public function getPageCount(string $filePath): int
    {
        try {
            if (!file_exists($filePath)) {
                throw new Exception("PDF file not found: {$filePath}");
            }

            $pdf = $this->parser->parseFile($filePath);
            $pages = $pdf->getPages();
            
            return count($pages);

        } catch (Exception $e) {
            Log::warning('Failed to get PDF page count', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);

            // Return a reasonable default if page count fails
            return 1;
        }
    }

    /**
     * Get PDF metadata (title, author, etc.)
     *
     * @param string $filePath Absolute path to the PDF file
     * @return array PDF metadata
     */
    public function getPDFMetadata(string $filePath): array
    {
        try {
            if (!file_exists($filePath)) {
                return [];
            }

            $pdf = $this->parser->parseFile($filePath);
            $details = $pdf->getDetails();

            return [
                'title' => $this->cleanMetadataValue($details['Title'] ?? ''),
                'author' => $this->cleanMetadataValue($details['Author'] ?? ''),
                'subject' => $this->cleanMetadataValue($details['Subject'] ?? ''),
                'creator' => $this->cleanMetadataValue($details['Creator'] ?? ''),
                'producer' => $this->cleanMetadataValue($details['Producer'] ?? ''),
                'creation_date' => $details['CreationDate'] ?? null,
                'modification_date' => $details['ModDate'] ?? null,
                'page_count' => $this->getPageCount($filePath)
            ];

        } catch (Exception $e) {
            Log::warning('Failed to extract PDF metadata', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Validate if the file is a valid PDF
     *
     * @param string $filePath Absolute path to the file
     * @return bool True if valid PDF, false otherwise
     */
    public function isValidPDF(string $filePath): bool
    {
        try {
            if (!file_exists($filePath)) {
                return false;
            }

            // Check file header for PDF signature
            $handle = fopen($filePath, 'rb');
            if (!$handle) {
                return false;
            }

            $header = fread($handle, 4);
            fclose($handle);

            if ($header !== '%PDF') {
                return false;
            }

            // Try to parse with the PDF parser
            $this->parser->parseFile($filePath);
            
            return true;

        } catch (Exception $e) {
            Log::debug('PDF validation failed', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Extract text using alternative method (page by page)
     *
     * @param \Smalot\PdfParser\Document $pdf
     * @return string Extracted text
     */
    private function extractTextAlternativeMethod($pdf): string
    {
        try {
            $pages = $pdf->getPages();
            $extractedText = '';

            foreach ($pages as $pageNumber => $page) {
                try {
                    $pageText = $page->getText();
                    if (!empty(trim($pageText))) {
                        $extractedText .= "\n\n=== PAGE " . ($pageNumber + 1) . " ===\n\n";
                        $extractedText .= $pageText;
                    }
                } catch (Exception $e) {
                    Log::warning('Failed to extract text from page', [
                        'page' => $pageNumber + 1,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return $extractedText;

        } catch (Exception $e) {
            Log::warning('Alternative text extraction method failed', [
                'error' => $e->getMessage()
            ]);
            
            return '';
        }
    }

    /**
     * Clean and process extracted text
     *
     * @param string $text Raw extracted text
     * @return string Cleaned text
     */
    private function cleanExtractedText(string $text): string
    {
        // Remove null bytes and control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Remove excessive whitespace while preserving paragraph structure
        $text = preg_replace('/[ \t]+/', ' ', $text); // Multiple spaces/tabs to single space
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text); // Multiple newlines to double newline
        
        // Remove page numbers and headers/footers (basic patterns)
        $text = preg_replace('/^\s*\d+\s*$/m', '', $text); // Standalone numbers (page numbers)
        
        // Fix common PDF extraction issues
        $text = $this->fixCommonExtractionIssues($text);
        
        // Trim and return
        return trim($text);
    }

    /**
     * Fix common PDF text extraction issues
     *
     * @param string $text Text to fix
     * @return string Fixed text
     */
    private function fixCommonExtractionIssues(string $text): string
    {
        // Fix hyphenated words split across lines
        $text = preg_replace('/(\w)-\s*\n\s*(\w)/', '$1$2', $text);
        
        // Fix words split without hyphen across lines (common in justified text)
        $text = preg_replace('/(\w)\s*\n\s*(\w)/', '$1 $2', $text);
        
        // Remove excessive spaces around punctuation
        $text = preg_replace('/\s+([,.!?;:])/', '$1', $text);
        $text = preg_replace('/([,.!?;:])\s+/', '$1 ', $text);
        
        // Fix missing spaces after periods
        $text = preg_replace('/\.([A-Z])/', '. $1', $text);
        
        // Fix common ligature issues
        $ligatures = [
            'ﬁ' => 'fi',
            'ﬂ' => 'fl',
            'ﬀ' => 'ff',
            'ﬃ' => 'ffi',
            'ﬄ' => 'ffl',
            '"' => '"',
        ];
        
        foreach ($ligatures as $search => $replace) {
            $text = str_replace($search, $replace, $text);
        }
        
        return $text;
    }

    /**
     * Clean metadata value
     *
     * @param mixed $value Raw metadata value
     * @return string Cleaned metadata value
     */
    private function cleanMetadataValue($value): string
    {
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        
        return trim((string) $value);
    }

    /**
     * Estimate word count more accurately
     *
     * @param string $text Text to count words in
     * @return int Estimated word count
     */
    public function getWordCount(string $text): int
    {
        // Remove extra whitespace and count words
        $cleanText = preg_replace('/\s+/', ' ', trim($text));
        
        if (empty($cleanText)) {
            return 0;
        }
        
        return str_word_count($cleanText);
    }

    /**
     * Estimate reading time based on word count
     *
     * @param string $text Text to estimate reading time for
     * @param int $wordsPerMinute Average reading speed (default: 250 WPM)
     * @return string Formatted reading time
     */
    public function estimateReadingTime(string $text, int $wordsPerMinute = 250): string
    {
        $wordCount = $this->getWordCount($text);
        $minutes = ceil($wordCount / $wordsPerMinute);
        
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($remainingMinutes == 0) {
            return $hours . 'h';
        }
        
        return $hours . 'h ' . $remainingMinutes . 'min';
    }

    /**
     * Check if PDF is password protected
     *
     * @param string $filePath Absolute path to the PDF file
     * @return bool True if password protected
     */
    public function isPasswordProtected(string $filePath): bool
    {
        try {
            $this->parser->parseFile($filePath);
            return false;
        } catch (Exception $e) {
            $errorMessage = strtolower($e->getMessage());
            return strpos($errorMessage, 'password') !== false || 
                   strpos($errorMessage, 'encrypted') !== false ||
                   strpos($errorMessage, 'protected') !== false;
        }
    }
}