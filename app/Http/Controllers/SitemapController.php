<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\JobPosting;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    /** Sitemap index — the one URL to hand to Google Search Console. */
    public function index()
    {
        $sitemaps = [
            ['loc' => route('sitemap.pages'), 'lastmod' => now()->toDateString()],
            ['loc' => route('sitemap.jobs'), 'lastmod' => $this->lastMod(JobPosting::max('updated_at'))],
            ['loc' => route('sitemap.blog'), 'lastmod' => $this->lastMod(BlogPost::max('updated_at'))],
        ];

        return response(view('sitemaps.index', compact('sitemaps'))->render(), 200)
            ->header('Content-Type', 'application/xml');
    }

    public function pages()
    {
        $pages = [
            ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => route('careers.index'), 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => route('blog.index'), 'priority' => '0.9', 'changefreq' => 'daily'],
        ];

        return response(view('sitemaps.urls', ['urls' => $pages])->render(), 200)
            ->header('Content-Type', 'application/xml');
    }

    /** Open roles — these are what people search for by name. */
    public function jobs()
    {
        $jobs = JobPosting::where('status', 'open')
            ->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>=', now()->toDateString()))
            ->get()
            ->map(fn ($job) => [
                'loc' => route('careers.show', $job->slug ?: $job->id),
                'lastmod' => $this->lastMod($job->updated_at),
                'priority' => '0.8',
                'changefreq' => 'daily',
            ]);

        return response(view('sitemaps.urls', ['urls' => $jobs])->render(), 200)
            ->header('Content-Type', 'application/xml');
    }

    /** Articles, topic archives and tag pages. */
    public function blog()
    {
        $posts = BlogPost::indexable()
            ->select('slug', 'updated_at', 'published_at', 'featured_image', 'featured_image_alt', 'title')
            ->orderByDesc('published_at')
            ->limit(50000)
            ->get()
            ->map(function ($post) {
                $entry = [
                    'loc' => route('blog.show', $post->slug),
                    'lastmod' => $this->lastMod($post->updated_at ?? $post->published_at),
                    'priority' => '0.8',
                    'changefreq' => 'weekly',
                ];

                if ($post->featured_image) {
                    $entry['image'] = [
                        'loc' => $post->featured_image_url,
                        'title' => $post->title,
                        'caption' => $post->featured_image_alt ?: $post->title,
                    ];
                }

                return $entry;
            });

        $categories = BlogCategory::where('is_active', true)
            ->get()
            ->map(fn ($category) => [
                'loc' => route('blog.category', $category->slug),
                'lastmod' => $this->lastMod($category->updated_at),
                'priority' => '0.6',
                'changefreq' => 'weekly',
            ]);

        $tags = BlogTag::whereHas('posts', fn ($q) => $q->published())
            ->get()
            ->map(fn ($tag) => [
                'loc' => route('blog.tag', $tag->slug),
                'lastmod' => $this->lastMod($tag->updated_at),
                'priority' => '0.4',
                'changefreq' => 'weekly',
            ]);

        $urls = $posts->concat($categories)->concat($tags);

        return response(view('sitemaps.urls', ['urls' => $urls])->render(), 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Normalise a lastmod value. Aggregates such as max('updated_at') come back
     * as raw strings, so they cannot be treated as Carbon instances.
     */
    protected function lastMod($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->toDateString();
            } catch (\Throwable $e) {
                return now()->toDateString();
            }
        }

        return now()->toDateString();
    }
}
