<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The sitemaps and the RSS feed must come out as parseable XML.
 *
 * All three blades opened with a literal `<?xml version="1.0" encoding="UTF-8"?>`.
 * Production runs short_open_tag=On, so PHP read that `<?` as an opening tag and
 * tried to execute `xml version="1.0" encoding="UTF-8"` as code - every one of
 * them died on a parse error, and the sitemap Google reads was among them.
 *
 * It never showed up locally because this machine has short_open_tag=Off, where
 * the same bytes are harmless text. That is the trap: the setting, not the code,
 * decided whether it worked, and the two machines disagreed.
 *
 * The declaration is now echoed from a quoted string, which is immune either
 * way. These tests assert the OUTPUT parses, because a template that lints is
 * not the same as a document a crawler can read.
 */
class XmlFeedTest extends TestCase
{
    use RefreshDatabase;

    public static function xmlRoutes(): array
    {
        return [
            'sitemap index' => ['/sitemap.xml', 'sitemapindex'],
            'pages sitemap' => ['/sitemap-pages.xml', 'urlset'],
            'blog sitemap' => ['/sitemap-blog.xml', 'urlset'],
            'jobs sitemap' => ['/sitemap-jobs.xml', 'urlset'],
            'rss feed' => ['/blog/feed.xml', 'rss'],
        ];
    }

    #[DataProvider("xmlRoutes")]
    public function test_the_route_returns_parseable_xml(string $url, string $rootElement): void
    {
        $body = $this->get($url)->assertOk()->getContent();

        // The declaration has to be the very first thing in the document - no
        // leading whitespace, and certainly no PHP error text.
        $this->assertStringStartsWith(
            '<?xml version="1.0" encoding="UTF-8"?>',
            $body,
            "{$url} did not start with the XML declaration. First 120 bytes: "
                . substr($body, 0, 120)
        );

        $this->assertStringNotContainsStringIgnoringCase('parse error', $body);
        $this->assertStringNotContainsStringIgnoringCase('syntax error', $body);

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($xml, "{$url} is not well-formed XML: "
            . implode('; ', array_map(fn ($e) => trim($e->message), $errors)));
        $this->assertSame($rootElement, $xml->getName(), "{$url} has the wrong root element.");
    }
}
