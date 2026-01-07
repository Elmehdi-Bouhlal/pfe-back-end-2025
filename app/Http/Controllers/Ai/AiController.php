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

    public function __construct(
        ChatGPTService $chatGPTService,
        PDFProcessingService $pdfService,
    ) {
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
                    "Données de requête invalides",
                    422,
                    ["validation_errors" => $validator->errors()],
                );
            }

            // Get the uploaded PDF file
            $pdfFile = $request->file("pdf");

            // Generate unique filename and store temporarily
            $filename = $this->generateUniqueFilename($pdfFile);
            $tempPath = $this->storeTempFile($pdfFile, $filename);

            Log::info("PDF Analysis Started", [
                "filename" => $filename,
                "size" => $pdfFile->getSize(),
                "mime" => $pdfFile->getMimeType(),
            ]);

            try {
                // Extract text content from PDF
                $extractedText = $this->pdfService->extractTextFromPDF(
                    $tempPath,
                );

                if (empty(trim($extractedText))) {
                    throw new Exception(
                        "Le PDF semble être vide ou ne contient pas de texte extractible.",
                    );
                }

                // Prepare text for ChatGPT analysis
                $preparedText = $this->prepareTextForAnalysis($extractedText);

                // Send to ChatGPT for analysis
                $analysisResult = $this->chatGPTService->analyzeBookContent(
                    $preparedText,
                    [
                        "filename" => $pdfFile->getClientOriginalName(),
                        "pages" => $this->pdfService->getPageCount($tempPath),
                        "word_count" => str_word_count($extractedText),
                    ],
                );

                // Process and validate the response
                $processedResult = $this->processAnalysisResult(
                    $analysisResult,
                    $pdfFile,
                );

                Log::info("PDF Analysis Completed Successfully", [
                    "filename" => $filename,
                    "analysis_size" => strlen(json_encode($processedResult)),
                ]);

                return $this->successResponse(
                    "Analyse PDF terminée avec succès",
                    $processedResult,
                );
            } finally {
                // Always cleanup temporary file
                $this->cleanupTempFile($tempPath);
            }
        } catch (Exception $e) {
            Log::error("PDF Analysis Error", [
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
                "file" => $request->file("pdf")
                    ? $request->file("pdf")->getClientOriginalName()
                    : "unknown",
            ]);

            return $this->errorResponse(
                $this->getUserFriendlyErrorMessage($e),
                $this->getErrorStatusCode($e),
            );
        }
    }

    /**
     * Validate the incoming request
     */
    private function validateRequest(
        Request $request,
    ): \Illuminate\Contracts\Validation\Validator {
        return Validator::make(
            $request->all(),
            [
                "pdf" => "required|file|mimes:pdf|max:22240",
            ],
            [
                "pdf.required" => "Un fichier PDF est requis",
                "pdf.file" => 'Le fichier téléchargé n\'est pas valide',
                "pdf.mimes" => "Seuls les fichiers PDF sont acceptés",
                "pdf.max" => "Le fichier ne peut pas dépasser 20MB",
            ],
        );
    }

    /**
     * Generate unique filename for temporary storage
     */
    private function generateUniqueFilename($file): string
    {
        $originalName = pathinfo(
            $file->getClientOriginalName(),
            PATHINFO_FILENAME,
        );
        $extension = $file->getClientOriginalExtension();
        $uniqueId = Str::uuid();

        return "pdf_analysis_{$uniqueId}_{$originalName}.{$extension}";
    }

    /**
     * Store file temporarily for processing
     */
    private function storeTempFile($file, string $filename): string
    {
        $tempDir = "temp/pdf_analysis";
        $path = $file->storeAs($tempDir, $filename, "local");

        return Storage::disk("local")->path($path);
    }

    /**
     * Prepare extracted text for ChatGPT analysis
     */
    private function prepareTextForAnalysis(string $text): string
    {
        // Clean up the text
        $text = preg_replace("/\s+/", " ", $text); // Normalize whitespace
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
    private function processAnalysisResult(
        array $analysisResult,
        $originalFile,
    ): array {
        // Add metadata about the original file
        $analysisResult["file_metadata"] = [
            "original_name" => $originalFile->getClientOriginalName(),
            "file_size" => $originalFile->getSize(),
            "file_size_human" => $this->formatFileSize(
                $originalFile->getSize(),
            ),
            "upload_time" => now()->toISOString(),
        ];

        // Add processing metadata
        $analysisResult["analysis_metadata"] = array_merge(
            $analysisResult["analysis_metadata"] ?? [],
            [
                "processed_at" => now()->toISOString(),
                "server_version" => app()->version(),
                "processing_server" => gethostname(),
            ],
        );

        // Ensure required fields have defaults
        $analysisResult = array_merge(
            [
                "title" => "Document Sans Titre",
                "author" => "Auteur Inconnu",
                "pages" => 0,
                "readingTime" => "0 min",
                "difficulty" => "Intermédiaire",
                "rating" => 3.0,
                "genre" => "Non Classifié",
                "tags" => [],
                "summary" => [],
            ],
            $analysisResult,
        );

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
            Log::warning("Failed to cleanup temp file", [
                "file" => $filePath,
                "error" => $e->getMessage(),
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
            "file not found" => 'Le fichier n\'a pas pu être traité',
            "invalid pdf" => "Le fichier PDF semble être corrompu",
            "text extraction failed" =>
                'Impossible d\'extraire le texte du PDF',
            "chatgpt api error" => 'Erreur lors de l\'analyse par l\'IA',
            "timeout" => 'L\'analyse a pris trop de temps',
            "rate limit" => "Trop de requêtes simultanées, veuillez réessayer",
            "insufficient content" =>
                "Le document ne contient pas assez de contenu à analyser",
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

        if (stripos($message, "validation") !== false) {
            return 422;
        }
        if (stripos($message, "not found") !== false) {
            return 404;
        }
        if (stripos($message, "unauthorized") !== false) {
            return 401;
        }
        if (stripos($message, "forbidden") !== false) {
            return 403;
        }
        if (stripos($message, "timeout") !== false) {
            return 408;
        }
        if (stripos($message, "rate limit") !== false) {
            return 429;
        }
        if (stripos($message, "too large") !== false) {
            return 413;
        }

        return 500;
    }

    /**
     * Format file size in human readable format
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ["B", "KB", "MB", "GB"];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . " " . $units[$pow];
    }

    /**
     * Return success response
     */
    private function successResponse(string $message, array $data): JsonResponse
    {
        return response()->json(
            [
                "success" => true,
                "message" => $message,
                "data" => $data,
                "timestamp" => now()->toISOString(),
            ],
            200,
        );
    }

    /**
     * Return error response
     */
    private function errorResponse(
        string $message,
        int $status = 500,
        array $details = [],
    ) {
        $response = [
            "success" => false,
            "error" => $message,
            "timestamp" => now()->toISOString(),
        ];

        if (!empty($details)) {
            $response["details"] = $details;
        }

        return response()->json($response, $status);
    }

    public function summarizePage(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                "text" => "required|string|max:50000",
                "book_title" => "required|string|max:255",
                "page_number" => "required|integer|min:1",
            ]);

            if ($validator->fails()) {
                Log::error("error ai summarize : " . $validator->errors());
                throw new Exception($validator->errors());
            }

            $text = $request->input("text");
            $bookTitle = $request->input("book_title");
            $pageNumber = $request->input("page_number");

            // Prepare prompt for summarization
            $prompt = $this->buildSummaryPrompt($text, $bookTitle, $pageNumber);

            // Get summary from ChatGPT
            $summary = $this->chatGPTService->generateSummary($prompt);

            Log::info("Page summarized successfully", [
                "book_title" => $bookTitle,
                "page_number" => $pageNumber,
                "text_length" => strlen($text),
            ]);

            return $this->successResponse("Page summarized successfully", [
                "summary" => $summary,
                "book_title" => $bookTitle,
                "page_number" => $pageNumber,
                "word_count" => str_word_count($text),
            ]);
        } catch (Exception $e) {
            Log::error("Error summarizing page", [
                "error" => $e->getMessage(),
                "book_title" => $request->input("book_title", "unknown"),
                "page_number" => $request->input("page_number", "unknown"),
            ]);

            return $this->errorResponse(
                "Failed to summarize page. Please try again.",
                500,
            );
        }
    }

    /**
     * Explain key concepts from a page
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function explainConcepts(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                "text" => "required|string|max:50000",
                "book_title" => "required|string|max:255",
                "page_number" => "required|integer|min:1",
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    "Invalid data provided",
                    422,
                    $validator->errors(),
                );
            }

            $text = $request->input("text");
            $bookTitle = $request->input("book_title");
            $pageNumber = $request->input("page_number");

            // Prepare prompt for concept explanation
            $prompt = $this->buildConceptExplanationPrompt(
                $text,
                $bookTitle,
                $pageNumber,
            );

            // Get explanation from ChatGPT
            $explanation = $this->chatGPTService->explainConcepts($prompt);

            Log::info("Concepts explained successfully", [
                "book_title" => $bookTitle,
                "page_number" => $pageNumber,
            ]);

            return $this->successResponse("Concepts explained successfully", [
                "explanation" => $explanation,
                "book_title" => $bookTitle,
                "page_number" => $pageNumber,
            ]);
        } catch (Exception $e) {
            Log::error("Error explaining concepts", [
                "error" => $e->getMessage(),
                "book_title" => $request->input("book_title", "unknown"),
            ]);

            return $this->errorResponse(
                "Failed to explain concepts. Please try again.",
                500,
            );
        }
    }

    /**
     * Chat about book content with AI assistant
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function chatAboutBook(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                "question" => "required|string|max:1000",
                "page_text" => "required|string|max:50000",
                "book_title" => "required|string|max:255",
                "page_number" => "required|integer|min:1",
                "chat_history" => "nullable|array|max:20",
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    "Invalid data provided",
                    422,
                    $validator->errors(),
                );
            }

            $question = $request->input("question");
            $pageText = $request->input("page_text");
            $bookTitle = $request->input("book_title");
            $pageNumber = $request->input("page_number");
            $chatHistory = $request->input("chat_history", []);

            // Build conversation context
            $context = $this->buildChatContext(
                $pageText,
                $bookTitle,
                $pageNumber,
                $chatHistory,
            );

            // Get AI response
            $answer = $this->chatGPTService->chatWithContext(
                $question,
                $context,
            );

            Log::info("AI chat response generated", [
                "book_title" => $bookTitle,
                "page_number" => $pageNumber,
                "question_length" => strlen($question),
            ]);

            return $this->successResponse("Response generated successfully", [
                "answer" => $answer,
                "question" => $question,
                "page_number" => $pageNumber,
                "timestamp" => now()->toISOString(),
            ]);
        } catch (Exception $e) {
            Log::error("Error in AI chat", [
                "error" => $e->getMessage(),
                "question" => $request->input("question", "unknown"),
            ]);

            return $this->errorResponse(
                "Sorry, I encountered an error. Please try again.",
                500,
            );
        }
    }

    /**
     * Get AI-powered reading recommendations based on current book
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getReadingRecommendations(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                "book_title" => "required|string|max:255",
                "book_author" => "required|string|max:255",
                "book_genre" => "nullable|string|max:100",
                "current_progress" => "nullable|integer|min:0|max:100",
                "user_preferences" => "nullable|array",
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    "Invalid data provided",
                    422,
                    $validator->errors(),
                );
            }

            $bookTitle = $request->input("book_title");
            $bookAuthor = $request->input("book_author");
            $bookGenre = $request->input("book_genre");
            $userPreferences = $request->input("user_preferences", []);

            // Build recommendation prompt
            $prompt = $this->buildRecommendationPrompt(
                $bookTitle,
                $bookAuthor,
                $bookGenre,
                $userPreferences,
            );

            // Get recommendations from AI
            $recommendations = $this->chatGPTService->getRecommendations(
                $prompt,
            );

            return $this->successResponse(
                "Recommendations generated successfully",
                [
                    "recommendations" => $recommendations,
                    "based_on_book" => $bookTitle,
                    "generated_at" => now()->toISOString(),
                ],
            );
        } catch (Exception $e) {
            Log::error("Error generating recommendations", [
                "error" => $e->getMessage(),
                "book_title" => $request->input("book_title", "unknown"),
            ]);

            return $this->errorResponse(
                "Failed to generate recommendations. Please try again.",
                500,
            );
        }
    }

    /**
     * Generate study notes from book content
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateStudyNotes(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                "text" => "required|string|max:50000",
                "book_title" => "required|string|max:255",
                "chapter_title" => "nullable|string|max:255",
                "note_type" =>
                    "required|in:bullet_points,flashcards,outline,mind_map",
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    "Invalid data provided",
                    422,
                    $validator->errors(),
                );
            }

            $text = $request->input("text");
            $bookTitle = $request->input("book_title");
            $chapterTitle = $request->input("chapter_title");
            $noteType = $request->input("note_type");

            // Build study notes prompt
            $prompt = $this->buildStudyNotesPrompt(
                $text,
                $bookTitle,
                $chapterTitle,
                $noteType,
            );

            // Generate study notes
            $studyNotes = $this->chatGPTService->generateStudyNotes($prompt);

            return $this->successResponse(
                "Study notes generated successfully",
                [
                    "study_notes" => $studyNotes,
                    "note_type" => $noteType,
                    "book_title" => $bookTitle,
                    "chapter_title" => $chapterTitle,
                ],
            );
        } catch (Exception $e) {
            Log::error("Error generating study notes", [
                "error" => $e->getMessage(),
                "note_type" => $request->input("note_type", "unknown"),
            ]);

            return $this->errorResponse(
                "Failed to generate study notes. Please try again.",
                500,
            );
        }
    }

    // Private helper methods

    private function buildSummaryPrompt(
        string $text,
        string $bookTitle,
        int $pageNumber,
    ): string {
        return "Please provide a concise summary of the following text from page {$pageNumber} of the book '{$bookTitle}'.
        Focus on the main points and key information. Keep the summary clear and informative.

        Text to summarize:
        {$text}

        Please respond in French and structure your summary with clear bullet points for better readability.";
    }

    private function buildConceptExplanationPrompt(
        string $text,
        string $bookTitle,
        int $pageNumber,
    ): string {
        return "Please identify and explain the key concepts, terms, or ideas from this text from page {$pageNumber} of '{$bookTitle}'.
        Provide clear explanations that would help a reader better understand the content.

        Text to analyze:
        {$text}

        Please respond in French and organize your explanations with:
        1. Key concepts identified
        2. Clear explanations for each concept
        3. How these concepts relate to each other if applicable";
    }

    private function buildChatContext(
        string $pageText,
        string $bookTitle,
        int $pageNumber,
        array $chatHistory,
    ): string {
        $context = "You are an AI reading assistant helping a user understand the book '{$bookTitle}'.
        The user is currently reading page {$pageNumber}. Here is the current page content:

        {$pageText}

        ";

        if (!empty($chatHistory)) {
            $context .= "Previous conversation:\n";
            foreach (array_slice($chatHistory, -5) as $message) {
                $role = $message["type"] === "user" ? "User" : "Assistant";
                $context .= "{$role}: {$message["content"]}\n";
            }
        }

        $context .=
            "\nPlease answer the user's questions about this content in French. Be helpful, accurate, and educational.";

        return $context;
    }

    private function buildRecommendationPrompt(
        string $bookTitle,
        string $bookAuthor,
        ?string $bookGenre,
        array $userPreferences,
    ): string {
        $prompt = "Based on the book '{$bookTitle}' by {$bookAuthor}";

        if ($bookGenre) {
            $prompt .= " (Genre: {$bookGenre})";
        }

        $prompt .=
            ", please recommend 5 similar books that the reader might enjoy. ";

        if (!empty($userPreferences)) {
            $prompt .=
                "Consider these user preferences: " .
                implode(", ", $userPreferences) .
                ". ";
        }

        $prompt .= "For each recommendation, provide:
        1. Book title and author
        2. Brief description (2-3 sentences)
        3. Why it's similar to the current book
        4. Difficulty level

        Please respond in French and format as a numbered list.";

        return $prompt;
    }

    private function buildStudyNotesPrompt(
        string $text,
        string $bookTitle,
        ?string $chapterTitle,
        string $noteType,
    ): string {
        $typeInstructions = [
            "bullet_points" =>
                "Create organized bullet points highlighting the main ideas and supporting details.",
            "flashcards" =>
                "Create question-answer pairs suitable for flashcard study.",
            "outline" =>
                "Create a hierarchical outline with main topics and subtopics.",
            "mind_map" =>
                "Create a mind map structure with central themes and connected concepts.",
        ];

        $instruction =
            $typeInstructions[$noteType] ?? "Create organized study notes.";

        $prompt = "Create study notes from the following text from the book '{$bookTitle}'";

        if ($chapterTitle) {
            $prompt .= ", chapter '{$chapterTitle}'";
        }

        $prompt .= ". {$instruction}

        Text to process:
        {$text}

        Please respond in French and make the notes clear, concise, and easy to study from.";

        return $prompt;
    }
}
