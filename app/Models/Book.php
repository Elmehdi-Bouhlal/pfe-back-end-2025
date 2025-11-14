<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Book extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'author',
        'isbn',
        'description',
        'genre',
        'language',
        'book_type',
        'book_condition',
        'price',
        'original_price',
        'currency',
        'is_available',
        'is_negotiable',
        'user_id',
        'seller_notes',
        'file_path',
        'file_size',
        'file_format',
        'download_count',
        'view_count',
        'like_count',
        'share_count',
        'status',
        'published_at',
        'sold_at',
        'location_city',
        'location_region',
        'location_country',
        'quantity',
        'payment_methods', 
        'shipping_delay_days',
        'shipping_cities',
        'shipping_cost',
        'free_shipping_above',
        'free_shipping_threshold',
        // Nouveaux champs pour les livres numériques
        'pages',
        'download_limit',
        'sample_content',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'is_available' => 'boolean',
        'is_negotiable' => 'boolean',
        'file_size' => 'integer',
        'download_count' => 'integer',
        'view_count' => 'integer',
        'like_count' => 'integer',
        'share_count' => 'integer',
        'published_at' => 'datetime',
        'sold_at' => 'datetime',
        'payment_methods' => 'array',
        'shipping_cities' => 'array',
        'free_shipping_above' => 'boolean',
        'pages' => 'integer',
        'download_limit' => 'integer',
        'quantity' => 'integer',
        'shipping_delay_days' => 'integer',
        'shipping_cost' => 'decimal:2',
        'free_shipping_threshold' => 'decimal:2',
    ];

    protected $dates = [
        'published_at',
        'sold_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // ========== RELATIONS ==========

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function images()
    {
        return $this->hasMany(BookImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(BookImage::class)->where('is_primary', true);
    }

    // Nouvelle relation pour les fichiers numériques
    public function digitalFiles()
    {
        return $this->hasMany(BookDigitalFile::class);
    }

    public function categories()
    {
        return $this->belongsToMany(BookCategory::class, 'book_category_pivot', 'book_id', 'category_id')
                    ->withTimestamps();
    }

    public function likes()
    {
        return $this->hasMany(BookLike::class);
    }

    public function views()
    {
        return $this->hasMany(BookView::class);
    }

    public function conversations()
    {
        return $this->hasMany(BookConversation::class);
    }

    public function transactions()
    {
        return $this->hasMany(BookTransaction::class);
    }

    public function reviews()
    {
        return $this->hasMany(BookReview::class);
    }

    public function reports()
    {
        return $this->hasMany(BookReport::class);
    }

    // ========== SCOPES ==========

    public function scopePublished(Builder $query)
    {
        return $query->where('status', 'published');
    }

    public function scopeAvailable(Builder $query)
    {
        return $query->where('is_available', true)
                    ->where('status', 'published');
    }

    public function scopeByType(Builder $query, string $type)
    {
        return $query->where('book_type', $type);
    }

    // Nouveaux scopes pour les livres numériques
    public function scopeDigital(Builder $query)
    {
        return $query->where('book_type', 'digital');
    }

    public function scopePhysical(Builder $query)
    {
        return $query->where('book_type', 'physical');
    }

    public function scopeInLocation(Builder $query, string $city = null, string $region = null)
    {
        if ($city) {
            $query->where('location_city', $city);
        }
        if ($region) {
            $query->where('location_region', $region);
        }
        return $query;
    }

    public function scopeByLanguage(Builder $query, string $language)
    {
        return $query->where('language', $language);
    }

    public function scopePriceRange(Builder $query, float $min = null, float $max = null)
    {
        if ($min) {
            $query->where('price', '>=', $min);
        }
        if ($max) {
            $query->where('price', '<=', $max);
        }
        return $query;
    }

    public function scopeSearch(Builder $query, string $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('author', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('genre', 'like', "%{$search}%");
        });
    }

    public function scopePopular(Builder $query)
    {
        return $query->orderByDesc('view_count')
                    ->orderByDesc('like_count');
    }

    public function scopeRecent(Builder $query)
    {
        return $query->orderByDesc('published_at');
    }

    // ========== MÉTHODES EXISTANTES ==========

    public function isLikedBy(User $user = null): bool
    {
        if (!$user) return false;
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    public function incrementLikeCount(): void
    {
        $this->increment('like_count');
    }

    public function decrementLikeCount(): void
    {
        $this->decrement('like_count');
    }

    public function incrementShareCount(): void
    {
        $this->increment('share_count');
    }

    public function getDiscountPercentageAttribute(): ?float
    {
        if (!$this->original_price || $this->original_price <= $this->price) {
            return null;
        }
        return round((($this->original_price - $this->price) / $this->original_price) * 100, 2);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2) . ' ' . $this->currency;
    }

    public function getIsDigitalAttribute(): bool
    {
        return $this->book_type === 'digital';
    }

    public function getIsPhysicalAttribute(): bool
    {
        return $this->book_type === 'physical';
    }

    public function getAverageRatingAttribute(): float
    {
        return $this->reviews()->avg('rating') ?? 0;
    }

    public function getTotalReviewsAttribute(): int
    {
        return $this->reviews()->count();
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function canBeDeletedBy(User $user): bool
    {
        return $this->user_id === $user->id && $this->status !== 'sold';
    }

    public function markAsSold(): bool
    {
        return $this->update([
            'status' => 'sold',
            'is_available' => false,
            'sold_at' => now(),
        ]);
    }

    public function publish(): bool
    {
        return $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    // ========== NOUVELLES MÉTHODES POUR LES LIVRES NUMÉRIQUES ==========

    /**
     * Obtenir le fichier PDF principal
     */
    public function getPdfFile()
    {
        return $this->digitalFiles()->where('file_type', 'pdf')->where('is_active', true)->first();
    }

    /**
     * Obtenir l'image de couverture (digital ou physique)
     */
    public function getCoverImage()
    {
        // Pour les livres numériques, chercher d'abord dans les images avec type cover
        if ($this->book_type === 'digital') {
            $coverImage = $this->images()->where('image_type', 'cover')->first();
            if ($coverImage) {
                return $coverImage;
            }
        }
        
        // Sinon, chercher l'image primaire
        return $this->images()->where('is_primary', true)->first();
    }

    /**
     * Obtenir l'URL de téléchargement du PDF
     */
    public function getDownloadUrl()
    {
        if ($this->book_type !== 'digital') {
            return null;
        }
        
        $pdfFile = $this->getPdfFile();
        return $pdfFile ? Storage::url($pdfFile->file_path) : null;
    }

    /**
     * Vérifier si l'utilisateur peut télécharger ce livre
     */
    public function canBeDownloadedBy(User $user)
    {
        if ($this->book_type !== 'digital') {
            return false;
        }
        
        // Propriétaire du livre peut toujours télécharger
        if ($this->user_id === $user->id) {
            return true;
        }
        
        // TODO: Vérifier si l'utilisateur a acheté ce livre
        // return $this->purchases()->where('user_id', $user->id)->where('status', 'completed')->exists();
        
        return false; // Temporaire jusqu'à l'implémentation du système d'achat
    }

    /**
     * Incrémenter le compteur de téléchargements
     */
    public function incrementDownloadCount(): void
    {
        if ($this->book_type === 'digital') {
            $this->increment('download_count');
        }
    }

    /**
     * Vérifier si la limite de téléchargement est atteinte pour un utilisateur
     */
    public function hasReachedDownloadLimit(User $user): bool
    {
        if ($this->book_type !== 'digital' || !$this->download_limit) {
            return false;
        }

        // TODO: Compter les téléchargements de cet utilisateur pour ce livre
        // $userDownloads = BookDownload::where('book_id', $this->id)
        //                              ->where('user_id', $user->id)
        //                              ->count();
        // return $userDownloads >= $this->download_limit;
        
        return false; // Temporaire
    }

    /**
     * Obtenir la taille formatée du fichier PDF
     */
    public function getFormattedFileSizeAttribute(): ?string
    {
        if ($this->book_type !== 'digital') {
            return null;
        }

        $pdfFile = $this->getPdfFile();
        if (!$pdfFile) {
            return null;
        }

        $bytes = $pdfFile->file_size;
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    /**
     * Vérifier si le livre a un PDF valide
     */
    public function hasPdfFile(): bool
    {
        if ($this->book_type !== 'digital') {
            return false;
        }

        $pdfFile = $this->getPdfFile();
        return $pdfFile && Storage::exists($pdfFile->file_path);
    }

    /**
     * Obtenir les statistiques du livre numérique
     */
    public function getDigitalStats(): array
    {
        if ($this->book_type !== 'digital') {
            return [];
        }

        return [
            'downloads' => $this->download_count,
            'views' => $this->view_count,
            'likes' => $this->like_count,
            'file_size' => $this->formatted_file_size,
            'pages' => $this->pages,
            'download_limit' => $this->download_limit,
        ];
    }

    /**
     * Scope pour les livres avec des fichiers PDF valides
     */
    public function scopeWithValidPdf(Builder $query)
    {
        return $query->digital()
                    ->whereHas('digitalFiles', function($q) {
                        $q->where('file_type', 'pdf')->where('is_active', true);
                    });
    }
    /**
 * Relation avec les achats de ce livre
 */
public function purchases()
{
    return $this->hasMany(BookPurchase::class);
}

/**
 * Relation avec les utilisateurs qui ont acheté ce livre
 */
public function purchasers()
{
    return $this->belongsToMany(User::class, 'book_purchases')
                ->withPivot(['amount_paid', 'status', 'purchased_at'])
                ->withTimestamps();
}

/**
 * Relation avec les progressions de lecture
 */
public function readingProgress()
{
    return $this->hasMany(ReadingProgress::class);
}

/**
 * Relation avec les notes de lecture
 */
public function readingNotes()
{
    return $this->hasMany(ReadingNote::class);
}

/**
 * Relation avec les téléchargements
 */
public function downloads()
{
    return $this->hasMany(BookDownload::class);
}

/**
 * Relation avec les interactions IA
 */
public function aiInteractions()
{
    return $this->hasMany(AiInteraction::class);
}

/**
 * Vérifier si l'utilisateur a acheté ce livre
 */
public function isPurchasedBy(User $user): bool
{
    return $this->purchases()
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->exists();
}

/**
 * Obtenir la progression de lecture d'un utilisateur
 */
public function getReadingProgressFor(User $user)
{
    return $this->readingProgress()
                ->where('user_id', $user->id)
                ->first();
}

/**
 * Obtenir les notes de lecture d'un utilisateur
 */
public function getNotesFor(User $user)
{
    return $this->readingNotes()
                ->where('user_id', $user->id)
                ->orderBy('page_number')
                ->get();
}
}