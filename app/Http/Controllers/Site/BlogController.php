<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\JobPosting;
use App\Services\BlogService;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function __construct(protected BlogService $blog)
    {
    }

    /** Blog home: featured lead article, latest posts, topic rail. */
    public function index(Request $request)
    {
        // Belt and braces: scheduled posts also go live on the first blog visit
        // after their time, so a stalled cron never holds an article back.
        $this->blog->releaseScheduledThrottled();

        $search = trim((string) $request->input('q'));

        $posts = BlogPost::published()
            ->with(['category', 'tags'])
            ->search($search ?: null)
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();

        $featured = null;
        if (! $search && $posts->currentPage() === 1) {
            $featured = BlogPost::published()
                ->with('category')
                ->orderByDesc('is_featured')
                ->orderByDesc('published_at')
                ->first();
        }

        return view('blog.index', [
            'posts' => $posts,
            'featured' => $featured,
            'categories' => $this->sidebarCategories(),
            'popular' => $this->popularPosts(),
            'tags' => $this->popularTags(),
            'jobs' => $this->openRoles(3),
            'search' => $search,
            'heading' => 'Insights from Mastermind',
            'subheading' => 'Practical guidance on hiring, payroll, labour law and building teams that stay, from the HR partner to organisations across Uganda.',
            'canonicalUrl' => route('blog.index'),
            'crumbs' => [
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'Insights', 'url' => route('blog.index')],
            ],
        ]);
    }

    /** Topic archive. */
    public function category(BlogCategory $category)
    {
        abort_unless($category->is_active, 404);

        $posts = $category->posts()
            ->published()
            ->with(['category', 'tags'])
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('blog.index', [
            'posts' => $posts,
            'featured' => null,
            'categories' => $this->sidebarCategories(),
            'popular' => $this->popularPosts(),
            'tags' => $this->popularTags(),
            'jobs' => $this->openRoles(3),
            'search' => null,
            'activeCategory' => $category,
            'heading' => $category->name,
            'subheading' => $category->description ?: 'Articles filed under ' . $category->name . '.',
            'metaTitle' => $category->meta_title ?: $category->name . ' Articles',
            'metaDescription' => $category->meta_description ?: 'Read the latest ' . $category->name . ' articles and guidance from Mastermind Consult.',
            'canonicalUrl' => route('blog.category', $category->slug),
            'crumbs' => [
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'Insights', 'url' => route('blog.index')],
                ['name' => $category->name, 'url' => route('blog.category', $category->slug)],
            ],
        ]);
    }

    /** Tag archive. */
    public function tag(BlogTag $tag)
    {
        $posts = $tag->posts()
            ->published()
            ->with(['category', 'tags'])
            ->orderByDesc('published_at')
            ->paginate(9);

        return view('blog.index', [
            'posts' => $posts,
            'featured' => null,
            'categories' => $this->sidebarCategories(),
            'popular' => $this->popularPosts(),
            'tags' => $this->popularTags(),
            'jobs' => $this->openRoles(3),
            'search' => null,
            'activeTag' => $tag,
            'heading' => '#' . $tag->name,
            'subheading' => 'Every article tagged ' . $tag->name . '.',
            'metaTitle' => $tag->name . ' Articles',
            'metaDescription' => 'Browse Mastermind Consult articles tagged ' . $tag->name . '.',
            'canonicalUrl' => route('blog.tag', $tag->slug),
            'crumbs' => [
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'Insights', 'url' => route('blog.index')],
                ['name' => $tag->name, 'url' => route('blog.tag', $tag->slug)],
            ],
        ]);
    }

    /** Single article. */
    public function show(Request $request, string $slug)
    {
        $this->blog->releaseScheduledThrottled();

        $post = BlogPost::with(['category', 'tags', 'author', 'relatedJob'])
            ->where('slug', $slug)
            ->firstOrFail();

        // Drafts and scheduled posts stay private, but staff can preview them.
        if ($post->status !== 'published' || ! $post->published_at || $post->published_at->isFuture()) {
            abort_unless($this->canPreview($request), 404);
        }

        BlogPost::whereKey($post->id)->update(['view_count' => $post->view_count + 1]);

        return view('blog.show', [
            'post' => $post,
            'related' => $post->relatedPosts(3),
            'jobs' => $this->openRoles(3),
            'categories' => $this->sidebarCategories(),
            'popular' => $this->popularPosts(),
            'crumbs' => array_values(array_filter([
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'Insights', 'url' => route('blog.index')],
                $post->category ? ['name' => $post->category->name, 'url' => route('blog.category', $post->category->slug)] : null,
                ['name' => $post->title, 'url' => $post->url],
            ])),
        ]);
    }

    /** RSS 2.0 feed. */
    public function feed()
    {
        $posts = BlogPost::published()
            ->with('category')
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        return response()
            ->view('blog.feed', ['posts' => $posts])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    protected function canPreview(Request $request): bool
    {
        $user = $request->user();

        return $user && $user->hasAnyRole(['super-admin', 'hr-admin', 'manager', 'md']);
    }

    protected function sidebarCategories()
    {
        return BlogCategory::where('is_active', true)
            ->withCount(['posts as posts_count' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    protected function popularPosts()
    {
        return BlogPost::published()
            ->orderByDesc('view_count')
            ->limit(5)
            ->get();
    }

    protected function popularTags()
    {
        return BlogTag::withCount(['posts as posts_count' => fn ($q) => $q->published()])
            ->having('posts_count', '>', 0)
            ->orderByDesc('posts_count')
            ->limit(15)
            ->get();
    }

    /** Open roles, shown beside articles — the reason the blog earns its keep. */
    protected function openRoles(int $limit = 3)
    {
        return JobPosting::with('department')
            ->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>=', now()))
            ->latest()
            ->limit($limit)
            ->get();
    }
}
