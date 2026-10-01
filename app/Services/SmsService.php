<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends one text message, or says plainly that it did not.
 *
 * send() returns true only when a message actually left the building. On the
 * default "log" driver it returns false, because nothing was sent. Callers
 * record what they are told, so an applicant trail never shows an SMS that
 * does not exist.
 */
class SmsService
{
    /** Is a real gateway configured, or are we still writing to the log? */
    public function isLive(): bool
    {
        return config('sms.driver') === 'http' && filled(config('sms.http.url'));
    }

    /**
     * Put a Ugandan number into the form a gateway accepts.
     *
     * 0772 123456 -> 256772123456, +256 772 123456 -> 256772123456.
     * Returns null when there is nothing usable, which is a real case: plenty
     * of applicants type a phone number wrong.
     */
    public function normalise(?string $phone): ?string
    {
        if (! $phone) return null;

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') return null;

        $cc = (string) config('sms.country_code', '256');

        if (str_starts_with($digits, '0'))  $digits = $cc . substr($digits, 1);
        if (str_starts_with($digits, '00')) $digits = substr($digits, 2);

        // A local number is 9 digits after the code. Anything shorter than
        // that is a typo, not a phone number.
        if (strlen($digits) < 9) return null;
        if (! str_starts_with($digits, $cc) && strlen($digits) === 9) $digits = $cc . $digits;

        return $digits;
    }

    public function send(?string $phone, string $message): bool
    {
        $to = $this->normalise($phone);

        if (! $to) {
            Log::warning('SMS not sent: unusable number', ['given' => $phone]);
            return false;
        }

        if (! $this->isLive()) {
            // Not an error. This is the configured behaviour until a gateway
            // account exists, and the full text is logged so it can be checked.
            Log::info('SMS (not sent - no gateway configured)', ['to' => $to, 'message' => $message]);
            return false;
        }

        $f      = config('sms.http.fields');
        $params = [
            $f['username'] => config('sms.http.username'),
            $f['password'] => config('sms.http.password'),
            $f['to']       => $to,
            $f['message']  => $message,
            $f['from']     => config('sms.from'),
        ];

        if ($extra = config('sms.http.extra')) {
            parse_str($extra, $parsed);
            $params = array_merge($params, $parsed);
        }

        try {
            $request = Http::timeout((int) config('sms.http.timeout', 10));
            $url     = config('sms.http.url');

            $response = strtoupper((string) config('sms.http.method', 'POST')) === 'GET'
                ? $request->get($url, $params)
                : $request->asForm()->post($url, $params);

            if (! $response->successful()) {
                Log::error('SMS gateway refused', [
                    'to' => $to, 'status' => $response->status(),
                    'body' => substr($response->body(), 0, 300),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            // A gateway being unreachable must never break whatever the user
            // was actually doing.
            Log::error('SMS gateway unreachable', ['to' => $to, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
