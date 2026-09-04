<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogApiToken;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Services\BlogService;
use App\Services\IndexNowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Publishing API for the AI writer.
 *
 * Authenticate with the key created under Admin -> Blog -> Publishing Keys:
 *
 *   Authorization: Bearer bmb_xxxxxxxx
 *
 * Posts are upserted on `external_id` (or `slug`), so re-sending the same
 * article updates it instead of creating duplicates.
 */
class BlogPublishController extends Controller
{
    public function __construct(
        protected BlogService $blog,
        protected IndexNowService $indexNow,
    ) {
    }

    /* -----------------------------------------------------------------
     | Endpoints
     |-----------------------------------------------------------------*/

    public function index(Request $request): JsonResponse
    {
        if ($error = $this->authorizeToken($request)) {
            return $error;
        }

        $posts = BlogPost::with(['category', 'tags'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->limit(min((int) $request->input('limit', 25), 100))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $posts->map(fn ($p) => $this->transform($p)),
        ]);
    }

    public function show(Request $request, string $post): JsonResponse
    {
        if ($error = $this->authorizeToken($request)) {
            return $error;
        }

        $model = $this->find($post);

        return $model
            ? response()->json(['success' => true, 'data' => $this->transform($model)])
            : $this->fail('Post not found.', 404);
    }

