<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'blog_category_id', 'user_id', 'related_job_id',
        'title', 'slug', 'subtitle', 'excerpt', 'content', 'content_format',
        'featured_image', 'featured_image_alt', 'featured_image_caption', 'og_image',
        'meta_title', 'meta_description', 'meta_keywords', 'focus_keyword',
        'canonical_url', 'schema_type', 'faqs', 'noindex', 'seo_score',
        'author_name', 'author_title', 'author_avatar',
        'status', 'is_featured', 'published_at',
        'reading_time', 'word_count', 'view_count', 'share_count',
        'source', 'external_id', 'external_payload',
    ];

    protected $casts = [
        'faqs' => 'array',
        'external_payload' => 'array',
        'noindex' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public const STATUSES = ['draft', 'scheduled', 'published', 'archived'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /* -----------------------------------------------------------------
     | Relationships
     |-----------------------------------------------------------------*/

    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function tags()
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Open role this article should funnel readers into. */
    public function relatedJob()
    {
        return $this->belongsTo(JobPosting::class, 'related_job_id');
    }

    /* -----------------------------------------------------------------
     | Scopes
     |-----------------------------------------------------------------*/

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')
                 ->whereNotNull('published_at')
                 ->where('published_at', '<=', now());
    }

    public function scopeIndexable(Builder $q): Builder
    {
        return $q->published()->where('noindex', false);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! $term) {
            return $q;
        }

        return $q->where(function ($sub) use ($term) {
            $sub->where('title', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%")
                ->orWhere('meta_keywords', 'like', "%{$term}%")
                ->orWhere('content', 'like', "%{$term}%");
        });
    }

    /* -----------------------------------------------------------------
     | Accessors
     |-----------------------------------------------------------------*/

    public function getUrlAttribute(): string
    {
        return route('blog.show', $this->slug);
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        return $this->imageUrl($this->featured_image);
    }

    public function getOgImageUrlAttribute(): ?string
    {
        return $this->imageUrl($this->og_image) ?: $this->featured_image_url;
    }

    public function getAuthorAvatarUrlAttribute(): ?string
    {
        return $this->imageUrl($this->author_avatar);
    }

    /** Handles both stored disk paths and absolute URLs (an agent may send remote images). */
    protected function imageUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://', '//'])) {
            return $value;
        }

        return asset('storage/' . ltrim($value, '/'));
    }

    public function getDisplayAuthorAttribute(): string
    {
        return $this->author_name ?: ($this->author?->name ?: config('app.name') . ' Editorial Team');
    }

    public function getMetaTitleValueAttribute(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function getMetaDescriptionValueAttribute(): string
    {
        $raw = $this->meta_description ?: $this->excerpt ?: strip_tags((string) $this->content);

        return trim(Str::limit(preg_replace('/\s+/', ' ', $raw), 158, ''));
    }

    public function getCanonicalAttribute(): string
    {
        return $this->canonical_url ?: $this->url;
    }

    /** Rendered body. Markdown posts (common from AI writers) are converted to HTML. */
    public function getRenderedContentAttribute(): string
    {
        $content = (string) $this->content;

        if ($this->content_format === 'markdown') {
            $content = Str::markdown($content);
        }

        return $content;
    }

    /** Headings pulled out of the body to build a table of contents. */
    public function getTableOfContentsAttribute(): array
    {
        preg_match_all('/<h([23])[^>]*>(.*?)<\/h\1>/is', $this->rendered_content, $m, PREG_SET_ORDER);

        return collect($m)->map(fn ($h) => [
            'level' => (int) $h[1],
            'text'  => trim(strip_tags($h[2])),
            'id'    => Str::slug(strip_tags($h[2])),
        ])->filter(fn ($h) => $h['text'] !== '')->values()->all();
    }

    /** Body with anchor ids injected into headings so the table of contents can link to them. */
    public function getContentWithAnchorsAttribute(): string
    {
        return preg_replace_callback('/<h([23])([^>]*)>(.*?)<\/h\1>/is', function ($m) {
            $id = Str::slug(strip_tags($m[3]));
            $attrs = $m[2];
            if (! str_contains($attrs, 'id=')) {
                $attrs .= ' id="' . $id . '"';
            }

            return "<h{$m[1]}{$attrs}>{$m[3]}</h{$m[1]}>";
        }, $this->rendered_content);
    }

    /* -----------------------------------------------------------------
     | Helpers
     |-----------------------------------------------------------------*/

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: Str::random(8);
        $slug = $base;
        $i = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /** Fills derived fields: word count, reading time, excerpt and SEO score. */
    public function refreshDerivedFields(): void
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($this->rendered_content)));
        $words = $text === '' ? 0 : str_word_count($text);

        $this->word_count = $words;
        $this->reading_time = max(1, (int) ceil($words / 220));

        if (! $this->excerpt) {
            $this->excerpt = Str::limit($text, 200);
        }

        $this->seo_score = $this->calculateSeoScore($text);
    }

    protected function calculateSeoScore(string $plainText): int
    {
        $score = 0;
        $keyword = Str::lower((string) $this->focus_keyword);

        $titleLen = Str::length($this->meta_title ?: $this->title);
        if ($titleLen >= 30 && $titleLen <= 65) {
            $score += 15;
        } elseif ($titleLen > 0) {
            $score += 7;
        }

        $descLen = Str::length($this->meta_description_value);
        if ($descLen >= 120 && $descLen <= 160) {
            $score += 15;
        } elseif ($descLen > 0) {
            $score += 7;
        }

        if ($this->word_count >= 900) {
            $score += 20;
        } elseif ($this->word_count >= 500) {
            $score += 14;
        } elseif ($this->word_count >= 300) {
            $score += 8;
        }

        if ($this->featured_image) {
            $score += 10;
        }

        if ($this->featured_image_alt) {
            $score += 5;
        }

        if (preg_match('/<h2[^>]*>/i', $this->rendered_content)) {
            $score += 10;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'bebamart.com';
        if (preg_match('/href=["\'](\/[^"\']*|https?:\/\/' . preg_quote($host, '/') . '[^"\']*)["\']/i', $this->rendered_content)) {
            $score += 8;
        }

        if ($keyword !== '') {
            $score += 5;
            if (Str::contains(Str::lower($this->title), $keyword)) {
                $score += 6;
            }
            if (Str::contains(Str::lower($plainText), $keyword)) {
                $score += 6;
            }
        }

        return min(100, $score);
    }

    /** Posts shown under "related reading". */
    public function relatedPosts(int $limit = 3)
    {
        $tagIds = $this->relationLoaded('tags') ? $this->tags->pluck('id') : $this->tags()->pluck('blog_tags.id');

        return static::published()
            ->where('id', '!=', $this->id)
            ->where(function ($q) use ($tagIds) {
                $q->where('blog_category_id', $this->blog_category_id);
                if ($tagIds->isNotEmpty()) {
                    $q->orWhereHas('tags', fn ($t) => $t->whereIn('blog_tags.id', $tagIds));
                }
            })
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }
}
