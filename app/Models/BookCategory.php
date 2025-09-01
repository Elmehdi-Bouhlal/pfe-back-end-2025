<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class BookCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ========== RELATIONS ==========

    public function parent()
    {
        return $this->belongsTo(BookCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(BookCategory::class, 'parent_id')
                    ->orderBy('sort_order');
    }

    public function books()
    {
        return $this->belongsToMany(Book::class, 'book_category_pivot', 'category_id', 'book_id')
                    ->withTimestamps();
    }

    public function publishedBooks()
    {
        return $this->books()->published();
    }

    // ========== SCOPES ==========

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot(Builder $query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query)
    {
        return $query->orderBy('sort_order');
    }

    // ========== MÉTHODES ==========

    public function getFullNameAttribute(): string
    {
        if ($this->parent) {
            return $this->parent->name . ' > ' . $this->name;
        }
        return $this->name;
    }

    public function hasChildren(): bool
    {
        return $this->children()->count() > 0;
    }

    public function getTotalBooksCountAttribute(): int
    {
        $count = $this->publishedBooks()->count();
        
        foreach ($this->children as $child) {
            $count += $child->getTotalBooksCountAttribute();
        }
        
        return $count;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}