    /** Create a post, or update it when the same external_id/slug comes back. */
    public function store(Request $request): JsonResponse
    {
        if ($error = $this->authorizeToken($request)) {
            return $error;
        }

        $validator = validator($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required_without:content_html|string',
            'content_html' => 'nullable|string',
            'slug' => 'nullable|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'status' => 'nullable|in:draft,scheduled,published,archived',
            'published_at' => 'nullable|date',
            'category' => 'nullable',
            'tags' => 'nullable',
            'featured_image' => 'nullable|string',
            'featured_image_alt' => 'nullable|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:320',
            'meta_keywords' => 'nullable|string|max:255',
            'focus_keyword' => 'nullable|string|max:120',
            'canonical_url' => 'nullable|url|max:255',
            'author_name' => 'nullable|string|max:120',
            'author_title' => 'nullable|string|max:120',
            'external_id' => 'nullable|string|max:190',
            'faqs' => 'nullable|array',
            'faqs.*.question' => 'nullable|string|max:255',
            'faqs.*.answer' => 'nullable|string|max:2000',
            'related_job_id' => 'nullable|integer|exists:job_postings,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $existing = $this->findExisting($request);
        $data = $this->normalise($request, $existing);
        $post = $this->blog->save($data, $existing);

        if ($post->status === 'published') {
            $this->indexNow->submit([$post->url, route('blog.index')]);
        }

        return response()->json([
            'success' => true,
            'created' => ! $existing,
            'message' => $existing ? 'Post updated.' : 'Post published.',
            'data' => $this->transform($post),
        ], $existing ? 200 : 201);
    }

    public function update(Request $request, string $post): JsonResponse
    {
        if ($error = $this->authorizeToken($request)) {
            return $error;
        }

        $model = $this->find($post);

        if (! $model) {
            return $this->fail('Post not found.', 404);
        }

        $data = $this->normalise($request, $model);
        $model = $this->blog->save($data, $model);

        if ($model->status === 'published') {
            $this->indexNow->submit([$model->url]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Post updated.',
            'data' => $this->transform($model),
        ]);
    }

    public function destroy(Request $request, string $post): JsonResponse
    {
        if ($error = $this->authorizeToken($request)) {
            return $error;
        }

        $model = $this->find($post);

        if (! $model) {
            return $this->fail('Post not found.', 404);
        }

        $model->delete();

        return response()->json(['success' => true, 'message' => 'Post deleted.']);
    }

    /** Upload an image: multipart `file`, or JSON `{"url": "https://..."}`. */
    public function uploadMedia(Request $request): JsonResponse
    {
        if ($error = $this->authorizeToken($request)) {
            return $error;
        }

        if ($request->hasFile('file')) {
            $request->validate(['file' => 'image|max:8192|mimes:jpg,jpeg,png,webp,gif,avif']);
            $media = $this->blog->storeUpload($request->file('file'), null, 'api');

            return response()->json([
                'success' => true,
                'url' => $media->url,
                'path' => $media->path,
            ], 201);
        }

        if ($request->filled('url')) {
            $path = $this->blog->storeFromUrl($request->input('url'), null, 'api');

            return $path
                ? response()->json(['success' => true, 'url' => asset('storage/' . $path), 'path' => $path], 201)
                : $this->fail('Could not download that image.', 422);
        }

        return $this->fail('Send either a `file` upload or an image `url`.', 422);
    }

    public function categories(Request $request): JsonResponse
    {
        if ($error = $this->authorizeToken($request)) {
            return $error;
        }

        return response()->json([
            'success' => true,
            'data' => BlogCategory::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'url' => $c->url,
                ]),
        ]);
    }

    /* -----------------------------------------------------------------
     | Internals
     |-----------------------------------------------------------------*/

    protected function authorizeToken(Request $request): ?JsonResponse
    {
        $raw = $request->bearerToken()
            ?: $request->header('X-Blog-Token')
            ?: $request->input('publish_key')
            ?: $request->input('token');

        if (! $raw) {
            return $this->fail('Missing publishing key. Send it as: Authorization: Bearer <key>', 401);
        }

        $token = BlogApiToken::where('token', $raw)->where('is_active', true)->first();

        if (! $token) {
            return $this->fail('Invalid or disabled publishing key.', 401);
        }

        $token->markUsed($request->ip());

        return null;
    }

    protected function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    protected function find(string $identifier): ?BlogPost
    {
        return BlogPost::where('id', is_numeric($identifier) ? $identifier : 0)
            ->orWhere('slug', $identifier)
            ->orWhere('external_id', $identifier)
            ->first();
    }

    protected function findExisting(Request $request): ?BlogPost
    {
        if ($request->filled('external_id')) {
            $found = BlogPost::where('external_id', $request->input('external_id'))->first();
            if ($found) {
                return $found;
            }
        }

        if ($request->filled('slug')) {
            return BlogPost::where('slug', Str::slug($request->input('slug')))->first();
        }

        return null;
    }

    /** Turns a loose agent payload into the attribute array BlogService expects. */
    protected function normalise(Request $request, ?BlogPost $existing): array
    {
        $content = (string) ($request->input('content_html') ?: $request->input('content', $existing->content ?? ''));
        $format = $request->input('content_format');

        // Agents usually send markdown; detect it when the format is not declared.
        if (! $format) {
            $looksLikeHtml = (bool) preg_match('/<(p|div|h[1-6]|ul|ol|img|section|article)\b/i', $content);
            $format = $looksLikeHtml ? 'html' : 'markdown';
        }

        // Markdown is converted once, on the way in, so the stored body is HTML
        // that both the site and the editor can work with.
        if ($format === 'markdown') {
            $content = Str::markdown($content);
            $format = 'html';
        }

        $content = $this->blog->localiseInlineImages($content);

        $data = [
            'title' => $request->input('title', $existing->title ?? ''),
            'content' => $content,
            'content_format' => 'html',
            'source' => 'ai',
            'status' => $request->input('status', $existing->status ?? 'published'),
        ];

        foreach ([
            'slug', 'subtitle', 'excerpt', 'featured_image_alt', 'featured_image_caption',
            'meta_title', 'meta_description', 'meta_keywords', 'focus_keyword',
            'canonical_url', 'schema_type', 'author_name', 'author_title',
            'external_id', 'related_job_id',
        ] as $field) {
            if ($request->filled($field)) {
                $data[$field] = $request->input($field);
            }
        }

        if ($request->has('published_at')) {
            $data['published_at'] = $request->input('published_at');
        }

        if ($request->has('is_featured')) {
            $data['is_featured'] = $request->boolean('is_featured');
        }

        if ($request->has('noindex')) {
            $data['noindex'] = $request->boolean('noindex');
        }

        // Category may arrive as an id, a slug or a plain name.
        $category = $request->input('category', $request->input('category_name'));
        if (filled($category)) {
            $data['blog_category_id'] = optional($this->blog->resolveCategory($category))->id;
        }

        if ($request->has('tags')) {
            $data['tags'] = $request->input('tags');
        }

        if ($request->has('faqs')) {
            $data['faqs'] = collect($request->input('faqs', []))
                ->filter(fn ($f) => filled(Arr::get($f, 'question')) && filled(Arr::get($f, 'answer')))
                ->map(fn ($f) => ['question' => $f['question'], 'answer' => $f['answer']])
                ->values()
                ->all();
        }

        // Remote hero image is copied onto our own storage.
        if ($request->filled('featured_image')) {
            $image = $request->input('featured_image');
            $data['featured_image'] = Str::startsWith($image, ['http://', 'https://'])
                ? ($this->blog->storeFromUrl($image, null, 'ai') ?: $image)
                : $image;
        }

        if ($request->filled('og_image')) {
            $data['og_image'] = $this->blog->storeFromUrl($request->input('og_image'), null, 'ai')
                ?: $request->input('og_image');
        }

        $data['external_payload'] = Arr::except($request->all(), ['content', 'content_html', 'token', 'publish_key']);

        return $data;
    }

    protected function transform(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'url' => $post->url,
            'status' => $post->status,
            'external_id' => $post->external_id,
            'category' => $post->category?->name,
            'tags' => $post->relationLoaded('tags') ? $post->tags->pluck('name') : [],
            'featured_image' => $post->featured_image_url,
            'excerpt' => $post->excerpt,
            'seo_score' => $post->seo_score,
            'reading_time' => $post->reading_time,
            'word_count' => $post->word_count,
            'views' => $post->view_count,
            'published_at' => optional($post->published_at)->toIso8601String(),
            'updated_at' => optional($post->updated_at)->toIso8601String(),
        ];
    }
}
