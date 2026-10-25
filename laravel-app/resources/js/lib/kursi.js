// Label & gaya bersama untuk peta kursi (admin dan peserta) serta status jendela booking.
import { formatWib } from './waktuWib';

// Sama dengan Kegiatan::BOOKING_* di backend.
export const STATUS_BOOKING = {
    belum_dibuka: { label: 'Belum dibuka', kelas: 'bg-sky-500/15 text-sky-300 border-sky-500/30', ikon: 'schedule' },
    dibuka: { label: 'Dibuka', kelas: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30', ikon: 'event_available' },
    ditutup: { label: 'Ditutup', kelas: 'bg-slate-700/60 text-slate-300 border-slate-600', ikon: 'event_busy' },
    dibekukan: { label: 'Dibekukan', kelas: 'bg-amber-500/15 text-amber-300 border-amber-500/30', ikon: 'ac_unit' },
};

/** Kalimat penjelas status booking dalam WIB. */
export function keteranganBooking(status, bukaIso, tutupIso) {
    switch (status) {
        case 'belum_dibuka':
            return bukaIso ? `Pemilihan kursi dibuka ${formatWib(bukaIso)} WIB.` : 'Pemilihan kursi belum dibuka.';
        case 'dibuka':
            return tutupIso ? `Pemilihan kursi dibuka sampai ${formatWib(tutupIso)} WIB.` : 'Pemilihan kursi sedang dibuka.';
        case 'ditutup':
            return tutupIso ? `Pemilihan kursi ditutup sejak ${formatWib(tutupIso)} WIB.` : 'Pemilihan kursi sudah ditutup.';
        case 'dibekukan':
            return 'Pemilihan kursi dibekukan panitia. Kursi yang sudah dipilih tetap tersimpan.';
        default:
            return '';
    }
}

// Gaya kursi. Warna dipilih agar tetap terbedakan bersama ikon/garis (bukan hanya warna).
export const GAYA_KURSI = {
    tersedia: 'bg-emerald-500/20 border-emerald-500/60 text-emerald-200 hover:bg-emerald-500/40',
    vip: 'bg-amber-500/20 border-amber-500/60 text-amber-200 hover:bg-amber-500/40',
    panitia: 'bg-violet-500/20 border-violet-400/60 text-violet-200',
    terisi: 'bg-rose-500/30 border-rose-500/60 text-rose-100',
    diblokir: 'bg-slate-800 border-slate-600 text-slate-500 line-through',
    tidak_tersedia: 'bg-slate-800/80 border-slate-700 text-slate-500 cursor-not-allowed',
    milik: 'bg-sky-500/30 border-sky-400 text-sky-100',
    dipilih: 'bg-sky-500 border-sky-200 text-white ring-2 ring-sky-300',
    pilihUlang: 'bg-amber-400/20 border-amber-300 text-amber-100 border-dashed',
};

/** Daftar kode dari teks "A, B , c" -> ["A", "B", "C"]. */
export function daftarDariTeks(teks) {
    return [...new Set(String(teks || '').split(/[\s,;]+/).map((s) => s.trim().toUpperCase()).filter(Boolean))];
}

export const kodeKursi = (baris, kolom) => `${baris}${String(kolom).padStart(2, '0')}`;

/** Kapasitas kursi peserta (sama dengan DenahKursiService::kapasitas). */
export function kapasitasDenah({ rows = [], cols_per_row: kolom = 0, panitia_rows: panitia = [], vip_rows: vip = [], blocked_seats: blokir = [] }) {
    let jumlah = 0;
    rows.filter((b) => !panitia.includes(b)).forEach((baris) => {
        for (let k = 1; k <= kolom; k++) {
            if (!blokir.includes(kodeKursi(baris, k))) jumlah++;
        }
    });
    return jumlah;
}
