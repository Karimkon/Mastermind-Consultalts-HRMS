<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($sitemaps as $sitemap)
    <sitemap>
        <loc>{!! htmlspecialchars($sitemap['loc'], ENT_XML1, 'UTF-8') !!}</loc>
        <lastmod>{{ $sitemap['lastmod'] }}</lastmod>
    </sitemap>
@endforeach
</sitemapindex>
