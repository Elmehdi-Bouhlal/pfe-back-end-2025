<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Service for integrating with ChatGPT API for book analysis
 * 
 * @package App\Services
 */
class ChatGPTService
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private int $maxTokens;
    private float $temperature;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');
        $this->model = config('services.openai.model', 'gpt-4');
        $this->maxTokens = config('services.openai.max_tokens', 4000);
        $this->temperature = config('services.openai.temperature', 0.7);
        
        if (empty($this->apiKey)) {
            throw new Exception('OpenAI API key not configured');
        }
    }

    /**
     * Analyze book content using ChatGPT
     *
     * @param string $content The extracted text from PDF
     * @param array $metadata Additional metadata about the file
     * @return array Structured analysis result
     * @throws Exception
     */
    public function analyzeBookContent(string $content, array $metadata = []): array
    {
        try {
            // Prepare the prompt for book analysis
            $prompt = $this->buildAnalysisPrompt($content, $metadata);
            
            // Make API request to ChatGPT
            $response = $this->makeAPIRequest($prompt);
     
            $analysisResult = $this->parseAnalysisResponse($response);
            Log::info(json_encode($analysisResult));
            Log::info('ChatGPT Analysis Completed', [
                'token_usage' => $response['usage'] ?? null,
                'model' => $this->model
            ]);
            
            return $analysisResult;
            
        } catch (Exception $e) {
            Log::error('ChatGPT Analysis Error', [
                'error' => $e->getMessage(),
                'metadata' => $metadata
            ]);
            throw $e;
        }
    }

    /**
     * Build the analysis prompt for ChatGPT
     */
    private function buildAnalysisPrompt(string $content, array $metadata): string
    {
        $filename = $metadata['filename'] ?? 'document.pdf';
        $pageCount = $metadata['pages'] ?? 'inconnu';
        $wordCount = $metadata['word_count'] ?? 'inconnu';

        return "Tu es un expert littéraire et analyste de contenu spécialisé dans la création de résumés détaillés et structurés de livres. 

INFORMATIONS SUR LE DOCUMENT:
- Nom du fichier: {$filename}
- Nombre de pages: {$pageCount}
- Nombre de mots: {$wordCount}

CONTENU À ANALYSER:
{$content}

INSTRUCTIONS:
Analyse ce contenu et crée un résumé complet sous forme de livre digital paginé. Je veux que ta réponse soit UNIQUEMENT un objet JSON valide (pas de texte avant ou après) avec la structure exacte suivante:

{
  \"title\": \"Titre exact du livre\",
  \"author\": \"Nom de l'auteur\",
  \"originalLanguage\": \"Langue du livre\",
  \"publishYear\": 2023,
  \"isbn\": \"ISBN si trouvé ou null\",
  \"publisher\": \"Éditeur si trouvé ou null\",
  
  \"pages\": 250,
  \"readingTime\": \"4h 30min\",
  \"difficulty\": \"Intermédiaire\",
  \"rating\": 4.2,
  \"wordCount\": 75000,
  
  \"genre\": \"Genre principal\",
  \"subgenres\": [\"Sous-genre 1\", \"Sous-genre 2\"],
  \"tags\": [\"tag1\", \"tag2\", \"tag3\", \"tag4\", \"tag5\"],
  \"topics\": [\"Sujet 1\", \"Sujet 2\", \"Sujet 3\"],
  \"targetAudience\": \"Public cible\",
  
  \"summary\": [
    {
      \"chapter\": \"Introduction\",
      \"content\": [
        \"Premier paragraphe du résumé de cette section.\",
        \"Deuxième paragraphe avec les détails importants.\",
        \"Troisième paragraphe avec les conclusions.\"
      ],
      \"keyPoints\": [
        \"Point clé numéro 1\",
        \"Point clé numéro 2\",
        \"Point clé numéro 3\"
      ]
    },
    {
      \"chapter\": \"Chapitre 1: Titre du chapitre\",
      \"content\": [
        \"Paragraphe 1 du chapitre 1.\",
        \"Paragraphe 2 du chapitre 1.\",
        \"Paragraphe 3 du chapitre 1.\"
      ],
      \"keyPoints\": [
        \"Point important du chapitre\",
        \"Autre point à retenir\"
      ]
    }
  ],
  
  \"mainThemes\": [\"Thème 1\", \"Thème 2\", \"Thème 3\"],
  \"keyTakeaways\": [
    \"Leçon principale numéro 1\",
    \"Leçon principale numéro 2\",
    \"Leçon principale numéro 3\"
  ],
  \"strengths\": [
    \"Point fort du livre\",
    \"Autre point fort\"
  ],
  \"weaknesses\": [
    \"Point faible identifié\",
    \"Autre limitation\"
  ],
  
  \"similarBooks\": [
    {
      \"title\": \"Livre similaire 1\",
      \"author\": \"Auteur du livre similaire\",
      \"reason\": \"Pourquoi il est similaire\"
    }
  ],
  
  \"analysisMetadata\": {
    \"analysisDate\": \"" . now()->toISOString() . "\",
    \"processingTime\": 45.2,
    \"confidence\": 4,
    \"model\": \"ChatGPT-4\",
    \"language\": \"fr\"
  }
}

