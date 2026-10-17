<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Akun Demo DatabaseSeeder
    |--------------------------------------------------------------------------
    |
    | Dipakai `php artisan db:seed` (hanya non-production). Bila kosong, akun
    | demo BARU mendapat password acak yang ditampilkan sekali di terminal.
    | Akun yang sudah ada hanya diganti passwordnya bila nilai ini diisi.
    |
    */

    'admin_password' => env('SEED_ADMIN_PASSWORD'),

    'mahasiswa_password' => env('SEED_MAHASISWA_PASSWORD'),

];
