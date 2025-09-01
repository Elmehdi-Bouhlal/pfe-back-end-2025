<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookView extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'user_id',
        'ip_address',
        'user_agent',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public $timestamps = false;

    // ========== RELATIONS ==========

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ========== MÉTHODES ==========

    public static function recordView(Book $book, User $user = null, string $ipAddress = null, string $userAgent = null): void
    {
        // Éviter les vues multiples du même utilisateur dans une courte période
        $recentView = self::where('book_id', $book->id)
            ->where(function($query) use ($user, $ipAddress) {
                if ($user) {
                    $query->where('user_id', $user->id);
                } else {
                    $query->where('ip_address', $ipAddress);
                }
            })
            ->where('viewed_at', '>', now()->subHour())
            ->first();

        if (!$recentView) {
            self::create([
                'book_id' => $book->id,
                'user_id' => $user?->id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'viewed_at' => now(),
            ]);

            $book->incrementViewCount();
        }
    }
}