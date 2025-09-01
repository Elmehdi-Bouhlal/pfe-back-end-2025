<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\ChatGPTService;
use App\Models\Book;
use Exception;

class BookSearchController extends Controller
{
    private ChatGPTService $chatGPTService;

    public function __construct(ChatGPTService $chatGPTService)
    {
        $this->chatGPTService = $chatGPTService;
    }

    /**
     * Search for book recommendations using AI based on user description
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchBooksWithAI(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = $this->validateSearchRequest($request);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Données de requête invalides',
                    422,
                    ['validation_errors' => $validator->errors()]
                );
            }

            $description = $request->input('description');
            $genre = $request->input('genre');
            $language = $request->input('language');
            $maxResults = (int) $request->input('max_results', 10);

            Log::info('AI Book Search Started', [
                'description_length' => strlen($description),
                'genre' => $genre,
                'language' => $language,
                'max_results' => $maxResults
            ]);

            // Build the AI prompt for book recommendations
            $prompt = $this->buildBookSearchPrompt($description, $genre, $language, $maxResults);

            // Get recommendations from ChatGPT
            $startTime = microtime(true);
            $aiResponse = $this->chatGPTService->analyzeBookContent($prompt, [
                'search_type' => 'book_recommendations',
                'user_query' => substr($description, 0, 100)
            ]);
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            // Parse and validate the AI response
            $recommendations = $this->parseRecommendations($aiResponse);

            // Enhance recommendations with additional data
            $enhancedRecommendations = $this->enhanceRecommendations($recommendations, $description);

            Log::info('AI Book Search Completed', [
                'recommendations_count' => count($enhancedRecommendations),
                'processing_time_ms' => $processingTime
            ]);

            return $this->successResponse(
                'Recommandations générées avec succès',
                [
                    'recommendations' => $enhancedRecommendations,
                    'total_results' => count($enhancedRecommendations),
                    'processing_time' => $processingTime,
                    'search_metadata' => [
                        'query_analyzed' => substr($description, 0, 50) . '...',
                        'ai_model' => 'ChatGPT-4',
                        'search_date' => now()->toISOString()
                    ]
                ]
            );

        } catch (Exception $e) {
            Log::error('AI Book Search Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->only(['description', 'genre', 'language'])
            ]);

            return $this->errorResponse(
                $this->getUserFriendlyErrorMessage($e),
                $this->getErrorStatusCode($e)
            );
        }
    }

    /**
     * Check if a recommended book is available in our database
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkBookAvailability(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'author' => 'required|string|max:255',
                'isbn' => 'nullable|string|max:20'
            ], [
                'title.required' => 'Le titre du livre est requis',
                'author.required' => 'L\'auteur du livre est requis'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Données de requête invalides',
                    422,
                    ['validation_errors' => $validator->errors()]
                );
            }

            $title = $request->input('title');
            $author = $request->input('author');
            $isbn = $request->input('isbn');

            Log::info('Book Availability Check Started', [
                'title' => $title,
                'author' => $author,
                'isbn' => $isbn
            ]);

            // Search for the book in our database
            $availabilityData = $this->findBookInDatabase($title, $author, $isbn);

            Log::info('Book Availability Check Completed', [
                'available' => $availabilityData['available'],
                'book_id' => $availabilityData['book']->id ?? null
            ]);

            return $this->successResponse(
                'Vérification de disponibilité terminée',
                $availabilityData
            );

        } catch (Exception $e) {
            Log::error('Book Availability Check Error', [
                'error' => $e->getMessage(),
                'request_data' => $request->only(['title', 'author', 'isbn'])
            ]);

            return $this->errorResponse(
                'Erreur lors de la vérification de disponibilité',
                500
            );
        }
    }

    /**
     * Build the AI prompt for book search
     */
    private function buildBookSearchPrompt(string $description, ?string $genre, ?string $language, int $maxResults): string
    {
        $genreFilter = $genre ? "Genre préféré: {$genre}" : "Tous genres acceptés";
        $languageFilter = $language ? "Langue préférée: {$language}" : "Toutes langues acceptées";

        return "Tu es un expert littéraire et bibliothécaire numérique spécialisé dans les recommandations personnalisées de livres.

DESCRIPTION DE L'UTILISATEUR:
{$description}

CRITÈRES SUPPLÉMENTAIRES:
- {$genreFilter}
- {$languageFilter}
- Nombre maximum de suggestions: {$maxResults}

INSTRUCTIONS:
Analyse cette description et recommande des livres qui correspondent exactement à ce que recherche l'utilisateur. Je veux que ta réponse soit UNIQUEMENT un objet JSON valide avec cette structure:

{
  \"recommendations\": [
    {
      \"title\": \"Titre exact du livre\",
      \"author\": \"Nom complet de l'auteur\",
      \"genre\": \"Genre principal\",
      \"rating\": 4.2,
      \"description\": \"Description détaillée du livre et pourquoi il correspond à la demande\",
      \"tags\": [\"tag1\", \"tag2\", \"tag3\"],
      \"matchScore\": 95,
      \"recommendationReason\": \"Explication détaillée de pourquoi ce livre correspond parfaitement à la description de l'utilisateur\",
      \"pages\": 320,
      \"language\": \"Français\",
      \"year\": 2020,
      \"isbn\": \"978-2-XXX-XXXXX-X\",
      \"publisher\": \"Nom de l'éditeur\"
    }
  ],
  \"search_analysis\": {
    \"detected_genres\": [\"genre1\", \"genre2\"],
    \"detected_themes\": [\"thème1\", \"thème2\"],
    \"user_preferences\": [\"préférence1\", \"préférence2\"],
    \"search_strategy\": \"Explication de la stratégie utilisée pour cette recherche\"
  }
}

