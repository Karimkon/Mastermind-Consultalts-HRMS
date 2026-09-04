<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('color', 20)->default('#1d4ed8');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blog_category_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('related_job_id')->nullable()->index(); // open role to cross-link

            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('content_format', 20)->default('html'); // html | markdown

            $table->string('featured_image')->nullable();
            $table->string('featured_image_alt')->nullable();
            $table->string('featured_image_caption')->nullable();
            $table->string('og_image')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('focus_keyword', 120)->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('schema_type', 40)->default('BlogPosting');
            $table->json('faqs')->nullable();
            $table->boolean('noindex')->default(false);
            $table->unsignedTinyInteger('seo_score')->default(0);

            // Authorship
            $table->string('author_name', 120)->nullable();
            $table->string('author_title', 120)->nullable();
            $table->string('author_avatar')->nullable();

            // Publication
            $table->string('status', 20)->default('draft'); // draft|scheduled|published|archived
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();

            // Stats
            $table->unsignedSmallInteger('reading_time')->default(0);
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('share_count')->default(0);

            // Origin (written in the dashboard vs pushed in by an AI writer)
            $table->string('source', 30)->default('manual'); // manual|api|ai|import
            $table->string('external_id', 190)->nullable()->unique();
            $table->json('external_payload')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['is_featured', 'status']);
        });

        Schema::create('blog_post_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('blog_post_id');
            $table->unsignedBigInteger('blog_tag_id');
            $table->primary(['blog_post_id', 'blog_tag_id']);
            $table->index('blog_tag_id');
        });

        Schema::create('blog_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('path');
            $table->string('disk', 30)->default('public');
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->string('source', 30)->default('manual');
            $table->timestamps();
        });

        Schema::create('blog_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('token', 100)->unique();
            $table->json('abilities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 64)->nullable();
            $table->unsignedInteger('requests_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_api_tokens');
        Schema::dropIfExists('blog_media');
        Schema::dropIfExists('blog_post_tag');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('blog_categories');
    }
};
