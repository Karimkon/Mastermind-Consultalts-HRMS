<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogTag extends Model
{
    protected $fillable = ['name', 'slug'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function posts()
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag');
    }

    public function getUrlAttribute(): string
    {
        return route('blog.tag', $this->slug);
    }

    /** Find or create a tag from a free-text label (used by the publishing API). */
    public static function fromName(string $name): ?self
    {
        $name = trim($name);
        $slug = Str::slug($name);

        if ($slug === '') {
            return null;
        }

        return static::firstOrCreate(['slug' => $slug], ['name' => $name]);
    }
}
