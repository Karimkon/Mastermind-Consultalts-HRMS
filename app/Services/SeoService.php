<?php

namespace App\Services;

use App\Models\BlogPost;
use Illuminate\Support\Str;

/**
 * Builds the structured data the public site emits. Everything is produced as a
 * PHP array and json_encode()d so no Blade escaping can corrupt the JSON-LD.
 */
class SeoService
{
    public const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;

    public const NAME = 'Mastermind Consult Ltd';

    public static function siteUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function logo(): string
    {
        return asset('images/logo.png');
    }

    /** Publisher identity — referenced by every other node via @id. */
    public static function organization(): array
    {
        return [
            '@type' => 'ProfessionalService',
            '@id' => self::siteUrl() . '/#organization',
            'name' => self::NAME,
            'alternateName' => 'Mastermind Consultants',
            'url' => self::siteUrl() . '/',
            'logo' => [
                '@type' => 'ImageObject',
                '@id' => self::siteUrl() . '/#logo',
                'url' => self::logo(),
                'contentUrl' => self::logo(),
                'caption' => self::NAME,
            ],
            'image' => ['@id' => self::siteUrl() . '/#logo'],
            'description' => 'Mastermind Consult Ltd is a strategic HR solutions partner in Uganda, providing executive recruitment, staffing, payroll and HR systems across East Africa.',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Kampala',
                'addressCountry' => 'UG',
            ],
            'areaServed' => [
                ['@type' => 'Country', 'name' => 'Uganda'],
                ['@type' => 'Place', 'name' => 'East Africa'],
            ],
            'knowsAbout' => [
                'Executive recruitment',
                'Staff outsourcing',
                'Payroll management',
                'HR systems',
                'Performance management',
                'Labour law compliance in Uganda',
            ],
            'sameAs' => array_values(array_filter([
                'https://www.linkedin.com/company/mastermind-consult',
            ])),
        ];
    }

    /** WebSite node with the sitelinks search box action. */
    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => self::siteUrl() . '/#website',
            'url' => self::siteUrl() . '/',
            'name' => self::NAME,
            'description' => 'Strategic HR solutions, executive recruitment and staffing across Uganda and East Africa.',
            'publisher' => ['@id' => self::siteUrl() . '/#organization'],
            'inLanguage' => 'en-UG',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => self::siteUrl() . '/blog?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /** The site-wide graph injected on every public page. */
    public static function siteGraph(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [self::organization(), self::website()],
        ];
    }

    /**
     * @param  array<int, array{name: string, url?: string|null}>  $items
     */
    public static function breadcrumbs(array $items): array
    {
        $list = [];
        $position = 1;

        foreach ($items as $item) {
            $entry = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $item['name'],
            ];

            if (! empty($item['url'])) {
                $entry['item'] = $item['url'];
            }

            $list[] = $entry;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ];
    }

    /** Article / BlogPosting graph for a single post. */
    public static function article(BlogPost $post): array
    {
        $url = $post->url;
        $image = $post->og_image_url ?: self::logo();

        $node = [
            '@type' => $post->schema_type ?: 'BlogPosting',
            '@id' => $url . '#article',
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'headline' => Str::limit($post->title, 110, ''),
            'name' => $post->title,
            'description' => $post->meta_description_value,
            'url' => $url,
            'datePublished' => optional($post->published_at ?: $post->created_at)->toAtomString(),
            'dateModified' => optional($post->updated_at)->toAtomString(),
            'inLanguage' => 'en-UG',
            'isAccessibleForFree' => true,
            'wordCount' => $post->word_count,
            'timeRequired' => 'PT' . max(1, (int) $post->reading_time) . 'M',
            'image' => [
                '@type' => 'ImageObject',
                'url' => $image,
                'contentUrl' => $image,
                'caption' => $post->featured_image_alt ?: $post->title,
            ],
            'author' => [
                '@type' => $post->author_name ? 'Person' : 'Organization',
                'name' => $post->display_author,
                'url' => $post->author_name ? $url . '#author' : self::siteUrl() . '/',
            ],
            'publisher' => ['@id' => self::siteUrl() . '/#organization'],
            'isPartOf' => ['@id' => self::siteUrl() . '/#website'],
        ];

        if ($post->category) {
            $node['articleSection'] = $post->category->name;
        }

        $keywords = collect([$post->focus_keyword])
            ->merge(explode(',', (string) $post->meta_keywords))
            ->merge($post->tags->pluck('name'))
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->unique()
            ->values();

        if ($keywords->isNotEmpty()) {
            $node['keywords'] = $keywords->implode(', ');
        }

        $node['speakable'] = [
            '@type' => 'SpeakableSpecification',
            'cssSelector' => ['h1', '.article-lead'],
        ];

        return [
            '@context' => 'https://schema.org',
            '@graph' => [$node],
        ];
    }

    /**
     * FAQPage block — this is what wins the expandable answers in Google.
     *
     * @param  array<int, array{question?: string, answer?: string}>|null  $faqs
     */
    public static function faq(?array $faqs): ?array
    {
        $entries = collect($faqs ?: [])
            ->filter(fn ($f) => ! empty($f['question']) && ! empty($f['answer']))
            ->map(fn ($f) => [
                '@type' => 'Question',
                'name' => strip_tags((string) $f['question']),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags((string) $f['answer']),
                ],
            ])
            ->values();

        if ($entries->isEmpty()) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entries->all(),
        ];
    }

    /** Blog index / archive listing. */
    public static function blogListing(string $name, string $url, $posts): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Blog',
            '@id' => $url . '#blog',
            'name' => $name,
            'url' => $url,
            'publisher' => ['@id' => self::siteUrl() . '/#organization'],
            'blogPost' => collect($posts)->take(20)->map(fn ($post) => [
                '@type' => 'BlogPosting',
                'headline' => Str::limit($post->title, 110, ''),
                'url' => $post->url,
                'datePublished' => optional($post->published_at)->toAtomString(),
                'image' => $post->featured_image_url,
                'author' => ['@type' => 'Person', 'name' => $post->display_author],
            ])->all(),
        ];
    }

    /** JobPosting graph, so open roles can win the Google Jobs box. */
    public static function jobPosting($job): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => $job->title,
            'description' => (string) $job->description,
            'datePosted' => optional($job->created_at)->toDateString(),
            'validThrough' => optional($job->deadline)->toAtomString(),
            'employmentType' => strtoupper(str_replace('-', '_', (string) $job->employment_type)),
            'hiringOrganization' => ['@id' => self::siteUrl() . '/#organization'],
            'jobLocation' => [
                '@type' => 'Place',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => $job->location ?: 'Kampala',
                    'addressCountry' => 'UG',
                ],
            ],
            'directApply' => true,
        ];
    }

    /** Render any array as a ready-to-print <script type="application/ld+json"> block. */
    public static function script(?array $data): string
    {
        if (empty($data)) {
            return '';
        }

        return '<script type="application/ld+json">'
            . json_encode($data, self::JSON_FLAGS)
            . '</script>';
    }
}
