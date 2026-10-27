<?php

use Tests\Support\PenjagaDatabaseUji;

// Bootstrap khusus phpunit.pgsql.xml: berhenti sebelum aplikasi atau migrasi apa pun berjalan bila database tidak aman.
require __DIR__.'/../vendor/autoload.php';

PenjagaDatabaseUji::periksa(
    (string) getenv('DB_CONNECTION'),
    (string) getenv('DB_DATABASE'),
    getenv('DB_DATABASE_DEV') ?: null,
);
