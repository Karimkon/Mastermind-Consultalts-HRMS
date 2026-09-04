<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * IndexNow lets us tell Bing, Yandex, Naver and Seznam about a new or updated
 * URL the moment it is published instead of waiting for a crawl. The key is a
 * random string that must also be readable at https://host/{key}.txt — that is
 * served by the /{key}.txt route, so nothing has to be written into the docroot.
 */
class IndexNowService
{
    protected const KEY_FILE = 'indexnow.key';

    public function key(): string
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::KEY_FILE)) {
            $disk->put(self::KEY_FILE, Str::lower(Str::random(32)));
        }

        return trim((string) $disk->get(self::KEY_FILE));
    }

    public function keyLocation(): string
    {
        return rtrim((string) config('app.url'), '/') . '/' . $this->key() . '.txt';
    }

    /**
     * @param  array<int, string>  $urls
     */
    public function submit(array $urls): bool
    {
        $urls = array_values(array_filter(array_unique($urls)));

        if (empty($urls) || ! app()->environment('production')) {
            return false;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        try {
            $response = Http::timeout(10)->post('https://api.indexnow.org/indexnow', [
                'host' => $host,
                'key' => $this->key(),
                'keyLocation' => $this->keyLocation(),
                'urlList' => array_slice($urls, 0, 10000),
            ]);

            if (! $response->successful()) {
                Log::info('IndexNow rejected submission', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 300),
                ]);
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('IndexNow submission failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