RÈGLES IMPORTANTES:
1. Le résumé doit contenir entre 5 et 15 pages selon la longueur du contenu
2. Chaque page doit avoir 2-4 paragraphes dans 'content'
3. Les keyPoints sont optionnels mais recommandés
4. Utilise un français parfait et professionnel
5. Adapte la difficulté selon le contenu (Débutant/Intermédiaire/Avancé/Expert)
6. La note (rating) doit être entre 1.0 et 5.0 basée sur la qualité perçue
7. Les tags doivent être des mots-clés pertinents en minuscules
8. Estime le temps de lecture: environ 250 mots par minute
9. Réponds UNIQUEMENT avec le JSON, pas de texte explicatif

Si le contenu n'est pas un livre ou est insuffisant, crée quand même une structure valide en l'adaptant au contenu disponible.";
    }

    /**
     * Make API request to ChatGPT
     */
    private function makeAPIRequest(string $prompt): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ])
        ->timeout(300) // 5 minutes timeout
        ->post($this->baseUrl . '/chat/completions', [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Tu es un expert littéraire et analyste de contenu. Tu créés des résumés structurés et détaillés de livres sous forme JSON.'
                ],
                [
                    'role' => 'user', 
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'response_format' => ['type' => 'json_object']
        ]);

        if (!$response->successful()) {
            $error = $response->json()['error'] ?? [];
            $errorMessage = $error['message'] ?? 'Unknown API error';
            $errorCode = $error['code'] ?? 'api_error';
            
            throw new Exception("ChatGPT API Error [{$errorCode}]: {$errorMessage}");
        }

        return $response->json();
    }

    /**
     * Parse and validate the ChatGPT response
     */
    private function parseAnalysisResponse(array $response): array
    {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception('Invalid response format from ChatGPT');
        }

        $content = $response['choices'][0]['message']['content'];
        
        // Decode JSON response
        $analysisResult = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('JSON Decode Error', [
                'error' => json_last_error_msg(),
                'content' => $content
            ]);
            throw new Exception('Invalid JSON response from ChatGPT: ' . json_last_error_msg());
        }

        // Validate required fields and add defaults
        $analysisResult = $this->validateAndEnhanceResult($analysisResult);
        
        return $analysisResult;
    }

    /**
     * Validate and enhance the analysis result
     */
    private function validateAndEnhanceResult(array $result): array
    {
        // Required fields with defaults
        $defaults = [
            'title' => 'Document Analysé',
            'author' => 'Auteur Inconnu',
            'originalLanguage' => 'Français',
            'pages' => 1,
            'readingTime' => '5 min',
            'difficulty' => 'Intermédiaire',
            'rating' => 3.0,
            'genre' => 'Non Classifié',
            'tags' => ['document', 'analyse'],
            'summary' => [],
            'mainThemes' => [],
            'keyTakeaways' => [],
            'strengths' => [],
            'weaknesses' => [],
            'similarBooks' => []
        ];

        // Merge with defaults
        $result = array_merge($defaults, $result);

        // Validate and fix specific fields
        $result['rating'] = max(1.0, min(5.0, (float) $result['rating']));
        $result['pages'] = max(1, (int) $result['pages']);
        
        // Ensure arrays are arrays
        $arrayFields = ['tags', 'summary', 'mainThemes', 'keyTakeaways', 'strengths', 'weaknesses', 'similarBooks'];
        foreach ($arrayFields as $field) {
            if (!is_array($result[$field])) {
                $result[$field] = [];
            }
        }

        // Validate difficulty
        $validDifficulties = ['Débutant', 'Intermédiaire', 'Avancé', 'Expert'];
        if (!in_array($result['difficulty'], $validDifficulties)) {
            $result['difficulty'] = 'Intermédiaire';
        }

        // Ensure summary has at least one page
        if (empty($result['summary'])) {
            $result['summary'] = [
                [
                    'chapter' => 'Résumé',
                    'content' => [
                        'Ce document a été analysé automatiquement.',
                        'Le contenu principal traite de sujets variés.',
                        'Une analyse plus détaillée pourrait nécessiter un document plus structuré.'
                    ],
                    'keyPoints' => [
                        'Document analysé automatiquement',
                        'Contenu extractable limité'
                    ]
                ]
            ];
        }

        // Add analysis metadata if not present
        if (!isset($result['analysisMetadata'])) {
            $result['analysisMetadata'] = [
                'analysisDate' => now()->toISOString(),
                'processingTime' => 0,
                'confidence' => 3,
                'model' => $this->model,
                'language' => 'fr'
            ];
        }

        return $result;
    }

    /**
     * Get available models for testing/configuration
     */
    public function getAvailableModels(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey
            ])->get($this->baseUrl . '/models');

            if ($response->successful()) {
                return $response->json()['data'] ?? [];
            }
        } catch (Exception $e) {
            Log::warning('Failed to fetch available models', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Test the API connection
     */
    public function testConnection(): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey
            ])->get($this->baseUrl . '/models');

            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }
}