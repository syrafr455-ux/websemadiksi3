// Jalankan dari laravel-app/: node --test tests/js
// Memakai runner bawaan Node tanpa paket tambahan. Hanya menguji fungsi murni di resources/js/lib
// dan satu pemeriksaan statis atas Index.jsx (pengendali event harus terdefinisi).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

import { kegiatanKeForm, kegiatanKePayload, kegiatanKosong } from '../../resources/js/lib/kegiatanForm.js';

const dasar = {
    id: 29,
    nama_kegiatan: 'Lomba Essay',
    kategori: 'lomba',
    sasaran_peserta: 'umum',
    metode_pelaksanaan: 'online',
    tanggal_mulai: '2026-11-20T02:00:00.000000Z',
    tanggal_selesai: '2026-11-20T05:00:00.000000Z',
    lokasi_type: 'online',
    lokasi_nama: 'Daring (Zoom)',
    biaya: '0.00',
    kuota: 100,
    status: 'draft',
};

// Bentuk payload daftar kegiatan dari KegiatanController::barisDaftar() untuk empat kasus.
const kasus = {
    'draft daring': {
        ...dasar,
        platform_daring: 'zoom', join_button_mode: 'manual', menit_buka_gabung: 45,
        batas_tutup_gabung: null, izin_tautan_presensi_akhir: true, wajib_tangkapan_layar: true,
        tautan_rapat_tersimpan: true, meeting_id_tersimpan: true, passcode_rapat_tersimpan: true,
        seat_layout_config: null, narasumber: null,
    },
    'daring terbit': {
        ...dasar, status: 'published',
        platform_daring: 'meet', join_button_mode: 'otomatis', menit_buka_gabung: 15,
        batas_tutup_gabung: '2026-11-20T06:00:00.000000Z', izin_tautan_presensi_akhir: false, wajib_tangkapan_layar: false,
        tautan_rapat_tersimpan: true, meeting_id_tersimpan: false, passcode_rapat_tersimpan: false,
        seat_layout_config: null, narasumber: [{ nama: 'Dr. Budi' }],
    },
    hybrid: {
        ...dasar, metode_pelaksanaan: 'hybrid', lokasi_type: 'unusa_campus', status: 'published',
        platform_daring: 'teams', join_button_mode: 'otomatis', menit_buka_gabung: 15,
        batas_tutup_gabung: null, izin_tautan_presensi_akhir: false, wajib_tangkapan_layar: false,
        tautan_rapat_tersimpan: true, meeting_id_tersimpan: false, passcode_rapat_tersimpan: false,
        is_seat_booking_active: true,
        seat_layout_config: { rows: ['A', 'B'], cols_per_row: 6, vip_rows: null, panitia_rows: [] },
    },
    'offline lama tanpa kolom daring': {
        ...dasar, metode_pelaksanaan: 'offline', lokasi_type: 'unusa_campus', status: 'published',
        // Baris lama: kolom daring tidak ada sama sekali di objek.
    },
};

for (const [nama, item] of Object.entries(kasus)) {
    test(`kegiatanKeForm aman untuk kasus: ${nama}`, () => {
        const form = kegiatanKeForm(item);

        for (const kunci of Object.keys(kegiatanKosong())) {
            assert.ok(kunci in form, `kunci '${kunci}' hilang`);
            assert.notEqual(form[kunci], undefined, `kunci '${kunci}' undefined`);
        }
        assert.equal(typeof form.tautan_rapat, 'string');
        assert.equal(typeof form.passcode_rapat, 'string');
        assert.equal(typeof form.meeting_id, 'string');
        assert.equal(typeof form.izin_tautan_presensi_akhir, 'boolean');
        assert.equal(typeof form.wajib_tangkapan_layar, 'boolean');
        assert.ok(Array.isArray(form.narasumber));
        assert.ok(Array.isArray(form.seat_layout_config.vip_rows), 'vip_rows harus array agar .join() aman');
        assert.ok(Array.isArray(form.seat_layout_config.rows));
        assert.equal(form.tanggal_mulai, '2026-11-20T09:00', 'jam form dalam WIB');
    });
}

test('kegiatanKePayload tidak mengirim rahasia rapat yang dibiarkan kosong', () => {
    const form = kegiatanKeForm(kasus['draft daring']);
    const payload = kegiatanKePayload(form);

    for (const kunci of ['tautan_rapat', 'meeting_id', 'passcode_rapat']) {
        assert.ok(!(kunci in payload), `'${kunci}' kosong tidak boleh ikut terkirim`);
    }
    assert.equal(payload.join_button_mode, 'manual');
    assert.equal(payload.menit_buka_gabung, 45);
});

test('kegiatanKePayload mengirim rahasia rapat yang diisi', () => {
    const payload = kegiatanKePayload({
        ...kegiatanKeForm(kasus['draft daring']),
        tautan_rapat: 'https://zoom.us/j/123',
        passcode_rapat: 'sandi',
    });

    assert.equal(payload.tautan_rapat, 'https://zoom.us/j/123');
    assert.equal(payload.passcode_rapat, 'sandi');
    assert.ok(!('meeting_id' in payload));
});

const BERKAS_DIPERIKSA = [
    'resources/js/Pages/Admin/Kegiatan/Index.jsx',
    'resources/js/Pages/Admin/Kegiatan/Show.jsx',
    'resources/js/Pages/Admin/Kegiatan/Sertifikat.jsx',
    'resources/js/Components/Admin/AksiPersetujuanKegiatan.jsx',
    'resources/js/Components/Admin/EditorTemplatSertifikat.jsx',
];

for (const relatif of BERKAS_DIPERIKSA) {
    test(`${relatif}: setiap pengendali event berbentuk identifier sudah terdeklarasi`, () => {
        const berkas = join(dirname(fileURLToPath(import.meta.url)), '../..', relatif);
        const kode = readFileSync(berkas, 'utf8');

        const dipakai = new Set([...kode.matchAll(/\bon[A-Z]\w*=\{(\w+)\}/g)].map((m) => m[1]));

        for (const nama of dipakai) {
            const terdeklarasi = new RegExp(
                String.raw`(?:const|let|function)\s+${nama}\b|import[^;]*\b${nama}\b|\(\s*\{[^}]*\b${nama}\b[^}]*\}\s*\)`,
            ).test(kode);
            assert.ok(terdeklarasi, `pengendali '${nama}' dipakai tetapi tidak terdeklarasi (ReferenceError saat render)`);
        }
    });
}
