<?php

namespace Tests\Concerns;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite menganggap nama kolom bertanda kutip ganda yang tidak ada sebagai teks literal, jadi kolom salah
 * lolos di tes tetapi gagal di PostgreSQL (SQLSTATE[42703]). Trait ini mencatat setiap query yang dijalankan
 * lalu mencocokkan nama kolomnya dengan skema asli.
 *
 * Pemakaian: panggil mulaiAuditKolom() sebelum request, lalu assertTidakAdaKolomTidakDikenal() sesudahnya.
 * Pemeriksaan bersifat heuristik atas SQL yang dihasilkan query builder: ia memeriksa kolom ber-awalan tabel
 * ("t"."c") dan, untuk query dengan satu tabel, setiap kolom tanpa awalan.
 */
trait MengauditKolomSql
{
    /** @var list<string> */
    private array $kueriTercatat = [];

    protected function mulaiAuditKolom(): void
    {
        $this->kueriTercatat = [];
        DB::listen(function (QueryExecuted $kueri) {
            $this->kueriTercatat[] = $kueri->sql;
        });
    }

    /** @return list<string> */
    protected function kolomTidakDikenal(): array
    {
        $temuan = [];

        foreach (array_unique($this->kueriTercatat) as $sqlAsli) {
            // Teks literal bertanda kutip tunggal (mis. jalur JSON '$."kunci"') bukan nama kolom.
            $sql = preg_replace("/'(?:[^']|'')*'/", "''", $sqlAsli);
            // Query bersarang dan alias terlalu beragam untuk diurai penuh; yang bukan select/insert/update/delete dilewati.
            if (! preg_match('/^\s*(select|insert|update|delete)\b/i', $sql)) {
                continue;
            }

            preg_match_all('/\b(?:from|join|into|update)\s+"([A-Za-z0-9_]+)"(?:\s+as\s+"([A-Za-z0-9_]+)")?/i', $sql, $m, PREG_SET_ORDER);
            $tabelPerAlias = [];
            foreach ($m as $baris) {
                if (Schema::hasTable($baris[1])) {
                    $tabelPerAlias[$baris[2] ?? $baris[1]] = $baris[1];
                    $tabelPerAlias[$baris[1]] = $baris[1];
                }
            }
            if ($tabelPerAlias === []) {
                continue;
            }

            preg_match_all('/\bas\s+"([A-Za-z0-9_]+)"/i', $sql, $al);
            $aliasKolom = $al[1];
            $tabelUnik = array_unique(array_values($tabelPerAlias));

            // Kolom ber-awalan: "tabel"."kolom"
            preg_match_all('/"([A-Za-z0-9_]+)"\."([A-Za-z0-9_]+)"/', $sql, $q, PREG_SET_ORDER);
            foreach ($q as $baris) {
                $tabel = $tabelPerAlias[$baris[1]] ?? null;
                if ($tabel && ! Schema::hasColumn($tabel, $baris[2])) {
                    $temuan[] = "{$tabel}.{$baris[2]} tidak ada di skema :: {$sqlAsli}";
                }
            }

            // Kolom tanpa awalan hanya diperiksa bila query memakai satu tabel.
            if (count($tabelUnik) === 1) {
                $tabel = $tabelUnik[0];
                $tanpaQualified = preg_replace('/"[A-Za-z0-9_]+"\."[A-Za-z0-9_]+"/', '', $sql);
                preg_match_all('/"([A-Za-z0-9_]+)"/', $tanpaQualified, $u);
                foreach (array_unique($u[1]) as $nama) {
                    if ($nama === $tabel || isset($tabelPerAlias[$nama]) || in_array($nama, $aliasKolom, true) || $nama === 'aggregate') {
                        continue;
                    }
                    if (! Schema::hasColumn($tabel, $nama)) {
                        $temuan[] = "{$tabel}.{$nama} tidak ada di skema :: {$sqlAsli}";
                    }
                }
            }
        }

        return array_values(array_unique($temuan));
    }

    protected function assertTidakAdaKolomTidakDikenal(string $konteks = ''): void
    {
        $this->assertSame([], $this->kolomTidakDikenal(), 'Query memakai kolom yang tidak ada di skema (gagal di PostgreSQL). '.$konteks);
    }
}
