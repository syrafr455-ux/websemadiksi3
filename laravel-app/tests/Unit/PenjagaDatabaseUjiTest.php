<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\PenjagaDatabaseUji;

class PenjagaDatabaseUjiTest extends TestCase
{
    public function test_menerima_database_pgsql_berakhiran_test(): void
    {
        PenjagaDatabaseUji::periksa('pgsql', 'semadiksi_test', 'semadiksi');
        $this->addToAssertionCount(1);
    }

    public function test_menolak_nama_tanpa_akhiran_test_driver_lain_dan_database_pengembangan(): void
    {
        foreach ([['pgsql', 'semadiksi', null], ['pgsql', 'semadiksi_test_x', null], ['sqlite', 'semadiksi_test', null], ['pgsql', '', null], ['pgsql', 'prod_test', 'prod_test']] as [$driver, $nama, $dev]) {
            try {
                PenjagaDatabaseUji::periksa($driver, $nama, $dev);
                $this->fail("Seharusnya ditolak: {$driver}/{$nama}");
            } catch (RuntimeException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function test_file_pgsql_memakai_bootstrap_penjaga_dan_database_bernama_test(): void
    {
        $xml = file_get_contents(__DIR__.'/../../phpunit.pgsql.xml');

        $this->assertStringContainsString('bootstrap="tests/bootstrap-pgsql.php"', $xml);
        $this->assertMatchesRegularExpression('/DB_DATABASE" value="[a-z_]+_test"/', $xml);
        $this->assertStringContainsString('DB_CONNECTION" value="pgsql"', $xml);
        $this->assertStringNotContainsString('PASSWORD', $xml, 'Kredensial tidak boleh ditulis di berkas konfigurasi.');
    }
}
