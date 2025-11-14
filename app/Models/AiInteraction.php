<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'interaction_type',
        'user_input',
        'ai_response',
        'page_number',
        'context_data',
        'response_time_ms',
        'user_rating'
    ];

    protected $casts = [
        'page_number' => 'integer',
        'context_data' => 'array',
        'response_time_ms' => 'integer',
        'user_rating' => 'float'
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('interaction_type', $type);
    }

    public function scopeByBook($query, $bookId)
    {
        return $query->where('book_id', $bookId);
    }

    public function scopeByPage($query, $page)
    {
        return $query->where('page_number', $page);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeRated($query)
    {
        return $query->whereNotNull('user_rating');
    }

    public function scopePositivelyRated($query)
    {
        return $query->where('user_rating', '>=', 4);
    }

    public function scopeFastResponses($query, $maxTimeMs = 5000)
    {
        return $query->where('response_time_ms', '<=', $maxTimeMs);
    }

    public function scopeSummarizations($query)
    {
        return $query->where('interaction_type', 'summarize');
    }

    public function scopeExplanations($query)
    {
        return $query->where('interaction_type', 'explain');
    }

    public function scopeChats($query)
    {
        return $query->where('interaction_type', 'chat');
    }

    public function scopeRecommendations($query)
    {
        return $query->where('interaction_type', 'recommend');
    }

    public function scopeStudyNotes($query)
    {
        return $query->where('interaction_type', 'study_notes');
    }

    // Accessors
    public function getFormattedResponseTimeAttribute()
    {
        if (!$this->response_time_ms) {
            return 'N/A';
        }

        if ($this->response_time_ms < 1000) {
            return $this->response_time_ms . 'ms';
        }

        return round($this->response_time_ms / 1000, 1) . 's';
    }

    public function getInteractionTypeDisplayAttribute()
    {
        $types = [
            'summarize' => 'Page Summary',
            'explain' => 'Concept Explanation',
            'chat' => 'Chat Question',
            'recommend' => 'Book Recommendation',
            'study_notes' => 'Study Notes'
        ];

        return $types[$this->interaction_type] ?? ucfirst($this->interaction_type);
    }

    public function getRatingDisplayAttribute()
    {
        if (!$this->user_rating) {
            return 'Not rated';
        }

        $stars = str_repeat('★', floor($this->user_rating));
        $halfStar = ($this->user_rating - floor($this->user_rating)) >= 0.5 ? '½' : '';
        $emptyStars = str_repeat('☆', 5 - ceil($this->user_rating));

        return $stars . $halfStar . $emptyStars . ' (' . $this->user_rating . '/5)';
    }

    public function getTruncatedInputAttribute($length = 100)
    {
        if (strlen($this->user_input) <= $length) {
            return $this->user_input;
        }

        return substr($this->user_input, 0, $length) . '...';
    }

    public function getTruncatedResponseAttribute($length = 200)
    {
        if (strlen($this->ai_response) <= $length) {
            return $this->ai_response;
        }

        return substr($this->ai_response, 0, $length) . '...';
    }

    // Methods
    public function addRating($rating)
    {
        $rating = max(1, min(5, $rating)); // Ensure rating is between 1 and 5
        return $this->update(['user_rating' => $rating]);
    }

    public function updateContext($key, $value)
    {
        $contextData = $this->context_data ?? [];
        $contextData[$key] = $value;
        return $this->update(['context_data' => $contextData]);
    }

    public function getContext($key, $default = null)
    {
        $contextData = $this->context_data ?? [];
        return $contextData[$key] ?? $default;
    }

    // Static methods
    public static function getAvailableTypes()
    {
        return [
            'summarize' => 'Page Summary',
            'explain' => 'Concept Explanation',
            'chat' => 'Chat Question',
            'recommend' => 'Book Recommendation',
            'study_notes' => 'Study Notes'
        ];
    }

    public static function logInteraction($userId, $bookId, $type, $userInput, $aiResponse, $pageNumber = null, $responseTimeMs = null)
    {
        return self::create([
            'user_id' => $userId,
            'book_id' => $bookId,
            'interaction_type' => $type,
            'user_input' => $userInput,
            'ai_response' => $aiResponse,
            'page_number' => $pageNumber,
            'response_time_ms' => $responseTimeMs
        ]);
    }

    public static function getInteractionStats($userId = null, $bookId = null)
    {
        $query = self::query();
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        if ($bookId) {
            $query->where('book_id', $bookId);
        }

        $totalInteractions = $query->count();
        $avgRating = $query->whereNotNull('user_rating')->avg('user_rating');
        $avgResponseTime = $query->whereNotNull('response_time_ms')->avg('response_time_ms');

        $typeBreakdown = $query->select('interaction_type', \DB::raw('count(*) as count'))
                              ->groupBy('interaction_type')
                              ->pluck('count', 'interaction_type')
                              ->toArray();

        return [
            'total_interactions' => $totalInteractions,
            'average_rating' => round($avgRating, 2),
            'average_response_time_ms' => round($avgResponseTime),
            'type_breakdown' => $typeBreakdown,
            'interactions_today' => $query->whereDate('created_at', today())->count(),
            'interactions_this_week' => $query->where('created_at', '>=', now()->startOfWeek())->count(),
            'interactions_this_month' => $query->where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    public static function getUserChatHistory($userId, $bookId, $limit = 20)
    {
        return self::where('user_id', $userId)
                   ->where('book_id', $bookId)
                   ->where('interaction_type', 'chat')
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get()
                   ->reverse()
                   ->values();
    }

    public static function getPopularQuestions($bookId = null, $limit = 10)
    {
        $query = self::select('user_input', \DB::raw('count(*) as frequency'))
                     ->where('interaction_type', 'chat')
                     ->groupBy('user_input')
                     ->orderBy('frequency', 'desc')
                     ->limit($limit);

        if ($bookId) {
            $query->where('book_id', $bookId);
        }

        return $query->get();
    }

    public static function getMostHelpfulResponses($limit = 10)
    {
        return self::whereNotNull('user_rating')
                   ->where('user_rating', '>=', 4)
                   ->orderBy('user_rating', 'desc')
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get();
    }
}