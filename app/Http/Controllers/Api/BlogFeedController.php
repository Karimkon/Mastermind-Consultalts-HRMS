<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\BlogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public read-only blog feed for the mobile apps.
 *
 * No authentication: these are the same articles anyone can read on the web.
 * Article bodies are returned both as raw HTML and as a pre-parsed block list,
 * so the app can lay them out with native widgets instead of a web view.
 */
class BlogFeedController extends Controller
{
    public function __construct(protected BlogService $blog)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->blog->releaseScheduledThrottled();

        $perPage = min((int) $request->input('per_page', 10), 30);

        $posts = BlogPost::published()
            ->with(['category', 'tags'])
            ->when($request->filled('category'), function ($q) use ($request) {
                $slug = $request->input('category');
                $q->whereHas('category', fn ($c) => $c->where('slug', $slug));
            })
            ->when($request->filled('tag'), function ($q) use ($request) {
                $tag = $request->input('tag');
                $q->whereHas('tags', fn ($t) => $t->where('slug', $tag));
            })
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->search($request->input('q'))
            ->orderByDesc('published_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($posts->items())->map(fn ($p) => $this->summary($p))->all(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'has_more' => $posts->hasMorePages(),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $post = BlogPost::published()
            ->with(['category', 'tags'])
            ->where('slug', $slug)
            ->first();

        if (! $post) {
            return response()->json(['success' => false, 'message' => 'Article not found.'], 404);
        }

        BlogPost::whereKey($post->id)->update(['view_count' => $post->view_count + 1]);

        $data = $this->summary($post) + [
            'content_html' => $post->rendered_content,
            'blocks' => $this->blog->toBlocks($post->rendered_content),
            'faqs' => collect($post->faqs ?: [])
                ->filter(fn ($f) => ! empty($f['question']) && ! empty($f['answer']))
                ->map(fn ($f) => ['question' => $f['question'], 'answer' => $f['answer']])
                ->values()
                ->all(),
            'author_title' => $post->author_title,
            'author_avatar' => $post->author_avatar_url,
            'related_job_id' => $post->related_job_id,
            'related' => $post->relatedPosts(3)->map(fn ($p) => $this->summary($p))->all(),
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function categories(): JsonResponse
    {
        $categories = BlogCategory::where('is_active', true)
            ->withCount(['posts as posts_count' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'description' => $c->description,
                'color' => $c->color,
                'image' => $c->image_url,
                'posts_count' => $c->posts_count,
            ]);

        $tags = BlogTag::withCount(['posts as posts_count' => fn ($q) => $q->published()])
            ->having('posts_count', '>', 0)
            ->orderByDesc('posts_count')
            ->limit(20)
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'tags' => $tags,
            ],
        ]);
    }

    protected function summary(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'subtitle' => $post->subtitle,
            'excerpt' => $post->excerpt,
            'image' => $post->featured_image_url,
            'image_alt' => $post->featured_image_alt,
            'category' => $post->category ? [
                'name' => $post->category->name,
                'slug' => $post->category->slug,
                'color' => $post->category->color,
            ] : null,
            'tags' => $post->relationLoaded('tags') ? $post->tags->pluck('name')->all() : [],
            'author' => $post->display_author,
            'reading_time' => $post->reading_time,
            'views' => $post->view_count,
            'is_featured' => (bool) $post->is_featured,
            'published_at' => optional($post->published_at)->toIso8601String(),
            'url' => $post->url,
        ];
    }
}
