<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">
    <channel>
        <title>Mastermind Insights</title>
        <link>{{ route('blog.index') }}</link>
        <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml"/>
        <description>Practical HR guidance from Mastermind Consult Ltd: hiring, payroll, labour law and people management in Uganda and East Africa.</description>
        <language>en-ug</language>
        <lastBuildDate>{{ optional($posts->first()?->published_at)->toRfc2822String() ?: now()->toRfc2822String() }}</lastBuildDate>
        <image>
            <url>{{ asset('favicon.png') }}</url>
            <title>Mastermind Insights</title>
            <link>{{ route('blog.index') }}</link>
        </image>
@foreach($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ $post->url }}</link>
            <guid isPermaLink="true">{{ $post->url }}</guid>
            <pubDate>{{ optional($post->published_at)->toRfc2822String() }}</pubDate>
            <description>{{ $post->meta_description_value }}</description>
            <content:encoded><![CDATA[{!! $post->rendered_content !!}]]></content:encoded>
@if($post->category)
            <category>{{ $post->category->name }}</category>
@endif
        </item>
@endforeach
    </channel>
</rss>
