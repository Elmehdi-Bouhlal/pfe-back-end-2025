<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'user_id',
    ];

    // ========== RELATIONS ==========

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ========== ÉVÉNEMENTS ==========

    protected static function boot()
    {
        parent::boot();

        static::created(function ($like) {
            $like->book->incrementLikeCount();
        });

        static::deleted(function ($like) {
            $like->book->decrementLikeCount();
        });
    }
}
