<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
class Setting extends Model {
    protected $fillable = ['key','value','group','type','label','description'];

    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting_{$key}", 3600, fn() =>
            static::where('key', $key)->value('value') ?? $default
        );
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting_{$key}");
    }
}
