<?php

namespace App\Services;

use App\Models\BlogCategory;
use App\Models\BlogMedia;
use App\Models\BlogPost;
use App\Models\BlogTag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Single place where a blog post is created or updated, whether the payload
 * came from the admin dashboard or from the AI writer hitting the publish API.
 */
class BlogService
{
    /** Tags Blade/browsers accept inside article bodies. Everything else is stripped. */
    protected const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s><h2><h3><h4><h5><ul><ol><li><blockquote>'
        . '<a><img><figure><figcaption><table><thead><tbody><tfoot><tr><th><td><code><pre><hr><span><div><iframe><small><sup><sub>';

    /**
     * Create or update a post from a normalised attribute array.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(array $data, ?BlogPost $post = null): BlogPost
    {
        $post ??= new BlogPost();

        $title = Arr::get($data, 'title', $post->title);

        $post->fill(Arr::only($data, [
            'blog_category_id', 'user_id', 'related_job_id',
            'title', 'subtitle', 'excerpt', 'content', 'content_format',
            'featured_image', 'featured_image_alt', 'featured_image_caption', 'og_image',
            'meta_title', 'meta_description', 'meta_keywords', 'focus_keyword',
            'canonical_url', 'schema_type', 'faqs', 'noindex',
            'author_name', 'author_title', 'author_avatar',
            'status', 'is_featured', 'source', 'external_id', 'external_payload',
        ]));

        // Slug: explicit value wins, otherwise derive from the title once.
        if (! empty($data['slug'])) {
            $post->slug = BlogPost::uniqueSlug($data['slug'], $post->id);
        } elseif (! $post->slug) {
            $post->slug = BlogPost::uniqueSlug((string) $title, $post->id);
        }

        if (isset($data['content'])) {
            $post->content = $this->sanitize((string) $data['content']);
        }

        if (array_key_exists('published_at', $data) && $data['published_at']) {
            $post->published_at = $data['published_at'];
        }

        // A post asked to go live without a date goes live now.
        if ($post->status === 'published' && ! $post->published_at) {
            $post->published_at = now();
        }

        // A future date on a live post means "schedule it".
        if ($post->status === 'published' && $post->published_at && $post->published_at->isFuture()) {
            $post->status = 'scheduled';
        }

        // No cover picked? Use the first image in the article, so a post never
        // shows up on the blog cards or on WhatsApp as a blank placeholder.
        if (! $post->featured_image) {
            $post->featured_image = $this->firstImagePath($post->content);
        }

        $post->refreshDerivedFields();
        $post->save();

        if (array_key_exists('tags', $data)) {
            $this->syncTags($post, $data['tags']);
        }

        return $post->fresh(['category', 'tags']);
    }

    /**
     * Attach tags from an array of names/slugs or a comma separated string.
     */
    public function syncTags(BlogPost $post, mixed $tags): void
    {
        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }

        $ids = collect($tags ?: [])
            ->map(fn ($name) => BlogTag::fromName((string) $name))
            ->filter()
            ->pluck('id')
            ->all();

