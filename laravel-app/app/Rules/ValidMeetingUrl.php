<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidMeetingUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value) || ! is_string($value)) {
            return;
        }

        $url = trim($value);

        // 1. Wajib HTTPS
        if (! str_starts_with(strtolower($url), 'https://')) {
            $fail('Tautan rapat harus menggunakan protokol aman (https://).');

            return;
        }

        // 2. Parse Host
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            $fail('Format tautan rapat tidak valid.');

            return;
        }

        $host = strtolower($host);
        $whitelist = config('kegiatan.whitelist_meeting_hosts', [
            'zoom.us',
            '*.zoom.us',
            'meet.google.com',
            'teams.microsoft.com',
            'teams.live.com',
        ]);

        $cocok = false;
        foreach ($whitelist as $pattern) {
            $pattern = strtolower($pattern);
            if (str_starts_with($pattern, '*.')) {
                $base = substr($pattern, 2);
                if ($host === $base || str_ends_with($host, '.'.$base)) {
                    $cocok = true;
                    break;
                }
            } elseif ($host === $pattern) {
                $cocok = true;
                break;
            }
        }

        if (! $cocok) {
            $fail('Tautan rapat harus berasal dari platform resmi yang didukung (Zoom, Google Meet, atau Microsoft Teams).');
        }
    }
}
