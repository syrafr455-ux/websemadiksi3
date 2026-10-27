<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Penjaga konfigurasi tes PostgreSQL (phpunit.pgsql.xml). RefreshDatabase menjalankan migrate:fresh, jadi tes
 * menolak berjalan kecuali koneksinya pgsql ke database yang namanya berakhiran _test dan bukan database pengembangan.
 */
final class PenjagaDatabaseUji
{
    /** @throws RuntimeException */
    public static function periksa(string $driver, string $namaDatabase, ?string $databasePengembangan = null): void
    {
        if ($driver !== 'pgsql') {
            throw new RuntimeException("phpunit.pgsql.xml hanya untuk koneksi pgsql, bukan '{$driver}'.");
        }
        if (! str_ends_with($namaDatabase, '_test')) {
            throw new RuntimeException("Ditolak: nama database uji '{$namaDatabase}' harus berakhiran _test.");
        }
        if ($databasePengembangan !== null && $databasePengembangan !== '' && $namaDatabase === $databasePengembangan) {
            throw new RuntimeException("Ditolak: database uji '{$namaDatabase}' sama dengan database pengembangan.");
        }
    }
}
