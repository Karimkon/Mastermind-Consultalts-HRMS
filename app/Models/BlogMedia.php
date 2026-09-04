<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogMedia extends Model
{
    protected $table = 'blog_media';

    protected $fillable = [
        'user_id', 'path', 'disk', 'original_name', 'mime',
        'size', 'width', 'height', 'alt', 'title', 'source',
    ];

    public function getUrlAttribute(): string
    {
        return asset('storage/' . ltrim($this->path, '/'));
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = (float) $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }
}
