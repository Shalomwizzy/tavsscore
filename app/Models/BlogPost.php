<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'content_pidgin',
        'content_swahili',
        'content_french',
        'featured_image',
        'image_path',
        'category',
        'editorial_desk',
        'editorial_slot',
        'author',
        'is_published',
        'is_ai_generated',
        'published_at',
    ];

    protected $casts = [
        'is_published'    => 'boolean',
        'is_ai_generated' => 'boolean',
        'published_at'    => 'datetime',
    ];

    public static function generateSlug(string $title): string
    {
        $slug = Str::slug($title);
        $count = static::where('slug', 'like', $slug.'%')->count();

        return $count > 0 ? $slug.'-'.($count + 1) : $slug;
    }

    /** Keep every stored article consistent with TavsScore's no long-dash style. */
    public static function normaliseTypography(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace(['—', '–'], [', ', ' to '], $value);
        $value = preg_replace('/\s+([,.;:!?])/u', '$1', $value) ?? $value;

        return preg_replace('/[ \t]{2,}/u', ' ', $value) ?? $value;
    }

    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['title'] = self::normaliseTypography($value);
    }

    public function setExcerptAttribute(?string $value): void
    {
        $this->attributes['excerpt'] = self::normaliseTypography($value);
    }

    public function setContentAttribute(?string $value): void
    {
        $this->attributes['content'] = self::normaliseTypography($value);
    }

    public function setContentPidginAttribute(?string $value): void
    {
        $this->attributes['content_pidgin'] = self::normaliseTypography($value);
    }

    public function setContentSwahiliAttribute(?string $value): void
    {
        $this->attributes['content_swahili'] = self::normaliseTypography($value);
    }

    public function setContentFrenchAttribute(?string $value): void
    {
        $this->attributes['content_french'] = self::normaliseTypography($value);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function ($q): void {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image_path) {
            return '/' . ltrim($this->image_path, '/');
        }
        return $this->featured_image ?: null;
    }

    public function getAbsoluteImageUrlAttribute(): ?string
    {
        $imageUrl = $this->image_url;

        if (blank($imageUrl)) {
            return null;
        }

        return Str::startsWith($imageUrl, ['http://', 'https://'])
            ? $imageUrl
            : url($imageUrl);
    }

    public function getPublicPathAttribute(): string
    {
        $date = $this->published_at ?? $this->created_at;

        return '/football-news/' . $date->format('Y/m/d') . '/' . $this->slug;
    }

    public function getPublicUrlAttribute(): string
    {
        return url($this->public_path);
    }

    public function getReadingTimeAttribute(): int
    {
        $wordCount = str_word_count(strip_tags($this->content));

        return max(1, (int) ceil($wordCount / 200));
    }

    public function getExcerptOrGeneratedAttribute(): string
    {
        if (! blank($this->excerpt)) {
            return $this->excerpt;
        }

        return Str::limit(strip_tags($this->content), 160);
    }
}
