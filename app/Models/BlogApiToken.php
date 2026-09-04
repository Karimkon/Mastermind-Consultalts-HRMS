<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogApiToken extends Model
{
    protected $fillable = [
        'name', 'token', 'abilities', 'is_active',
        'last_used_at', 'last_used_ip', 'requests_count', 'created_by',
    ];

    protected $casts = [
        'abilities' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public static function generate(string $name, ?int $userId = null): self
    {
        return static::create([
            'name' => $name,
            'token' => 'bmb_' . Str::random(48),
            'abilities' => ['publish', 'update', 'upload'],
            'is_active' => true,
            'created_by' => $userId,
        ]);
    }

    public function markUsed(?string $ip = null): void
    {
        $this->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
            'requests_count' => $this->requests_count + 1,
        ])->saveQuietly();
    }

    public function getMaskedTokenAttribute(): string
    {
        return substr($this->token, 0, 10) . str_repeat('*', 20) . substr($this->token, -4);
    }
}
