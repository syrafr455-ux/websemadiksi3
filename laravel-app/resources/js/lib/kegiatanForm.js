// Pemetaan data kegiatan (props dari server) <-> state form admin.
// Nama field form = nama kolom model App\Models\Kegiatan, kecuali berkas unggahan (panduan_file).

import { keInputWib } from './waktuWib';

// Sama dengan Kegiatan::KATEGORI, Kegiatan::SASARAN, Kegiatan::METODE, Kegiatan::LOKASI_TYPES.
export const KATEGORI_OPTIONS = [
    { value: 'seminar', label: 'Seminar' },
    { value: 'webinar', label: 'Webinar' },
    { value: 'workshop', label: 'Workshop' },
    { value: 'pelatihan', label: 'Pelatihan' },
    { value: 'lomba', label: 'Lomba / Kompetisi' },
    { value: 'pengabdian_masyarakat', label: 'Pengabdian Masyarakat' },
    { value: 'lainnya', label: 'Lainnya' },
];

export const SASARAN_OPTIONS = [
    { value: 'umum', label: 'Umum (terbuka untuk semua peserta)' },
    { value: 'internal_unusa', label: 'Internal UNUSA (mahasiswa KIP-K / email UNUSA)' },
    { value: 'kipk_only', label: 'Khusus Mahasiswa KIP-K terverifikasi' },
];

export const METODE_OPTIONS = [
    { value: 'offline', label: 'Offline (tatap muka)' },
    { value: 'online', label: 'Online' },
    { value: 'hybrid', label: 'Hybrid' },
];

export const LOKASI_TYPE_OPTIONS = [
    { id: 'unusa_campus', label: 'Kampus UNUSA', icon: 'school' },
    { id: 'external', label: 'Venue Eksternal', icon: 'location_on' },
    { id: 'online', label: 'Online (Zoom / Meet)', icon: 'videocam' },
];

export const DEFAULT_SEAT_LAYOUT = {
    rows: ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
    cols_per_row: 10,
    vip_rows: ['A', 'B'],
    panitia_rows: [],
};

const DEFAULT_FASILITAS = {
    lokasi_kampus: 'Kampus B (Jemursari)',
    lokasi_gedung: 'Tower UNUSA',
    lokasi_ruangan: 'Auditorium Lt. 9',
};

export const labelDari = (options, value) => options.find((o) => o.value === value)?.label || value || '-';

export function narasumberKosong() {
    return { nama: '', gelar: '', instansi: '' };
}

/** Teks narasumber untuk tampilan, mis. "Dr. Siti (UNUSA), Budi". */
export function formatNarasumber(narasumber) {
    if (!Array.isArray(narasumber)) return '';

    return narasumber
        .filter((n) => n && n.nama)
        .map((n) => [n.gelar, n.nama].filter(Boolean).join(' ') + (n.instansi ? ` (${n.instansi})` : ''))
        .join(', ');
}

/** State form untuk kegiatan baru. */
export function kegiatanKosong() {
    return {
        nama_kegiatan: '',
        deskripsi: '',
        kategori: 'seminar',
        sasaran_peserta: 'umum',
        metode_pelaksanaan: 'offline',
        tanggal_mulai: '',
        tanggal_selesai: '',
        registration_deadline: '',
        submission_deadline: '',
        lokasi_type: 'unusa_campus',
        ...DEFAULT_FASILITAS,
        lokasi_nama: 'Tower UNUSA - Kampus B',
        lokasi_detail: 'Auditorium Lantai 9, Jl. Raya Jemursari No. 57 Surabaya',
        biaya: 0,
        kuota: 100,
        poin_xp: 10,
        narasumber: [],
        is_seat_booking_active: true,
        is_submission_active: false,
        submission_instructions: '',
        status: 'published',
        seat_layout_config: { ...DEFAULT_SEAT_LAYOUT },
        panduan_file: null,
    };
}

/**
 * State form edit dari data kegiatan yang dikirim server. Membuka form lalu menyimpan tanpa perubahan
 * mengirim kembali nilai yang sama persis (tanggal sebagai jam WIB, dikonversi ke UTC oleh controller).
 */
export function kegiatanKeForm(item) {
    const layout = item.seat_layout_config || {};

    return {
        nama_kegiatan: item.nama_kegiatan ?? '',
        deskripsi: item.deskripsi ?? '',
        kategori: item.kategori ?? 'seminar',
        sasaran_peserta: item.sasaran_peserta ?? 'umum',
        metode_pelaksanaan: item.metode_pelaksanaan ?? 'offline',
        tanggal_mulai: keInputWib(item.tanggal_mulai),
        tanggal_selesai: keInputWib(item.tanggal_selesai),
        registration_deadline: keInputWib(item.registration_deadline),
        submission_deadline: keInputWib(item.submission_deadline),
        lokasi_type: item.lokasi_type ?? 'unusa_campus',
        // Pilihan fasilitas tersimpan (hanya untuk lokasi kampus); kegiatan lama tanpa pilihan memakai default.
        lokasi_kampus: item.lokasi_kampus || DEFAULT_FASILITAS.lokasi_kampus,
        lokasi_gedung: item.lokasi_gedung || DEFAULT_FASILITAS.lokasi_gedung,
        lokasi_ruangan: item.lokasi_ruangan || DEFAULT_FASILITAS.lokasi_ruangan,
        lokasi_nama: item.lokasi_nama ?? '',
        lokasi_detail: item.lokasi_detail ?? '',
        biaya: item.biaya ?? 0,
        kuota: item.kuota ?? 1,
        poin_xp: item.poin_xp ?? 0,
        narasumber: Array.isArray(item.narasumber) ? item.narasumber.map((n) => ({ ...n })) : [],
        is_seat_booking_active: Boolean(item.is_seat_booking_active),
        is_submission_active: Boolean(item.is_submission_active),
        submission_instructions: item.submission_instructions ?? '',
        status: item.status ?? 'published',
        seat_layout_config: {
            rows: layout.rows ?? DEFAULT_SEAT_LAYOUT.rows,
            cols_per_row: layout.cols_per_row ?? DEFAULT_SEAT_LAYOUT.cols_per_row,
            vip_rows: layout.vip_rows ?? DEFAULT_SEAT_LAYOUT.vip_rows,
            panitia_rows: layout.panitia_rows ?? DEFAULT_SEAT_LAYOUT.panitia_rows,
        },
        panduan_file: null,
    };
}

/**
 * Data yang dikirim ke server. Bila ada berkas, Inertia mengirim FormData yang membuang array kosong,
 * sehingga "hapus semua narasumber" atau "tanpa baris VIP" tidak sampai ke server. Array kosong
 * dikirim sebagai null (string kosong di FormData) dan server membacanya sebagai daftar kosong.
 */
export function kegiatanKePayload(data) {
    const kosongJadiNull = (list) => (Array.isArray(list) && list.length === 0 ? null : list);
    const layout = data.seat_layout_config || {};

    return {
        ...data,
        narasumber: kosongJadiNull(data.narasumber),
        seat_layout_config: {
            ...layout,
            vip_rows: kosongJadiNull(layout.vip_rows),
            panitia_rows: kosongJadiNull(layout.panitia_rows),
        },
    };
}
