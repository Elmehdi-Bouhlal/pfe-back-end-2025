<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ReadingList extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'author',
        'genre',
        'total_pages',
        'current_page',
        'cover_image',
        'rating',
        'comment',
        'isbn',
        'due_date',
        'progress',
        'started_at',
        'completed_at',
        'sort_order'
    ];

    protected $casts = [
        'due_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_pages' => 'integer',
        'current_page' => 'integer',
        'rating' => 'integer',
        'progress' => 'integer',
        'sort_order' => 'integer'
    ];

    protected $appends = [
        'reading_progress',
        'time_to_complete',
        'is_overdue'
    ];

    // =====================
    // RELATIONS
    // =====================

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // =====================
    // ACCESSORS
    // =====================

    /**
     * Calculer le pourcentage de progression de lecture
     */
    public function getReadingProgressAttribute(): int
    {
        if ($this->type === 'task') {
            return $this->progress;
        }

        if ($this->type === 'book' && $this->total_pages > 0) {
            return (int) round(($this->current_page / $this->total_pages) * 100);
        }

        return 0;
    }

    /**
     * Estimer le temps restant pour terminer (en jours)
     */
    public function getTimeToCompleteAttribute(): ?int
    {
        if ($this->status === 'completed' || $this->type === 'task') {
            return null;
        }

        if (!$this->started_at || !$this->total_pages || $this->current_page <= 0) {
            return null;
        }

        $daysReading = $this->started_at->diffInDays(now()) + 1;
        $pagesPerDay = $this->current_page / $daysReading;
        
        if ($pagesPerDay <= 0) {
            return null;
        }

        $remainingPages = $this->total_pages - $this->current_page;
        return (int) ceil($remainingPages / $pagesPerDay);
    }

    /**
     * Vérifier si une tâche est en retard
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->type === 'task' && 
               $this->due_date && 
               $this->status !== 'completed' && 
               $this->due_date->isPast();
    }

    // =====================
    // SCOPES
    // =====================

    /**
     * Filtrer par utilisateur
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Filtrer par type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Filtrer par statut
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Ordonner par position dans la colonne
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    /**
     * Livres seulement
     */
    public function scopeBooks($query)
    {
        return $query->where('type', 'book');
    }

    /**
     * Tâches seulement
     */
    public function scopeTasks($query)
    {
        return $query->where('type', 'task');
    }

    /**
     * Éléments en retard
     */
    public function scopeOverdue($query)
    {
        return $query->where('type', 'task')
                    ->where('status', '!=', 'completed')
                    ->where('due_date', '<', now());
    }

    /**
     * Livres par genre
     */
    public function scopeByGenre($query, $genre)
    {
        return $query->where('type', 'book')->where('genre', $genre);
    }

    // =====================
    // METHODS
    // =====================

    /**
     * Marquer comme démarré
     */
    public function markAsStarted(): self
    {
        if ($this->status === 'to_read') {
            $this->update([
                'status' => 'reading',
                'started_at' => now()
            ]);
        }

        return $this;
    }

    /**
     * Marquer comme terminé
     */
    public function markAsCompleted(): self
    {
        $updateData = [
            'status' => 'completed',
            'completed_at' => now()
        ];

        // Pour les livres, mettre la page courante au maximum
        if ($this->type === 'book' && $this->total_pages) {
            $updateData['current_page'] = $this->total_pages;
        }

        // Pour les tâches, mettre le progrès à 100%
        if ($this->type === 'task') {
            $updateData['progress'] = 100;
        }

        $this->update($updateData);

        return $this;
    }

    /**
     * Mettre à jour la progression
     */
    public function updateProgress(int $currentPage = null, int $progress = null): self
    {
        $updateData = [];

        if ($this->type === 'book' && $currentPage !== null) {
            $updateData['current_page'] = min($currentPage, $this->total_pages ?? $currentPage);
            
            // Auto-compléter si toutes les pages sont lues
            if ($this->total_pages && $currentPage >= $this->total_pages) {
                $updateData['status'] = 'completed';
                $updateData['completed_at'] = now();
            }
        }

        if ($this->type === 'task' && $progress !== null) {
            $updateData['progress'] = min($progress, 100);
            
            // Auto-compléter si 100% atteint
            if ($progress >= 100) {
                $updateData['status'] = 'completed';
                $updateData['completed_at'] = now();
            }
        }

        // Marquer comme démarré si ce n'est pas encore fait
        if ($this->status === 'to_read' && !empty($updateData)) {
            $updateData['status'] = $updateData['status'] ?? 'reading';
            $updateData['started_at'] = $updateData['started_at'] ?? now();
        }

        if (!empty($updateData)) {
            $this->update($updateData);
        }

        return $this;
    }

    /**
     * Ajouter une note et un commentaire
     */
    public function addRating(int $rating, string $comment = null): self
    {
        $this->update([
            'rating' => max(1, min(5, $rating)),
            'comment' => $comment
        ]);

        return $this;
    }

    /**
     * Changer de statut avec gestion automatique des dates
     */
    public function changeStatus(string $newStatus): self
    {
        $updateData = ['status' => $newStatus];

        switch ($newStatus) {
            case 'reading':
                if (!$this->started_at) {
                    $updateData['started_at'] = now();
                }
                break;

            case 'completed':
                $updateData['completed_at'] = now();
                if ($this->type === 'book' && $this->total_pages) {
                    $updateData['current_page'] = $this->total_pages;
                }
                if ($this->type === 'task') {
                    $updateData['progress'] = 100;
                }
                break;

            case 'to_read':
                $updateData['started_at'] = null;
                $updateData['completed_at'] = null;
                if ($this->type === 'book') {
                    $updateData['current_page'] = 0;
                }
                if ($this->type === 'task') {
                    $updateData['progress'] = 0;
                }
                break;
        }

        $this->update($updateData);

        return $this;
    }

    /**
     * Réorganiser l'ordre dans une colonne
     */
    public static function reorderInColumn(int $userId, string $status, array $itemIds): void
    {
        foreach ($itemIds as $index => $itemId) {
            self::where('id', $itemId)
                ->where('user_id', $userId)
                ->where('status', $status)
                ->update(['sort_order' => $index]);
        }
    }

    /**
     * Obtenir les statistiques de l'utilisateur
     */
    public static function getUserStats(int $userId): array
    {
        $baseQuery = self::forUser($userId);

        return [
            'total_items' => (clone $baseQuery)->count(),
            'books' => (clone $baseQuery)->books()->count(),
            'tasks' => (clone $baseQuery)->tasks()->count(),
            'to_read' => (clone $baseQuery)->withStatus('to_read')->count(),
            'reading' => (clone $baseQuery)->withStatus('reading')->count(),
            'completed' => (clone $baseQuery)->withStatus('completed')->count(),
            'books_completed_this_month' => (clone $baseQuery)
                ->books()
                ->withStatus('completed')
                ->whereMonth('completed_at', now()->month)
                ->whereYear('completed_at', now()->year)
                ->count(),
            'pages_read_this_month' => (clone $baseQuery)
                ->books()
                ->withStatus('completed')
                ->whereMonth('completed_at', now()->month)
                ->whereYear('completed_at', now()->year)
                ->sum('total_pages'),
            'average_rating' => (clone $baseQuery)
                ->books()
                ->whereNotNull('rating')
                ->avg('rating'),
            'overdue_tasks' => (clone $baseQuery)->overdue()->count(),
            'genres' => (clone $baseQuery)
                ->books()
                ->whereNotNull('genre')
                ->groupBy('genre')
                ->selectRaw('genre, count(*) as count')
                ->pluck('count', 'genre')
                ->toArray()
        ];
    }

    /**
     * Obtenir la liste des genres de l'utilisateur
     */
    public static function getUserGenres(int $userId): array
    {
        return self::forUser($userId)
            ->books()
            ->whereNotNull('genre')
            ->distinct()
            ->pluck('genre')
            ->sort()
            ->values()
            ->toArray();
    }
}