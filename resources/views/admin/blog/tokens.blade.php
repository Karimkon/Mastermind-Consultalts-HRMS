@extends("layouts.app")

@section('title', 'Blog Publishing Keys')

@section("breadcrumb")<a href="{{ route('admin.blog.index') }}" class="text-slate-500 hover:text-blue-600 text-sm">Blog</a><span class="text-slate-300 mx-1">/</span><span class="text-slate-600 text-sm font-medium">AI publishing keys</span>@endsection

@section('content')
<x-page-header title="AI publishing keys" subtitle="Let an AI writer publish straight into the Mastermind blog" />


@include('admin.blog.partials.flash')

<div class="mb-5">
    <a href="{{ route('admin.blog.index') }}" class="text-sm text-gray-500 hover:text-blue-600">
        <i class="fas fa-arrow-left mr-1.5"></i>Back to all posts
    </a>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        {{-- Keys --}}
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="font-bold text-gray-900">Publishing keys</h2>
                    <p class="text-sm text-gray-500">One key per agent or tool. Disable a key to cut it off instantly.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.blog.tokens.store') }}" class="flex gap-2 mb-6">
                @csrf
                <input type="text" name="name" required maxlength="120" placeholder="e.g. Blog writer agent"
                       class="flex-1 rounded-lg border-gray-200 text-sm">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 whitespace-nowrap">
                    <i class="fas fa-key mr-1.5"></i>Create key
                </button>
            </form>

            @forelse($tokens as $token)
                <div class="rounded-xl border border-gray-200 p-4 mb-3">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="font-semibold text-gray-900">{{ $token->name }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $token->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $token->is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <code id="token-{{ $token->id }}" data-full="{{ $token->token }}"
                                      class="flex-1 text-xs bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 font-mono truncate select-all">
                                    {{ $token->masked_token }}
                                </code>
                                <button type="button" onclick="revealToken({{ $token->id }}, this)"
                                        class="px-3 py-2 rounded-lg bg-gray-100 text-gray-600 text-xs hover:bg-gray-200 whitespace-nowrap">
                                    <i class="far fa-eye"></i>
                                </button>
                                <button type="button" onclick="copyToken({{ $token->id }}, this)"
                                        class="px-3 py-2 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold hover:bg-blue-100 whitespace-nowrap">
                                    Copy
                                </button>
                            </div>

                            <p class="text-xs text-gray-400 mt-2">
                                Created {{ $token->created_at?->format('M j, Y') }}
                                @if($token->last_used_at)
                                    &middot; last used {{ $token->last_used_at->diffForHumans() }} from {{ $token->last_used_ip }}
                                    &middot; {{ number_format($token->requests_count) }} request{{ $token->requests_count === 1 ? '' : 's' }}
                                @else
                                    &middot; never used
                                @endif
                            </p>
                        </div>

                        <div class="flex gap-1 flex-shrink-0">
                            <form method="POST" action="{{ route('admin.blog.tokens.toggle', $token->id) }}">
                                @csrf
                                <button type="submit" title="{{ $token->is_active ? 'Disable' : 'Enable' }}"
                                        class="w-9 h-9 rounded-lg hover:bg-gray-100 text-gray-500">
                                    <i class="fas {{ $token->is_active ? 'fa-pause' : 'fa-play' }} text-xs"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.blog.tokens.destroy', $token->id) }}"
                                  onsubmit="return confirm('Delete this key? Any agent using it stops working immediately.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="w-9 h-9 rounded-lg hover:bg-red-50 text-red-500">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border-2 border-dashed border-gray-200 p-10 text-center">
                    <div class="w-12 h-12 mx-auto rounded-2xl bg-gray-50 flex items-center justify-center mb-3">
                        <i class="fas fa-robot text-gray-300 text-lg"></i>
                    </div>
                    <p class="font-semibold text-gray-900 mb-1">No keys yet</p>
                    <p class="text-gray-500 text-sm">Create one above, then paste it into your AI writer.</p>
                </div>
            @endforelse
        </div>

        {{-- API reference --}}
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="font-bold text-gray-900 mb-1">How the agent publishes</h2>
            <p class="text-sm text-gray-500 mb-5">
                One POST creates a real, server-rendered page at <code class="text-xs bg-gray-50 px-1.5 py-0.5 rounded">/blog/&lt;slug&gt;</code>
                — indexed by Google, in the sitemap and in the RSS feed. Re-sending the same <code class="text-xs bg-gray-50 px-1.5 py-0.5 rounded">external_id</code> updates that post instead of duplicating it.
            </p>

            <div class="rounded-xl bg-ink-900 text-gray-100 p-4 overflow-x-auto mb-5">
<pre class="text-xs leading-relaxed"><code>POST {{ $endpoint }}
Authorization: Bearer &lt;your publishing key&gt;
Content-Type: application/json

{
  "title": "How to buy electronics online in Uganda safely",
  "slug": "buy-electronics-online-uganda-safely",
  "excerpt": "A practical checklist before you pay for any gadget online.",
  "content": "&lt;h2&gt;Start with the seller&lt;/h2&gt;&lt;p&gt;...&lt;/p&gt;",
  "category": "Buying Guides",
  "tags": ["electronics", "escrow", "safety"],
  "featured_image": "https://example.com/hero.jpg",
  "featured_image_alt": "Person unboxing a phone",
  "meta_title": "How to Buy Electronics Online in Uganda Safely (2026)",
  "meta_description": "The 7 checks that stop you losing money on gadgets bought online in Uganda.",
  "focus_keyword": "buy electronics online uganda",
  "author_name": "Mastermind Editorial Team",
  "faqs": [
    { "question": "Is escrow free?", "answer": "Yes, escrow is included on every order." }
  ],
  "status": "published",
  "external_id": "agent-post-00123"
}</code></pre>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                        <tr>
                            <th class="text-left font-semibold px-3 py-2">Endpoint</th>
                            <th class="text-left font-semibold px-3 py-2">What it does</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-600">
                        <tr><td class="px-3 py-2 font-mono text-xs">POST /api/blog/posts</td><td class="px-3 py-2">Create or update an article</td></tr>
                        <tr><td class="px-3 py-2 font-mono text-xs">PUT /api/blog/posts/{id|slug}</td><td class="px-3 py-2">Update an existing article</td></tr>
                        <tr><td class="px-3 py-2 font-mono text-xs">GET /api/blog/posts</td><td class="px-3 py-2">List recent articles</td></tr>
                        <tr><td class="px-3 py-2 font-mono text-xs">DELETE /api/blog/posts/{id|slug}</td><td class="px-3 py-2">Remove an article</td></tr>
                        <tr><td class="px-3 py-2 font-mono text-xs">POST /api/blog/media</td><td class="px-3 py-2">Upload an image (file upload or <code>{"url": "..."}</code>)</td></tr>
                        <tr><td class="px-3 py-2 font-mono text-xs">GET /api/blog/categories</td><td class="px-3 py-2">List blog categories</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-5 rounded-xl bg-blue-50 border border-blue-100 p-4 text-sm text-blue-900">
                <p class="font-semibold mb-1"><i class="fas fa-circle-info mr-1.5"></i>Only <code>title</code> and <code>content</code> are required.</p>
                <p>
                    Markdown is accepted — we convert it. Remote images are copied onto mastermind.autos automatically.
                    Anything you leave out (excerpt, meta description, reading time) is generated for you.
                </p>
            </div>
        </div>
    </div>

    {{-- Side notes --}}
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="font-bold text-gray-900 mb-3">Why not a paste-in script?</h2>
            <p class="text-sm text-gray-600 leading-relaxed mb-3">
                Embed snippets render articles with JavaScript inside one page. Google usually indexes that as a
                <em>single</em> URL, so fifty articles compete as one thin page and none of them rank.
            </p>
            <p class="text-sm text-gray-600 leading-relaxed">
                Publishing through this API gives every article its own real URL, its own title and meta description,
                its own Open Graph image and its own entry in the sitemap — which is what actually earns search traffic.
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="font-bold text-gray-900 mb-3">Test a key right now</h2>
            <p class="text-sm text-gray-500 mb-3">Paste this into any terminal after swapping in your key.</p>
            <div class="rounded-xl bg-ink-900 text-gray-100 p-3 overflow-x-auto">
<pre class="text-[11px] leading-relaxed"><code>curl -X POST {{ $endpoint }} \
  -H "Authorization: Bearer YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"title":"Hello from the agent",
       "content":"&lt;p&gt;First automated post.&lt;/p&gt;",
       "status":"draft"}'</code></pre>
            </div>
            <p class="text-xs text-gray-400 mt-3">
                It lands as a draft under Blog &rarr; posts. Publish it from there once you are happy.
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="font-bold text-gray-900 mb-3">Live feeds</h2>
            <ul class="text-sm space-y-2">
                <li><a href="{{ route('blog.index') }}" target="_blank" class="text-blue-600 hover:underline">/blog</a> — the public blog</li>
                <li><a href="{{ route('blog.feed') }}" target="_blank" class="text-blue-600 hover:underline">/blog/feed.xml</a> — RSS feed</li>
                <li><a href="{{ route('sitemap.blog') }}" target="_blank" class="text-blue-600 hover:underline">/sitemap-blog.xml</a> — blog sitemap</li>
                <li><a href="{{ route('sitemap.index') }}" target="_blank" class="text-blue-600 hover:underline">/sitemap.xml</a> — sitemap index</li>
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function revealToken(id, btn) {
        const el = document.getElementById('token-' + id);
        const full = el.dataset.full;
        if (el.dataset.revealed === '1') {
            el.textContent = full.substring(0, 10) + '********************' + full.slice(-4);
            el.dataset.revealed = '0';
            btn.innerHTML = '<i class="far fa-eye"></i>';
        } else {
            el.textContent = full;
            el.dataset.revealed = '1';
            btn.innerHTML = '<i class="far fa-eye-slash"></i>';
        }
    }

    function copyToken(id, btn) {
        const el = document.getElementById('token-' + id);
        navigator.clipboard.writeText(el.dataset.full).then(() => {
            btn.textContent = 'Copied!';
            setTimeout(() => { btn.textContent = 'Copy'; }, 1800);
        });
    }
</script>
@endpush
