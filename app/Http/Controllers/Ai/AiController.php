<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\ChatGPTService;
use App\Services\PDFProcessingService;
use Exception;

class AiController extends Controller
{
    private ChatGPTService $chatGPTService;
    private PDFProcessingService $pdfService;

    public function __construct(ChatGPTService $chatGPTService, PDFProcessingService $pdfService)
    {
        $this->chatGPTService = $chatGPTService;
        $this->pdfService = $pdfService;
    }

    /**
     * Analyze PDF file with ChatGPT
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function analyzePDF(Request $request): JsonResponse
    {
        try {
            // Validate incoming request
            $validator = $this->validateRequest($request);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Données de requête invalides',
                    422,
                    ['validation_errors' => $validator->errors()]
                );
            }

            // Get the uploaded PDF file
            $pdfFile = $request->file('pdf');

            // Generate unique filename and store temporarily
            $filename = $this->generateUniqueFilename($pdfFile);
            $tempPath = $this->storeTempFile($pdfFile, $filename);

            Log::info('PDF Analysis Started', [
                'filename' => $filename,
                'size' => $pdfFile->getSize(),
                'mime' => $pdfFile->getMimeType()
            ]);

            try {
                // Extract text content from PDF
                $extractedText = $this->pdfService->extractTextFromPDF($tempPath);

                if (empty(trim($extractedText))) {
                    throw new Exception('Le PDF semble être vide ou ne contient pas de texte extractible.');
                }

                // Prepare text for ChatGPT analysis
                $preparedText = $this->prepareTextForAnalysis($extractedText);

                // Send to ChatGPT for analysis
                $analysisResult = $this->chatGPTService->analyzeBookContent($preparedText, [
                    'filename' => $pdfFile->getClientOriginalName(),
                    'pages' => $this->pdfService->getPageCount($tempPath),
                    'word_count' => str_word_count($extractedText)
                ]);

                // Process and validate the response
                $processedResult = $this->processAnalysisResult($analysisResult, $pdfFile);

                Log::info('PDF Analysis Completed Successfully', [
                    'filename' => $filename,
                    'analysis_size' => strlen(json_encode($processedResult))
                ]);

                return $this->successResponse('Analyse PDF terminée avec succès', $processedResult);

            } finally {
                // Always cleanup temporary file
                $this->cleanupTempFile($tempPath);
            }

        } catch (Exception $e) {
            Log::error('PDF Analysis Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $request->file('pdf') ? $request->file('pdf')->getClientOriginalName() : 'unknown'
            ]);

            return $this->errorResponse(
                $this->getUserFriendlyErrorMessage($e),
                $this->getErrorStatusCode($e)
            );
        }
    }

    /**
     * Validate the incoming request
     */
    private function validateRequest(Request $request): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($request->all(), [
            'pdf' => 'required|file|mimes:pdf|max:22240',
        ], [
            'pdf.required' => 'Un fichier PDF est requis',
            'pdf.file' => 'Le fichier téléchargé n\'est pas valide',
            'pdf.mimes' => 'Seuls les fichiers PDF sont acceptés',
            'pdf.max' => 'Le fichier ne peut pas dépasser 20MB'
        ]);
    }


    /**
     * Generate unique filename for temporary storage
     */
    private function generateUniqueFilename($file): string
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $uniqueId = Str::uuid();

        return "pdf_analysis_{$uniqueId}_{$originalName}.{$extension}";
    }

    /**
     * Store file temporarily for processing
     */
    private function storeTempFile($file, string $filename): string
    {
        $tempDir = 'temp/pdf_analysis';
        $path = $file->storeAs($tempDir, $filename, 'local');

        return Storage::disk('local')->path($path);
    }

    /**
     * Prepare extracted text for ChatGPT analysis
     */
    private function prepareTextForAnalysis(string $text): string
    {
        // Clean up the text
        $text = preg_replace('/\s+/', ' ', $text); // Normalize whitespace
        $text = trim($text);

        // Limit text size for ChatGPT (approx 16k tokens = ~64k characters)
        $maxChars = 60000;
        if (strlen($text) > $maxChars) {
            // Take beginning and end of the text to preserve context
            $halfSize = $maxChars / 2;
            $beginning = substr($text, 0, $halfSize);
            $ending = substr($text, -$halfSize);
            $text = $beginning . "\n\n[... CONTENU TRONQUÉ ...]\n\n" . $ending;
        }

        return $text;
    }

    /**
     * Process and enhance the analysis result from ChatGPT
     */
    private function processAnalysisResult(array $analysisResult, $originalFile): array
    {
        // Add metadata about the original file
        $analysisResult['file_metadata'] = [
            'original_name' => $originalFile->getClientOriginalName(),
            'file_size' => $originalFile->getSize(),
            'file_size_human' => $this->formatFileSize($originalFile->getSize()),
            'upload_time' => now()->toISOString()
        ];

        // Add processing metadata
        $analysisResult['analysis_metadata'] = array_merge(
            $analysisResult['analysis_metadata'] ?? [],
            [
                'processed_at' => now()->toISOString(),
                'server_version' => app()->version(),
                'processing_server' => gethostname()
            ]
        );

        // Ensure required fields have defaults
        $analysisResult = array_merge([
            'title' => 'Document Sans Titre',
            'author' => 'Auteur Inconnu',
            'pages' => 0,
            'readingTime' => '0 min',
            'difficulty' => 'Intermédiaire',
            'rating' => 3.0,
            'genre' => 'Non Classifié',
            'tags' => [],
            'summary' => []
        ], $analysisResult);

        return $analysisResult;
    }

    /**
     * Clean up temporary files
     */
    private function cleanupTempFile(string $filePath): void
    {
        try {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        } catch (Exception $e) {
            Log::warning('Failed to cleanup temp file', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get user-friendly error message
     */
    private function getUserFriendlyErrorMessage(Exception $e): string
    {
        $message = $e->getMessage();

        // Map technical errors to user-friendly messages
        $errorMappings = [
            'file not found' => 'Le fichier n\'a pas pu être traité',
            'invalid pdf' => 'Le fichier PDF semble être corrompu',
            'text extraction failed' => 'Impossible d\'extraire le texte du PDF',
            'chatgpt api error' => 'Erreur lors de l\'analyse par l\'IA',
            'timeout' => 'L\'analyse a pris trop de temps',
            'rate limit' => 'Trop de requêtes simultanées, veuillez réessayer',
            'insufficient content' => 'Le document ne contient pas assez de contenu à analyser'
        ];

        foreach ($errorMappings as $technical => $friendly) {
            if (stripos($message, $technical) !== false) {
                return $friendly;
            }
        }

        return 'Une erreur inattendue s\'est produite lors de l\'analyse';
    }

    /**
     * Get appropriate HTTP status code for error
     */
    private function getErrorStatusCode(Exception $e): int
    {
        $message = strtolower($e->getMessage());

        if (stripos($message, 'validation') !== false)
            return 422;
        if (stripos($message, 'not found') !== false)
            return 404;
        if (stripos($message, 'unauthorized') !== false)
            return 401;
        if (stripos($message, 'forbidden') !== false)
            return 403;
        if (stripos($message, 'timeout') !== false)
            return 408;
        if (stripos($message, 'rate limit') !== false)
            return 429;
        if (stripos($message, 'too large') !== false)
            return 413;

        return 500;
    }

    /**
     * Format file size in human readable format
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Return success response
     */
    private function successResponse(string $message, array $data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ], 200);
    }

    /**
     * Return error response
     */
    private function errorResponse(string $message, int $status = 500, array $details = []): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => $message,
            'timestamp' => now()->toISOString()
        ];

        if (!empty($details)) {
            $response['details'] = $details;
        }

        return response()->json($response, $status);
    }
}
