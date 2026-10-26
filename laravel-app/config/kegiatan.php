<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Daftar Putih Host Tautan Rapat Daring
    |--------------------------------------------------------------------------
    |
    | Hanya host dalam daftar ini yang diizinkan untuk tautan rapat online/hybrid.
    | Tautan harus menggunakan protokol HTTPS.
    |
    */
    'whitelist_meeting_hosts' => [
        'zoom.us',
        '*.zoom.us',
        'meet.google.com',
        'teams.microsoft.com',
        'teams.live.com',
    ],
];
