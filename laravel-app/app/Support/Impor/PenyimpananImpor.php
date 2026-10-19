<?php

namespace App\Support\Impor;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * File impor yang sedang dipratinjau disimpan sementara TERENKRIPSI di disk "local"
 * (bisa berisi nomor pendaftaran KIP), lalu dihapus setelah disimpan atau dibatalkan.
 */
final class PenyimpananImpor
{
    public static function simpan(string $jenis, string $isi, string $namaFile): void
    {
        self::hapus($jenis);

        $token = (string) Str::uuid();
        Storage::disk('local')->put("impor/{$token}", Crypt::encryptString($isi));
        session()->put("impor.{$jenis}", ['token' => $token, 'nama_file' => $namaFile]);
    }

    /**
     * @return array{isi: string, nama_file: string}|null
     */
    public static function ambil(string $jenis): ?array
    {
        $meta = session("impor.{$jenis}");
        if (! is_array($meta) || ! Storage::disk('local')->exists("impor/{$meta['token']}")) {
            return null;
        }

        return [
            'isi' => Crypt::decryptString(Storage::disk('local')->get("impor/{$meta['token']}")),
            'nama_file' => $meta['nama_file'],
        ];
    }

    public static function hapus(string $jenis): void
    {
        $meta = session("impor.{$jenis}");
        if (is_array($meta)) {
            Storage::disk('local')->delete("impor/{$meta['token']}");
        }
        session()->forget("impor.{$jenis}");
    }
}