RÈGLES IMPORTANTES:
1. Recommande des livres réels et populaires qui existent vraiment
2. Le matchScore (0-100) doit refléter la pertinence par rapport à la description
3. Classe les résultats par pertinence décroissante (matchScore le plus élevé en premier)
4. Varie les genres et auteurs pour offrir de la diversité
5. La recommendationReason doit être personnalisée et détaillée
6. Utilise un français parfait et professionnel
7. Si aucun genre n'est spécifié, propose une variété de genres pertinents
8. Priorise les livres bien notés et reconnus
9. Adapte les recommandations à l'âge supposé de l'utilisateur si déductible
10. Réponds UNIQUEMENT avec le JSON, pas de texte explicatif

Assure-toi que chaque recommandation soit une excellente correspondance avec la description fournie.";
    }

    /**
     * Parse recommendations from AI response
     */
    private function parseRecommendations(array $aiResponse): array
    {
        // The AI response should already be parsed JSON from ChatGPTService
        if (isset($aiResponse['recommendations']) && is_array($aiResponse['recommendations'])) {
            return $aiResponse['recommendations'];
        }

        // Fallback: try to extract from different response format
        if (isset($aiResponse['summary']) && is_array($aiResponse['summary'])) {
            // This shouldn't happen with book search, but handle gracefully
            return [];
        }

        // If no recommendations found, return empty array
        return [];
    }

    /**
     * Enhance recommendations with additional data and scoring
     */
    private function enhanceRecommendations(array $recommendations, string $originalQuery): array
    {
        $enhanced = [];

        foreach ($recommendations as $index => $book) {
            // Ensure all required fields exist
            $enhanced[] = array_merge([
                'title' => 'Titre Non Disponible',
                'author' => 'Auteur Inconnu',
                'genre' => 'Non Classifié',
                'rating' => 3.0,
                'description' => 'Description non disponible.',
                'tags' => [],
                'matchScore' => 50,
                'recommendationReason' => 'Recommandation basée sur vos critères.',
                'pages' => null,
                'language' => 'Non spécifié',
                'year' => null,
                'isbn' => null,
                'publisher' => null,
                'searchRank' => $index + 1,
                'aiGenerated' => true,
                'searchQuery' => substr($originalQuery, 0, 100)
            ], $book);
        }

        // Sort by match score (highest first)
        usort($enhanced, function ($a, $b) {
            return ($b['matchScore'] ?? 0) <=> ($a['matchScore'] ?? 0);
        });

        return $enhanced;
    }

    /**
     * Find book in our database using multiple search strategies
     */
    private function findBookInDatabase(string $title, string $author, ?string $isbn): array
    {
        $book = null;
        $searchStrategy = '';

        // Strategy 1: Exact ISBN match (most reliable)
        if ($isbn) {
            $book = Book::where('isbn', $isbn)
                         ->where('status', 'available')
                         ->first();
            if ($book) {
                $searchStrategy = 'exact_isbn_match';
            }
        }

        // Strategy 2: Exact title and author match
        if (!$book) {
            $book = Book::where('title', $title)
                        ->where('author', $author)
                        ->where('status', 'available')
                        ->first();
            if ($book) {
                $searchStrategy = 'exact_title_author_match';
            }
        }

        // Strategy 3: Fuzzy title match with same author
        if (!$book) {
            $book = Book::where('author', $author)
                        ->where('title', 'LIKE', '%' . $title . '%')
                        ->where('status', 'available')
                        ->first();
            if ($book) {
                $searchStrategy = 'fuzzy_title_exact_author';
            }
        }

        // Strategy 4: Fuzzy author match with exact title
        if (!$book) {
            $book = Book::where('title', $title)
                        ->where('author', 'LIKE', '%' . $author . '%')
                        ->where('status', 'available')
                        ->first();
            if ($book) {
                $searchStrategy = 'exact_title_fuzzy_author';
            }
        }

        // Strategy 5: Double fuzzy match (least reliable)
        if (!$book) {
            $book = Book::where('title', 'LIKE', '%' . $title . '%')
                        ->where('author', 'LIKE', '%' . $author . '%')
                        ->where('status', 'available')
                        ->first();
            if ($book) {
                $searchStrategy = 'double_fuzzy_match';
            }
        }

        // Count available copies if book is found
        $copiesCount = 0;
        if ($book) {
            $copiesCount = Book::where('title', $book->title)
                              ->where('author', $book->author)
                              ->where('status', 'available')
                              ->count();
        }

        return [
            'available' => $book !== null,
            'book' => $book,
            'copies_count' => $copiesCount,
            'search_strategy' => $searchStrategy,
            'searched_criteria' => [
                'title' => $title,
                'author' => $author,
                'isbn' => $isbn
            ]
        ];
    }

    /**
     * Validate search request
     */
    private function validateSearchRequest(Request $request): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($request->all(), [
            'description' => 'required|string|min:10|max:1000',
            'genre' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:50',
            'max_results' => 'nullable|integer|min:1|max:50'
        ], [
            'description.required' => 'Une description est requise',
            'description.min' => 'La description doit contenir au moins 10 caractères',
            'description.max' => 'La description ne peut pas dépasser 1000 caractères',
            'max_results.max' => 'Le nombre maximum de résultats est de 50'
        ]);
    }

    /**
     * Get user-friendly error message
     */
    private function getUserFriendlyErrorMessage(Exception $e): string
    {
        $message = strtolower($e->getMessage());

        $errorMappings = [
            'chatgpt api error' => 'Service d\'IA temporairement indisponible',
            'rate limit' => 'Trop de requêtes, veuillez réessayer dans quelques minutes',
            'timeout' => 'La recherche a pris trop de temps, veuillez réessayer',
            'invalid json' => 'Erreur dans le traitement de la réponse IA',
            'insufficient content' => 'Votre description n\'est pas assez détaillée'
        ];

        foreach ($errorMappings as $technical => $friendly) {
            if (stripos($message, $technical) !== false) {
                return $friendly;
            }
        }

        return 'Une erreur inattendue s\'est produite lors de la recherche';
    }

    /**
     * Get error status code
     */
    private function getErrorStatusCode(Exception $e): int
    {
        $message = strtolower($e->getMessage());

        if (stripos($message, 'validation') !== false) return 422;
        if (stripos($message, 'not found') !== false) return 404;
        if (stripos($message, 'rate limit') !== false) return 429;
        if (stripos($message, 'timeout') !== false) return 408;

        return 500;
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