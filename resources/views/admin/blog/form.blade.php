@php
    $isEdit = $post->exists;
    $action = $isEdit ? route('admin.blog.update', $post->id) : route('admin.blog.store');
    $faqs = old('faq_question') ? array_map(null, old('faq_question', []), old('faq_answer', [])) : collect($post->faqs ?? [])->map(fn ($f) => [$f['question'] ?? '', $f['answer'] ?? ''])->all();
    if (empty($faqs)) { $faqs = [['', '']]; }
@endphp

@include('admin.blog.partials.flash')

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" id="postForm">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="grid lg:grid-cols-3 gap-6">
        {{-- Main column --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Cover image: this is the picture that appears on the blog cards,
                 at the top of the article and on WhatsApp/Facebook shares. --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-start justify-between mb-1">
                    <h2 class="font-bold text-gray-900">Cover image</h2>
                    @if($post->featured_image_url)
                        <button type="button" onclick="removeCover()"
                                class="text-xs text-red-500 hover:underline">Remove</button>
                    @endif
                </div>
                <p class="text-sm text-gray-500 mb-4">
                    Shown on the blog cards, at the top of the article and when the link is shared.
                    Landscape, about 1200 &times; 675px.
                </p>

                <div id="coverDropZone"
                     onclick="document.getElementById('featuredInput').click()"
                     class="relative rounded-xl border-2 border-dashed border-gray-300 overflow-hidden bg-gray-50 cursor-pointer hover:border-blue-400 hover:bg-blue-50/40 transition aspect-[16/7] flex items-center justify-center">
                    <img src="{{ $post->featured_image_url ?: '' }}" alt="" id="featuredPreview"
                         class="absolute inset-0 w-full h-full object-cover {{ $post->featured_image_url ? '' : 'hidden' }}">

                    <div class="text-center text-gray-400 p-6 relative" id="featuredPlaceholder"
                         @if($post->featured_image_url) style="display:none" @endif>
                        <i class="fas fa-cloud-arrow-up text-3xl mb-2 text-gray-300"></i>
                        <p class="text-sm font-medium text-gray-600">Click to upload a cover image</p>
                        <p class="text-xs mt-1">or drag one in &middot; JPG, PNG or WebP up to 8&nbsp;MB</p>
                    </div>
                </div>

                <input type="file" name="featured_image" id="featuredInput" accept="image/*" class="hidden" onchange="previewFeatured(this)">
                <input type="hidden" name="featured_image_path" id="featuredPathField" value="">
                <input type="hidden" name="remove_featured_image" id="removeCoverField" value="0">

                <div class="flex gap-2 mt-3">
                    <button type="button" onclick="document.getElementById('featuredInput').click()"
                            class="flex-1 py-2 rounded-lg bg-blue-50 text-blue-700 text-sm font-semibold hover:bg-blue-100">
                        <i class="fas fa-upload mr-1.5"></i>Upload from this device
                    </button>
                    <button type="button" onclick="openMediaPicker()"
                            class="flex-1 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200">
                        <i class="fas fa-images mr-1.5"></i>Pick from library
                    </button>
                </div>

                @if(! $post->featured_image_url)
                    <p class="text-xs text-gray-400 mt-3">
                        <i class="fas fa-circle-info mr-1"></i>
                        If you leave this empty, we will use the first image inside your article as the cover.
                    </p>
                @endif

                <div class="grid md:grid-cols-2 gap-4 mt-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Alt text</label>
                        <input type="text" name="featured_image_alt" value="{{ old('featured_image_alt', $post->featured_image_alt) }}" maxlength="255"
                               placeholder="Describe the image for screen readers and Google Images"
                               class="w-full rounded-lg border-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Caption</label>
                        <input type="text" name="featured_image_caption" value="{{ old('featured_image_caption', $post->featured_image_caption) }}" maxlength="255"
                               placeholder="Optional line printed under the image"
                               class="w-full rounded-lg border-gray-200 text-sm">
                    </div>
                </div>

                <label class="block text-sm font-semibold text-gray-700 mb-1.5 mt-4">Different image for social shares (optional)</label>
                <input type="file" name="og_image" accept="image/*" class="w-full text-sm text-gray-600">
                <p class="text-xs text-gray-400 mt-1">Defaults to the cover image. 1200 &times; 630px.</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" id="titleField" value="{{ old('title', $post->title) }}" required maxlength="255"
                       placeholder="How to buy electronics online in Uganda without getting scammed"
                       class="w-full rounded-lg border-gray-200 text-lg font-semibold focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                <div class="flex justify-between mt-1.5 text-xs text-gray-400">
                    <span>A clear, specific title with your keyword near the front ranks best.</span>
                    <span><span id="titleCount">0</span>/65</span>
                </div>

                <label class="block text-sm font-semibold text-gray-700 mb-2 mt-5">Subtitle / standfirst</label>
                <input type="text" name="subtitle" value="{{ old('subtitle', $post->subtitle) }}" maxlength="255"
                       placeholder="One sentence that sells the article"
                       class="w-full rounded-lg border-gray-200 focus:ring-2 focus:ring-blue-100 focus:border-blue-400">

                <label class="block text-sm font-semibold text-gray-700 mb-2 mt-5">URL slug</label>
                <div class="flex items-center rounded-lg border border-gray-200 overflow-hidden focus-within:ring-2 focus-within:ring-blue-100">
                    <span class="px-3 py-2 bg-gray-50 text-gray-400 text-sm border-r border-gray-200 whitespace-nowrap">{{ rtrim(config('app.url'), '/') }}/blog/</span>
                    <input type="text" name="slug" id="slugField" value="{{ old('slug', $post->slug) }}"
                           class="flex-1 border-0 focus:ring-0 text-sm" placeholder="auto-generated-from-title">
                </div>
                <p class="text-xs text-gray-400 mt-1.5">Leave blank to generate it from the title. Changing it on a live post breaks existing links.</p>

                <label class="block text-sm font-semibold text-gray-700 mb-2 mt-5">Excerpt</label>
                <textarea name="excerpt" rows="2" maxlength="500"
                          placeholder="Short summary shown on the blog index and in social shares."
                          class="w-full rounded-lg border-gray-200 text-sm focus:ring-2 focus:ring-blue-100 focus:border-blue-400">{{ old('excerpt', $post->excerpt) }}</textarea>
                <p class="text-xs text-gray-400 mt-1.5">Optional — we generate one from the article if you leave it empty.</p>
            </div>

            {{-- Editor --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-3">
                    <label class="block text-sm font-semibold text-gray-700">Article content <span class="text-red-500">*</span></label>
                    <button type="button" onclick="toggleSourceMode()" id="sourceToggle"
                            class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200">
                        <i class="fas fa-code mr-1"></i>HTML source
                    </button>
                </div>
                <textarea name="content" id="contentEditor" rows="24">{{ old('content', $post->content) }}</textarea>
                <input type="hidden" name="content_format" value="html">
                <p class="text-xs text-gray-400 mt-2">
                    Use <strong>Heading 2</strong> for the main sections — they build the table of contents and are what Google reads first.
                    Paste images straight in, or use the image button to upload.
                </p>
            </div>

            {{-- SEO panel --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="font-bold text-gray-900 mb-1 flex items-center gap-2">
                    <i class="fas fa-magnifying-glass-chart text-blue-600"></i>Search engine listing
                </h2>
                <p class="text-sm text-gray-500 mb-5">This is how the article will look in Google results.</p>

                {{-- SERP preview --}}
                <div class="rounded-xl border border-gray-200 p-4 mb-6 bg-gray-50">
                    <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                        <img src="{{ asset('favicon.png') }}" alt="" class="w-4 h-4 rounded">
                        <span>{{ config('app.name') }}</span>
                        <span class="text-gray-300">›</span>
                        <span id="serpUrl">{{ rtrim(config('app.url'), '/') }}/blog/{{ $post->slug ?: 'your-post' }}</span>
                    </div>
                    <p id="serpTitle" class="text-[#1a0dab] text-lg leading-snug truncate">{{ $post->meta_title ?: $post->title ?: 'Your article title appears here' }}</p>
                    <p id="serpDesc" class="text-sm text-[#4d5156] line-clamp-2">{{ $post->meta_description ?: $post->excerpt ?: 'Your meta description appears here. Write 120-160 characters that make someone want to click.' }}</p>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Focus keyword</label>
                        <input type="text" name="focus_keyword" value="{{ old('focus_keyword', $post->focus_keyword) }}" maxlength="120"
                               placeholder="e.g. buy phones online in Uganda"
                               class="w-full rounded-lg border-gray-200 text-sm focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                        <p class="text-xs text-gray-400 mt-1">The one phrase this article should rank for.</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Meta title</label>
                        <input type="text" name="meta_title" id="metaTitleField" value="{{ old('meta_title', $post->meta_title) }}" maxlength="255"
                               placeholder="Defaults to the post title"
                               class="w-full rounded-lg border-gray-200 text-sm focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                        <div class="flex justify-between text-xs text-gray-400 mt-1">
                            <span>Aim for 50–65 characters.</span>
                            <span id="metaTitleCount">0</span>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Meta description</label>
                        <textarea name="meta_description" id="metaDescField" rows="2" maxlength="320"
                                  placeholder="120-160 characters that summarise the article and invite the click."
                                  class="w-full rounded-lg border-gray-200 text-sm focus:ring-2 focus:ring-blue-100 focus:border-blue-400">{{ old('meta_description', $post->meta_description) }}</textarea>
                        <div class="flex justify-between text-xs text-gray-400 mt-1">
                            <span>Aim for 120–160 characters.</span>
                            <span id="metaDescCount">0</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Meta keywords</label>
                        <input type="text" name="meta_keywords" value="{{ old('meta_keywords', $post->meta_keywords) }}" maxlength="255"
                               placeholder="comma, separated, terms"
                               class="w-full rounded-lg border-gray-200 text-sm focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Schema type</label>
                        <select name="schema_type" class="w-full rounded-lg border-gray-200 text-sm">
                            @foreach(['BlogPosting' => 'Blog post', 'Article' => 'Article', 'NewsArticle' => 'News article', 'HowTo' => 'How-to guide'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('schema_type', $post->schema_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Canonical URL</label>
                        <input type="url" name="canonical_url" value="{{ old('canonical_url', $post->canonical_url) }}"
                               placeholder="Only if this article was first published somewhere else"
                               class="w-full rounded-lg border-gray-200 text-sm focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                    </div>
                </div>
            </div>

            {{-- FAQ repeater --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="font-bold text-gray-900 mb-1 flex items-center gap-2">
                    <i class="fas fa-circle-question text-blue-600"></i>FAQ block
                </h2>
                <p class="text-sm text-gray-500 mb-5">
                    Questions here render at the bottom of the article <em>and</em> as FAQ structured data — this is what wins the
                    expandable answers under your result in Google.
                </p>

                <div id="faqRows" class="space-y-3">
                    @foreach($faqs as $i => $faq)
                        <div class="faq-row rounded-xl border border-gray-200 p-4 bg-gray-50">
                            <div class="flex items-start gap-3">
                                <div class="flex-1 space-y-2">
                                    <input type="text" name="faq_question[]" value="{{ $faq[0] ?? '' }}" maxlength="255"
                                           placeholder="Question"
                                           class="w-full rounded-lg border-gray-200 text-sm font-medium">
                                    <textarea name="faq_answer[]" rows="2" maxlength="2000" placeholder="Answer"
                                              class="w-full rounded-lg border-gray-200 text-sm">{{ $faq[1] ?? '' }}</textarea>
                                </div>
                                <button type="button" onclick="this.closest('.faq-row').remove()"
                                        class="w-8 h-8 rounded-lg text-red-500 hover:bg-red-50 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <button type="button" onclick="addFaqRow()"
                        class="mt-3 px-4 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200">
                    <i class="fas fa-plus mr-1.5"></i>Add question
                </button>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Publish box --}}
            <div class="bg-white rounded-xl shadow-sm p-6 lg:sticky lg:top-4 z-10">
                <h2 class="font-bold text-gray-900 mb-4">Publishing</h2>

                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                <select name="status" id="statusField" class="w-full rounded-lg border-gray-200 text-sm mb-4">
                    @foreach(['draft' => 'Draft (only you can see it)', 'published' => 'Published (live)', 'scheduled' => 'Scheduled', 'archived' => 'Archived'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $post->status ?: 'draft') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Publish date &amp; time</label>
                <input type="datetime-local" name="published_at"
                       value="{{ old('published_at', optional($post->published_at)->format('Y-m-d\TH:i')) }}"
                       class="w-full rounded-lg border-gray-200 text-sm mb-4">

                <label class="flex items-center gap-2 mb-2 cursor-pointer">
                    <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $post->is_featured))
                           class="rounded border-gray-300 text-blue-600">
                    <span class="text-sm text-gray-700">Feature on the blog home</span>
                </label>

                <label class="flex items-center gap-2 mb-5 cursor-pointer">
                    <input type="checkbox" name="noindex" value="1" @checked(old('noindex', $post->noindex))
                           class="rounded border-gray-300 text-blue-600">
                    <span class="text-sm text-gray-700">Hide from search engines</span>
                </label>

                @if($isEdit)
                    <div class="rounded-xl bg-gray-50 p-4 mb-5 text-sm space-y-1.5">
                        <div class="flex justify-between"><span class="text-gray-500">SEO score</span>
                            <span class="font-semibold {{ $post->seo_score >= 75 ? 'text-green-600' : ($post->seo_score >= 50 ? 'text-amber-600' : 'text-red-500') }}">
                                {{ $post->seo_score }}/100
                            </span>
                        </div>
                        <div class="flex justify-between"><span class="text-gray-500">Words</span><span class="font-medium">{{ number_format($post->word_count) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Reading time</span><span class="font-medium">{{ $post->reading_time }} min</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Views</span><span class="font-medium">{{ number_format($post->view_count) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Source</span><span class="font-medium">{{ in_array($post->source, ['ai','api']) ? 'AI agent' : 'Dashboard' }}</span></div>
                    </div>
                @endif

                <button type="submit" class="w-full py-2.5 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700">
                    <i class="fas fa-save mr-1.5"></i>{{ $isEdit ? 'Save changes' : 'Create post' }}
                </button>

                @if($isEdit)
                    <div class="flex gap-2 mt-2">
                        <a href="{{ $post->url }}" target="_blank" rel="noopener"
                           class="flex-1 text-center py-2.5 rounded-lg bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200">
                            <i class="fas fa-arrow-up-right-from-square mr-1"></i>Preview
                        </a>
                        <form method="POST" action="{{ route('admin.blog.duplicate', $post->id) }}" class="flex-1">
                            @csrf
                            <button type="submit" class="w-full py-2.5 rounded-lg bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200">
                                <i class="fas fa-copy mr-1"></i>Duplicate
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            {{-- Organisation --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="font-bold text-gray-900 mb-4">Organisation</h2>

                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Blog category</label>
                <select name="blog_category_id" class="w-full rounded-lg border-gray-200 text-sm mb-4">
                    <option value="">— None —</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('blog_category_id', $post->blog_category_id) == $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>

                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tags</label>
                <input type="text" name="tags" value="{{ old('tags', $tagList) }}" maxlength="500"
                       placeholder="escrow, electronics, shipping"
                       class="w-full rounded-lg border-gray-200 text-sm mb-1">
                <p class="text-xs text-gray-400 mb-4">Comma separated. New tags are created automatically.</p>

                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Link to an open role</label>
                <select name="related_job_id" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="">— None —</option>
                    @foreach($jobs as $category)
                        <option value="{{ $category->id }}" @selected(old('related_job_id', $post->related_job_id) == $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">The role appears at the bottom of the article as a "we are hiring" block.</p>
            </div>

            {{-- Author --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="font-bold text-gray-900 mb-4">Author byline</h2>

                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Name</label>
                <input type="text" name="author_name" value="{{ old('author_name', $post->author_name) }}" maxlength="120"
                       placeholder="{{ config('app.name') }} Editorial Team"
                       class="w-full rounded-lg border-gray-200 text-sm mb-3">

                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Role / bio line</label>
                <input type="text" name="author_title" value="{{ old('author_title', $post->author_title) }}" maxlength="120"
                       placeholder="Marketplace editor"
                       class="w-full rounded-lg border-gray-200 text-sm mb-3">

                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Photo</label>
                <input type="file" name="author_avatar" accept="image/*" class="w-full text-sm text-gray-600">
            </div>
        </div>
    </div>
</form>

{{-- Media picker modal --}}
<div id="mediaPicker" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[80vh] flex flex-col">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <h3 class="font-bold text-gray-900">Media library</h3>
            <button type="button" onclick="closeMediaPicker()" class="w-8 h-8 rounded-lg hover:bg-gray-100 text-gray-500">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <div class="p-5 overflow-y-auto">
            @if($media->count())
                <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
                    @foreach($media as $item)
                        <button type="button" onclick="pickMedia('{{ $item->url }}', '{{ $item->path }}')"
                                class="aspect-square rounded-lg overflow-hidden border-2 border-transparent hover:border-blue-500 bg-gray-100">
                            <img src="{{ $item->url }}" alt="{{ $item->alt }}" class="w-full h-full object-cover" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @else
                <p class="text-center text-gray-500 py-10">Nothing in the library yet. Upload an image to get started.</p>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.6.1/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    const UPLOAD_URL = @json(route('admin.blog.media.upload'));
    const CSRF = @json(csrf_token());

    const tinymceConfig = {
        selector: '#contentEditor',
        license_key: 'gpl',
        height: 620,
        menubar: 'edit insert format table',
        plugins: 'advlist autolink lists link image media table code codesample charmap searchreplace visualblocks wordcount fullscreen anchor autosave quickbars',
        toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter | bullist numlist | link image media table blockquote | codesample removeformat | fullscreen code',
        block_formats: 'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4',
        quickbars_selection_toolbar: 'bold italic link h2 h3 blockquote',
        content_style: "body{font-family:Outfit,system-ui,sans-serif;font-size:16px;line-height:1.75;color:#334155} h2,h3,h4{font-family:Sora,sans-serif;color:#0f172a} img{max-width:100%;height:auto;border-radius:12px}",
        branding: false,
        promotion: false,
        relative_urls: false,
        remove_script_host: false,
        convert_urls: false,
        automatic_uploads: true,
        images_upload_handler: (blobInfo, progress) => new Promise((resolve, reject) => {
            const data = new FormData();
            data.append('file', blobInfo.blob(), blobInfo.filename());
            data.append('_token', CSRF);

            fetch(UPLOAD_URL, { method: 'POST', body: data, headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
                .then(r => r.ok ? r.json() : Promise.reject('Upload failed (' + r.status + ')'))
                .then(json => json.location ? resolve(json.location) : reject('Upload failed'))
                .catch(err => reject(String(err)));
        }),
        setup: (editor) => {
            editor.on('change keyup', () => editor.save());
        }
    };

    tinymce.init(tinymceConfig);

    // Keep the plain textarea in sync before submitting (source mode + validation).
    document.getElementById('postForm').addEventListener('submit', () => {
        if (tinymce.get('contentEditor')) { tinymce.get('contentEditor').save(); }
    });

    let sourceMode = false;
    function toggleSourceMode() {
        const btn = document.getElementById('sourceToggle');
        const ta = document.getElementById('contentEditor');

        if (sourceMode) {
            // Back to the visual editor with whatever the raw HTML now says.
            tinymce.init(Object.assign({}, tinymceConfig, { selector: '#contentEditor' }));
            btn.innerHTML = '<i class="fas fa-code mr-1"></i>HTML source';
            sourceMode = false;
            return;
        }

        const editor = tinymce.get('contentEditor');
        if (editor) {
            editor.save();
            editor.remove();
        }
        ta.classList.add('w-full', 'rounded-lg', 'border-gray-200', 'font-mono', 'text-xs');
        ta.style.minHeight = '520px';
        btn.innerHTML = '<i class="fas fa-rotate-left mr-1"></i>Back to editor';
        sourceMode = true;
    }

    // Featured image preview
    function previewFeatured(input) {
        if (!input.files || !input.files[0]) return;
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.getElementById('featuredPreview');
            img.src = e.target.result;
            img.classList.remove('hidden');
            const ph = document.getElementById('featuredPlaceholder');
            if (ph) ph.style.display = 'none';
            document.getElementById('featuredPathField').value = '';
            document.getElementById('removeCoverField').value = '0';
        };
        reader.readAsDataURL(input.files[0]);
    }

    // Drag a file straight onto the cover box.
    (function () {
        const zone = document.getElementById('coverDropZone');
        const input = document.getElementById('featuredInput');
        if (!zone) return;

        ['dragenter', 'dragover'].forEach(evt => zone.addEventListener(evt, e => {
            e.preventDefault();
            zone.classList.add('border-blue-500', 'bg-blue-50');
        }));

        ['dragleave', 'drop'].forEach(evt => zone.addEventListener(evt, e => {
            e.preventDefault();
            zone.classList.remove('border-blue-500', 'bg-blue-50');
        }));

        zone.addEventListener('drop', e => {
            const file = e.dataTransfer?.files?.[0];
            if (!file || !file.type.startsWith('image/')) return;
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
            previewFeatured(input);
        });
    })();

    function removeCover() {
        const img = document.getElementById('featuredPreview');
        img.src = '';
        img.classList.add('hidden');
        const ph = document.getElementById('featuredPlaceholder');
        if (ph) ph.style.display = '';
        document.getElementById('featuredInput').value = '';
        document.getElementById('featuredPathField').value = '';
        document.getElementById('removeCoverField').value = '1';
    }

    function openMediaPicker() { const m = document.getElementById('mediaPicker'); m.classList.remove('hidden'); m.classList.add('flex'); }
    function closeMediaPicker() { const m = document.getElementById('mediaPicker'); m.classList.add('hidden'); m.classList.remove('flex'); }
    function pickMedia(url, path) {
        const img = document.getElementById('featuredPreview');
        img.src = url;
        img.classList.remove('hidden');
        const ph = document.getElementById('featuredPlaceholder');
        if (ph) ph.style.display = 'none';
        document.getElementById('featuredPathField').value = path;
        document.getElementById('featuredInput').value = '';
        document.getElementById('removeCoverField').value = '0';
        closeMediaPicker();
    }

    // FAQ rows
    function addFaqRow() {
        const wrap = document.getElementById('faqRows');
        const row = document.createElement('div');
        row.className = 'faq-row rounded-xl border border-gray-200 p-4 bg-gray-50';
        row.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="flex-1 space-y-2">
                    <input type="text" name="faq_question[]" maxlength="255" placeholder="Question" class="w-full rounded-lg border-gray-200 text-sm font-medium">
                    <textarea name="faq_answer[]" rows="2" maxlength="2000" placeholder="Answer" class="w-full rounded-lg border-gray-200 text-sm"></textarea>
                </div>
                <button type="button" onclick="this.closest('.faq-row').remove()" class="w-8 h-8 rounded-lg text-red-500 hover:bg-red-50 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-trash text-xs"></i>
                </button>
            </div>`;
        wrap.appendChild(row);
    }

    // Live SERP preview + counters
    (function () {
        const title = document.getElementById('titleField');
        const slug = document.getElementById('slugField');
        const metaTitle = document.getElementById('metaTitleField');
        const metaDesc = document.getElementById('metaDescField');
        const serpTitle = document.getElementById('serpTitle');
        const serpDesc = document.getElementById('serpDesc');
        const serpUrl = document.getElementById('serpUrl');
        const base = @json(rtrim(config('app.url'), '/') . '/blog/');

        const slugify = s => s.toLowerCase().trim()
            .replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').substring(0, 70);

        const sync = () => {
            const t = (metaTitle.value || title.value || 'Your article title appears here');
            serpTitle.textContent = t;
            document.getElementById('titleCount').textContent = title.value.length;
            document.getElementById('metaTitleCount').textContent = (metaTitle.value || title.value).length;
            const d = metaDesc.value || 'Your meta description appears here.';
            serpDesc.textContent = d;
            const len = metaDesc.value.length;
            const counter = document.getElementById('metaDescCount');
            counter.textContent = len;
            counter.className = (len >= 120 && len <= 160) ? 'text-green-600 font-semibold' : (len > 160 ? 'text-red-500 font-semibold' : 'text-gray-400');
            serpUrl.textContent = base + (slug.value || slugify(title.value) || 'your-post');
        };

        [title, slug, metaTitle, metaDesc].forEach(el => el && el.addEventListener('input', sync));
        sync();
    })();
</script>
@endpush