        $post->tags()->sync($ids);
    }

    /**
     * Resolve a category from an id, a slug or a plain name, creating it when
     * the AI writer invents a new topic that does not exist yet.
     */
    public function resolveCategory(mixed $value, bool $createMissing = true): ?BlogCategory
    {
        if (blank($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return BlogCategory::find($value);
        }

        $slug = Str::slug((string) $value);
        $category = BlogCategory::where('slug', $slug)->first();

        if ($category || ! $createMissing) {
            return $category;
        }

        return BlogCategory::create([
            'name' => (string) $value,
            'slug' => BlogCategory::uniqueSlug((string) $value),
            'is_active' => true,
        ]);
    }

    /**
     * Store an uploaded image and register it in the media library.
     */
    public function storeUpload(UploadedFile $file, ?int $userId = null, string $source = 'manual', string $folder = 'blog'): BlogMedia
    {
        $path = $file->store($folder, 'public');
        $dimensions = @getimagesize($file->getRealPath() ?: '') ?: [null, null];

        return BlogMedia::create([
            'user_id' => $userId,
            'path' => $path,
            'disk' => 'public',
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'source' => $source,
        ]);
    }

    /**
     * Pull a remote image onto our own storage so articles never hotlink
     * (hotlinked images break, and Google will not use them for rich results).
     */
    public function storeFromUrl(?string $url, ?int $userId = null, string $source = 'api'): ?string
    {
        if (! $url || ! Str::startsWith($url, ['http://', 'https://'])) {
            return $url ?: null;
        }

        try {
            $response = Http::timeout(25)->withOptions(['verify' => false])->get($url);

            if (! $response->successful()) {
                return null;
            }

            $mime = strtolower((string) $response->header('Content-Type'));
            if (! Str::startsWith($mime, 'image/')) {
                return null;
            }

            $ext = match (true) {
                str_contains($mime, 'png') => 'png',
                str_contains($mime, 'webp') => 'webp',
                str_contains($mime, 'gif') => 'gif',
                str_contains($mime, 'svg') => 'svg',
                default => 'jpg',
            };

            $path = 'blog/' . date('Y/m') . '/' . Str::random(32) . '.' . $ext;
            Storage::disk('public')->put($path, $response->body());

            BlogMedia::create([
                'user_id' => $userId,
                'path' => $path,
                'disk' => 'public',
                'original_name' => basename(parse_url($url, PHP_URL_PATH) ?: $path),
                'mime' => $mime,
                'size' => strlen($response->body()),
                'source' => $source,
            ]);

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Blog remote image fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Rewrite <img src="https://someone-else.com/..."> inside article bodies to
     * locally stored copies.
     */
    public function localiseInlineImages(?string $html, ?int $userId = null): ?string
    {
        if (! $html || ! str_contains($html, '<img')) {
            return $html;
        }

        return preg_replace_callback('/<img([^>]*)src=["\']([^"\']+)["\']/i', function ($m) use ($userId) {
            $src = $m[2];
            $host = parse_url((string) config('app.url'), PHP_URL_HOST);

            if (! Str::startsWith($src, ['http://', 'https://']) || ($host && str_contains($src, (string) $host))) {
                return $m[0];
            }

            $stored = $this->storeFromUrl($src, $userId, 'api');

            return $stored ? '<img' . $m[1] . 'src="' . asset('storage/' . $stored) . '"' : $m[0];
        }, $html);
    }

    /**
     * Strip anything dangerous out of submitted HTML and make the markup
     * search-engine friendly (lazy images, safe external links).
     */
    public function sanitize(string $html): string
    {
        // Drop script/style blocks and inline event handlers outright.
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;

        $html = strip_tags($html, self::ALLOWED_TAGS);

        // Lazy-load and decode inline images unless the author already said otherwise.
        $html = preg_replace_callback('/<img((?:(?!loading=)[^>])*)>/i', function ($m) {
            return '<img' . rtrim($m[1]) . ' loading="lazy" decoding="async">';
        }, $html) ?? $html;

        // External links open in a new tab and never leak link equity.
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'bebamart.com';
        $html = preg_replace_callback('/<a\s([^>]*href=["\'](https?:\/\/[^"\']+)["\'][^>]*)>/i', function ($m) use ($host) {
            if (str_contains($m[2], $host)) {
                return $m[0];
            }
            $attrs = $m[1];
            if (! str_contains(strtolower($attrs), 'rel=')) {
                $attrs .= ' rel="nofollow noopener"';
            }
            if (! str_contains(strtolower($attrs), 'target=')) {
                $attrs .= ' target="_blank"';
            }

            return '<a ' . trim($attrs) . '>';
        }, $html) ?? $html;

        return trim($html);
    }

    /**
     * Publish any scheduled post whose time has come.
     *
     * Called by the blog:publish-scheduled command and, as a safety net, by the
     * public blog surfaces - this host has no crontab, so a stalled scheduler
     * must never be the reason an article stays hidden. The cache lock keeps it
     * to one check every couple of minutes no matter how much traffic arrives.
     */
    public function releaseScheduledThrottled(): void
    {
        $cache = \Illuminate\Support\Facades\Cache::store();

        if ($cache->get('blog:scheduled:checked')) {
            return;
        }

        $cache->put('blog:scheduled:checked', true, now()->addMinutes(2));

        try {
            $this->releaseScheduled();
        } catch (\Throwable $e) {
            Log::warning('Scheduled blog release failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Publish any scheduled post whose time has come.
     */
    public function releaseScheduled(): int
    {
        $due = BlogPost::where('status', 'scheduled')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->get();

        foreach ($due as $post) {
            $post->status = 'published';
            $post->save();
            app(IndexNowService::class)->submit([$post->url]);
        }

        return $due->count();
    }

    /**
     * Break an article body into a flat list of render blocks so the mobile apps
     * can lay it out with native widgets rather than a web view.
     *
     * Block shapes:
     *   heading   level, text
     *   paragraph text, html
     *   image     url, alt, caption
     *   list      ordered, items[]
     *   quote     text
     *   code      text
     *   divider
     *
     * @return array<int, array<string, mixed>>
     */
    public function toBlocks(?string $html): array
    {
        $html = trim((string) $html);

        if ($html === '') {
            return [];
        }

        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('root');

        if (! $root) {
            return [['type' => 'paragraph', 'text' => trim(strip_tags($html)), 'html' => $html]];
        }

        $blocks = [];
        $this->walkNodes($root, $blocks);

        return $blocks;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    protected function walkNodes(\DOMNode $parent, array &$blocks): void
    {
        foreach ($parent->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $text = $this->cleanText($node->textContent);
                if ($text !== '') {
                    $blocks[] = ['type' => 'paragraph', 'text' => $text, 'html' => $text];
                }
                continue;
            }

            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            /** @var \DOMElement $node */
            $tag = strtolower($node->nodeName);

            switch ($tag) {
                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                    $text = $this->cleanText($node->textContent);
                    if ($text !== '') {
                        $blocks[] = [
                            'type' => 'heading',
                            'level' => max(2, (int) substr($tag, 1)),
                            'text' => $text,
                        ];
                    }
                    break;

                case 'p':
                    $image = $this->firstImage($node);
                    if ($image && $this->cleanText($node->textContent) === '') {
                        $blocks[] = $image;
                        break;
                    }
                    $text = $this->cleanText($node->textContent);
                    if ($text !== '') {
                        $blocks[] = [
                            'type' => 'paragraph',
                            'text' => $text,
                            'html' => $this->innerHtml($node),
                        ];
                    }
                    if ($image) {
                        $blocks[] = $image;
                    }
                    break;

                case 'img':
                    $blocks[] = $this->imageBlock($node);
                    break;

                case 'figure':
                    $image = $this->firstImage($node);
                    if ($image) {
                        $caption = '';
                        foreach ($node->getElementsByTagName('figcaption') as $cap) {
                            $caption = $this->cleanText($cap->textContent);
                            break;
                        }
                        $image['caption'] = $caption;
                        $blocks[] = $image;
                    }
                    break;

                case 'ul':
                case 'ol':
                    $items = [];
                    foreach ($node->getElementsByTagName('li') as $li) {
                        $text = $this->cleanText($li->textContent);
                        if ($text !== '') {
                            $items[] = $text;
                        }
                    }
                    if ($items) {
                        $blocks[] = ['type' => 'list', 'ordered' => $tag === 'ol', 'items' => $items];
                    }
                    break;

                case 'blockquote':
                    $text = $this->cleanText($node->textContent);
                    if ($text !== '') {
                        $blocks[] = ['type' => 'quote', 'text' => $text];
                    }
                    break;

                case 'pre':
                case 'code':
                    $text = trim($node->textContent);
                    if ($text !== '') {
                        $blocks[] = ['type' => 'code', 'text' => $text];
                    }
                    break;

                case 'hr':
                    $blocks[] = ['type' => 'divider'];
                    break;

                case 'table':
                    // Tables stay as HTML - the app shows them in a scrollable card.
                    $blocks[] = ['type' => 'html', 'html' => $this->outerHtml($node)];
                    break;

                case 'br':
                    break;

                default:
                    // div, section, span wrappers: descend into them.
                    $this->walkNodes($node, $blocks);
                    break;
            }
        }
    }

    protected function firstImage(\DOMNode $node): ?array
    {
        if (! $node instanceof \DOMElement) {
            return null;
        }

        foreach ($node->getElementsByTagName('img') as $img) {
            return $this->imageBlock($img);
        }

        return null;
    }

    protected function imageBlock(\DOMNode $img): array
    {
        $src = $img instanceof \DOMElement ? $img->getAttribute('src') : '';
        $alt = $img instanceof \DOMElement ? $img->getAttribute('alt') : '';

        return [
            'type' => 'image',
            'url' => $src,
            'alt' => $alt,
            'caption' => '',
        ];
    }

    protected function innerHtml(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return trim($html);
    }

    protected function outerHtml(\DOMNode $node): string
    {
        return trim($node->ownerDocument->saveHTML($node));
    }

    protected function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * The storage path of the first image inside a body, if it is one of ours.
     */
    public function firstImagePath(?string $html): ?string
    {
        if (! $html || ! preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            return null;
        }

        $src = $m[1];
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        // Only adopt images we host - a remote one would break the moment it moves.
        if (\Illuminate\Support\Str::startsWith($src, ['http://', 'https://'])) {
            if (! $host || ! str_contains($src, (string) $host)) {
                return null;
            }
            $src = parse_url($src, PHP_URL_PATH) ?: '';
        }

        $src = ltrim($src, '/');

        if (\Illuminate\Support\Str::startsWith($src, 'storage/')) {
            $src = substr($src, strlen('storage/'));
        }

        return $src !== '' && Storage::disk('public')->exists($src) ? $src : null;
    }
}
