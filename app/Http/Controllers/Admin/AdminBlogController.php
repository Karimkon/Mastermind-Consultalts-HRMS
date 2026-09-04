<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogApiToken;
use App\Models\BlogCategory;
use App\Models\BlogMedia;
use App\Models\BlogPost;
use App\Models\JobPosting;
use App\Services\BlogService;
use App\Services\IndexNowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminBlogController extends Controller
{
    public function __construct(
        protected BlogService $blog,
        protected IndexNowService $indexNow,
    ) {
    }

    /* -----------------------------------------------------------------
     | Posts
     |-----------------------------------------------------------------*/

    public function index(Request $request)
    {
        $posts = BlogPost::with(['category', 'author'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('blog_category_id', $request->input('category')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->input('source')))
            ->search($request->input('q'))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => BlogPost::count(),
            'published' => BlogPost::where('status', 'published')->count(),
            'draft' => BlogPost::where('status', 'draft')->count(),
            'scheduled' => BlogPost::where('status', 'scheduled')->count(),
            'views' => (int) BlogPost::sum('view_count'),
            'ai' => BlogPost::whereIn('source', ['api', 'ai'])->count(),
        ];

        return view('admin.blog.index', [
            'posts' => $posts,
            'stats' => $stats,
            'categories' => BlogCategory::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.blog.create', $this->formData(new BlogPost([
            'status' => 'draft',
            'content_format' => 'html',
            'schema_type' => 'BlogPosting',
        ])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        $data['source'] = 'manual';

        $post = $this->blog->save($this->withUploads($request, $data), null);

        if ($post->status === 'published') {
            $this->indexNow->submit([$post->url, route('blog.index')]);
        }

        return redirect()
            ->route('admin.blog.edit', $post->id)
            ->with('success', 'Post created successfully.');
    }

    public function edit(BlogPost $post)
    {
        $post->load('tags');

        return view('admin.blog.edit', $this->formData($post));
    }

    public function update(Request $request, BlogPost $post)
    {
        $wasPublished = $post->status === 'published';
        $data = $this->validated($request, $post);

        $post = $this->blog->save($this->withUploads($request, $data, $post), $post);

        if ($post->status === 'published') {
            $this->indexNow->submit(array_filter([
                $post->url,
                $wasPublished ? null : route('blog.index'),
            ]));
        }

        return back()->with('success', 'Post updated successfully.');
    }

    public function destroy(BlogPost $post)
    {
        $post->delete();

        return redirect()->route('admin.blog.index')->with('success', 'Post moved to trash.');
    }

    /** Quick publish / unpublish from the list screen. */
    public function toggleStatus(BlogPost $post)
    {
        if ($post->status === 'published') {
            $post->status = 'draft';
        } else {
            $post->status = 'published';
            $post->published_at ??= now();
        }

        $post->save();

        if ($post->status === 'published') {
            $this->indexNow->submit([$post->url]);
        }

        return back()->with('success', 'Post is now ' . $post->status . '.');
    }

    public function toggleFeatured(BlogPost $post)
    {
        $post->update(['is_featured' => ! $post->is_featured]);

        return back()->with('success', $post->is_featured ? 'Post featured.' : 'Post unfeatured.');
    }

    public function duplicate(BlogPost $post)
    {
        $copy = $post->replicate(['view_count', 'share_count', 'external_id', 'published_at']);
        $copy->title = $post->title . ' (copy)';
        $copy->slug = BlogPost::uniqueSlug($post->title . ' copy');
        $copy->status = 'draft';
        $copy->external_id = null;
        $copy->published_at = null;
        $copy->view_count = 0;
        $copy->save();
        $copy->tags()->sync($post->tags->pluck('id'));

        return redirect()->route('admin.blog.edit', $copy->id)->with('success', 'Post duplicated.');
    }

    /* -----------------------------------------------------------------
     | Media library
     |-----------------------------------------------------------------*/

    public function media(Request $request)
    {
        return view('admin.blog.media', [
            'media' => BlogMedia::orderByDesc('id')->paginate(30),
        ]);
    }

    /** Upload used by both the media library and the rich text editor. */
    public function uploadMedia(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:8192|mimes:jpg,jpeg,png,webp,gif,avif',
            'alt' => 'nullable|string|max:255',
        ]);

        $media = $this->blog->storeUpload($request->file('file'), $request->user()->id, 'manual');

        if ($request->filled('alt')) {
            $media->update(['alt' => $request->input('alt')]);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'id' => $media->id,
                'location' => $media->url,   // TinyMCE expects "location"
                'url' => $media->url,
                'path' => $media->path,
            ]);
        }

        return back()->with('success', 'Image uploaded.');
    }

    public function destroyMedia(BlogMedia $media)
    {
        Storage::disk($media->disk ?: 'public')->delete($media->path);
        $media->delete();

        return back()->with('success', 'Image deleted.');
    }

    /* -----------------------------------------------------------------
     | Publishing tokens (used by the AI writer)
     |-----------------------------------------------------------------*/

    public function tokens()
    {
        return view('admin.blog.tokens', [
            'tokens' => BlogApiToken::orderByDesc('id')->get(),
            'endpoint' => url('/api/blog/posts'),
        ]);
    }

    public function storeToken(Request $request)
    {
        $request->validate(['name' => 'required|string|max:120']);

        BlogApiToken::generate($request->input('name'), $request->user()->id);

        return back()->with('success', 'Publishing key created. Copy it into your AI agent.');
    }

    public function toggleToken(BlogApiToken $token)
    {
        $token->update(['is_active' => ! $token->is_active]);

        return back()->with('success', 'Key ' . ($token->is_active ? 'enabled' : 'disabled') . '.');
    }

    public function destroyToken(BlogApiToken $token)
    {
        $token->delete();

        return back()->with('success', 'Key deleted.');
    }

    /* -----------------------------------------------------------------
     | Helpers
     |-----------------------------------------------------------------*/

    protected function formData(BlogPost $post): array
    {
        return [
            'post' => $post,
            'categories' => BlogCategory::orderBy('sort_order')->orderBy('name')->get(),
            'jobs' => JobPosting::where('status', 'open')->orderBy('title')->get(['id', 'title']),
            'tagList' => $post->exists ? $post->tags->pluck('name')->implode(', ') : '',
            'media' => BlogMedia::orderByDesc('id')->limit(24)->get(),
        ];
    }

    protected function validated(Request $request, ?BlogPost $post = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('blog_posts', 'slug')->ignore($post?->id)],
            'subtitle' => 'nullable|string|max:255',
            'blog_category_id' => 'nullable|exists:blog_categories,id',
            'related_job_id' => 'nullable|exists:job_postings,id',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'content_format' => 'nullable|in:html,markdown',
            'featured_image_alt' => 'nullable|string|max:255',
            'featured_image_caption' => 'nullable|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:320',
            'meta_keywords' => 'nullable|string|max:255',
            'focus_keyword' => 'nullable|string|max:120',
            'canonical_url' => 'nullable|url|max:255',
            'schema_type' => 'nullable|string|max:40',
            'author_name' => 'nullable|string|max:120',
            'author_title' => 'nullable|string|max:120',
            'status' => 'required|in:draft,scheduled,published,archived',
            'published_at' => 'nullable|date',
            'tags' => 'nullable|string|max:500',
            'faq_question' => 'nullable|array',
            'faq_question.*' => 'nullable|string|max:255',
            'faq_answer' => 'nullable|array',
            'faq_answer.*' => 'nullable|string|max:2000',
        ]);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['noindex'] = $request->boolean('noindex');
        $data['content_format'] = $data['content_format'] ?? 'html';

        $faqs = [];
        foreach ((array) $request->input('faq_question', []) as $i => $question) {
            $answer = $request->input('faq_answer.' . $i);
            if (filled($question) && filled($answer)) {
                $faqs[] = ['question' => $question, 'answer' => $answer];
            }
        }
        $data['faqs'] = $faqs;

        unset($data['faq_question'], $data['faq_answer']);

        return $data;
    }

    /** Move uploaded images (or picked library paths) into the payload. */
    protected function withUploads(Request $request, array $data, ?BlogPost $post = null): array
    {
        $userId = $request->user()->id;

        if ($request->hasFile('featured_image')) {
            $request->validate(['featured_image' => 'image|max:8192|mimes:jpg,jpeg,png,webp,gif,avif']);
            $data['featured_image'] = $this->blog->storeUpload($request->file('featured_image'), $userId)->path;
        } elseif ($request->filled('featured_image_path')) {
            $data['featured_image'] = $request->input('featured_image_path');
        } elseif ($request->input('remove_featured_image') === '1') {
            $data['featured_image'] = null;
            if ($post) {
                $post->featured_image = null;
            }
        }

        if ($request->hasFile('og_image')) {
            $request->validate(['og_image' => 'image|max:8192|mimes:jpg,jpeg,png,webp']);
            $data['og_image'] = $this->blog->storeUpload($request->file('og_image'), $userId)->path;
        }

        if ($request->hasFile('author_avatar')) {
            $request->validate(['author_avatar' => 'image|max:2048|mimes:jpg,jpeg,png,webp']);
            $data['author_avatar'] = $this->blog->storeUpload($request->file('author_avatar'), $userId)->path;
        }

        return $data;
    }
}
