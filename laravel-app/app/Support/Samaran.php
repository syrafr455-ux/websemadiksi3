<?php

namespace App\Support;

final class Samaran
{
    /**
     * "fauzan@student.unusa.ac.id" -> "fa****@student.unusa.ac.id".
     */
    public static function email(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return '-';
        }

        [$lokal, $domain] = explode('@', $email, 2);
        $terlihat = mb_strlen($lokal) > 2 ? 2 : 1;

        return mb_substr($lokal, 0, $terlihat).'****@'.$domain;
    }
}
