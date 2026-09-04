<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach($urls as $url)
    <url>
        <loc>{!! htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') !!}</loc>
        @if(isset($url['lastmod']))
        <lastmod>{{ $url['lastmod'] }}</lastmod>
        @endif
        @if(isset($url['changefreq']))
        <changefreq>{{ $url['changefreq'] }}</changefreq>
        @endif
        @if(isset($url['priority']))
        <priority>{{ $url['priority'] }}</priority>
        @endif
        @if(isset($url['image']) && !empty($url['image']['loc']))
        <image:image>
            <image:loc>{!! htmlspecialchars($url['image']['loc'], ENT_XML1, 'UTF-8') !!}</image:loc>
            @if(!empty($url['image']['title']))
            <image:title>{!! htmlspecialchars($url['image']['title'], ENT_XML1, 'UTF-8') !!}</image:title>
            @endif
            @if(!empty($url['image']['caption']))
            <image:caption>{!! htmlspecialchars($url['image']['caption'], ENT_XML1, 'UTF-8') !!}</image:caption>
            @endif
        </image:image>
        @endif
    </url>
@endforeach
</urlset>
