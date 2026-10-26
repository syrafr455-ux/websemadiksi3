<?php

namespace App\Http\Controllers;

use App\Enums\KegiatanStatus;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KegiatanGabung;
use App\Models\KegiatanRegistration;
use App\Support\Wib;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KegiatanGabungController extends Controller
{
    /**
     * Endpoint peserta bergabung ke ruang pertemuan daring kegiatan.
     * Mengembalikan tautan rapat terenkripsi, meeting ID, dan passcode HANYA setelah validasi sah.
     */
    public function gabung(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan masuk ke akun Anda terlebih dahulu.',
            ], 401);
        }

        if ($user->is_blocked) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda sedang dibekukan. Silakan hubungi admin.',
            ], 403);
        }

        $kegiatan = Kegiatan::findOrFail($id);

        if (! $kegiatan->isDaringOrHybrid()) {
            return response()->json([
                'success' => false,
                'message' => 'Kegiatan ini dilaksanakan secara tatap muka (luring), tidak tersedia ruang daring.',
            ], 422);
        }

        // Status kegiatan harus terbit atau berlangsung
        $allowedStatuses = [KegiatanStatus::Published, KegiatanStatus::Ongoing];
        if (! in_array($kegiatan->status, $allowedStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Ruang pertemuan daring belum tersedia karena kegiatan belum diterbitkan.',
            ], 422);
        }

        // Cari registrasi aktif & terkonfirmasi milik user
        $registration = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
            ->where(function ($q) use ($user) {
                $q->where('participant_user_id', $user->id)
                    ->orWhere('booker_user_id', $user->id);
                if ($user->is_unusa && $user->nim) {
                    $q->orWhere('participant_identity', $user->nim);
                }
            })
            ->confirmed()
            ->first();

        if (! $registration) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki pendaftaran atau tiket yang terkonfirmasi untuk kegiatan ini.',
            ], 403);
        }

        // Periksa jendela waktu tombol gabung
        if (! $kegiatan->isJoinWindowOpen()) {
            $status = $kegiatan->statusAksesGabung();
            $buka = $kegiatan->waktuBukaGabung();
            $bukaTeks = $buka ? Wib::dari($buka)->format('d M Y H:i').' WIB' : 'segera';

            $pesan = $status === 'belum_dibuka'
                ? "Akses ruang pertemuan daring belum dibuka. Akses akan dibuka pada {$bukaTeks}."
                : 'Akses ruang pertemuan daring telah ditutup oleh penyelenggara.';

            return response()->json([
                'success' => false,
                'status_akses' => $status,
                'message' => $pesan,
            ], 422);
        }

        if (empty($kegiatan->tautan_rapat)) {
            return response()->json([
                'success' => false,
                'message' => 'Tautan pertemuan daring belum dikonfigurasi oleh panitia.',
            ], 422);
        }

        // Catat atau perbarui catatan kegiatan_gabung
        $gabung = KegiatanGabung::firstOrNew([
            'kegiatan_id' => $kegiatan->id,
            'registration_id' => $registration->id,
        ]);

        $isFirst = ! $gabung->exists;
        if ($isFirst) {
            $gabung->user_id = $user->id;
            $gabung->pertama_pada = now();
            $gabung->jumlah = 1;
        } else {
            $gabung->jumlah = ($gabung->jumlah ?? 0) + 1;
        }
        $gabung->terakhir_pada = now();
        $gabung->save();

        AuditLog::record(
            $user->id,
            'kegiatan_gabung_daring',
            "Peserta {$user->name} bergabung ke ruang daring kegiatan '{$kegiatan->nama_kegiatan}' (ID: {$kegiatan->id})",
            $kegiatan,
            [
                'registration_id' => $registration->id,
                'platform' => $kegiatan->platform_daring,
                'pertama' => $isFirst,
                'jumlah_gabung' => $gabung->jumlah,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengakses ruang pertemuan daring.',
            'join_url' => $kegiatan->tautan_rapat,
            'meeting_id' => $kegiatan->meeting_id,
            'passcode' => $kegiatan->passcode_rapat,
            'platform' => $kegiatan->platform_daring,
            'pertama_pada' => $gabung->pertama_pada?->toIso8601String(),
            'jumlah' => $gabung->jumlah,
        ]);
    }
}
