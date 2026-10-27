// Logika murni editor templat sertifikat. Aturan penjepitan sama dengan App\Support\Sertifikat\TataLetak
// di server; server tetap memvalidasi ulang, jadi berkas ini hanya menjaga tampilan editor jujur.

export const HALAMAN_MM = { landscape: [297, 210], portrait: [210, 297] };
export const QR_MIN_MM = 18;
export const QR_MAKS_MM = 60;
export const MARGIN_QR_MM = 5;
export const WAJIB = ['nama', 'qr', 'nomor_seri'];
export const LANGKAH_PANAH = 0.5;
export const LANGKAH_PANAH_SHIFT = 2;
export const AMBANG_SNAP = 1.2;

export const LABEL_ELEMEN = {
    judul: 'Judul',
    pengantar: 'Pengantar',
    nama: 'Nama penerima',
    asal_institusi: 'Asal institusi',
    jenis_predikat: 'Jenis dan predikat',
    kegiatan: 'Keterangan kegiatan',
    tanggal: 'Tanggal terbit',
    nomor_seri: 'Nomor seri',
    qr: 'Kode QR',
    penandatangan_1: 'Penandatangan 1',
    penandatangan_2: 'Penandatangan 2',
    penandatangan_3: 'Penandatangan 3',
    penandatangan_4: 'Penandatangan 4',
};

const batas = (nilai, min, maks) => Math.max(min, Math.min(maks, nilai));
const bulat = (n, digit = 3) => Math.round(n * 10 ** digit) / 10 ** digit;

/** Persen halaman ke piksel kanvas. */
export function persenKePiksel(persen, ukuranPx) {
    return (persen / 100) * ukuranPx;
}

/** Piksel kanvas ke persen halaman (0 bila kanvas belum berukuran). */
export function pikselKePersen(px, ukuranPx) {
    return ukuranPx > 0 ? (px / ukuranPx) * 100 : 0;
}

/** Jepit satu elemen ke dalam halaman. QR: persegi, 18-60 mm, margin 5 mm dari tepi halaman. */
export function jepitElemen(el, kunci, orientasi) {
    const [w, h] = HALAMAN_MM[orientasi] ?? HALAMAN_MM.landscape;
    const salinan = { ...el, x: Number(el.x), y: Number(el.y), lebar: Number(el.lebar), tinggi: Number(el.tinggi) };

    if (kunci === 'qr') {
        const sisi = batas((salinan.lebar / 100) * w, QR_MIN_MM, QR_MAKS_MM);
        const xMm = batas((salinan.x / 100) * w, MARGIN_QR_MM, w - MARGIN_QR_MM - sisi);
        const yMm = batas((salinan.y / 100) * h, MARGIN_QR_MM, h - MARGIN_QR_MM - sisi);
        return {
            ...salinan,
            lebar: bulat((sisi / w) * 100),
            tinggi: bulat((sisi / h) * 100),
            x: bulat((xMm / w) * 100),
            y: bulat((yMm / h) * 100),
            tampil: true,
        };
    }

    const lebar = batas(salinan.lebar, 1, 100);
    const tinggi = batas(salinan.tinggi, 1, 100);
    return {
        ...salinan,
        lebar,
        tinggi,
        x: bulat(batas(salinan.x, 0, 100 - lebar)),
        y: bulat(batas(salinan.y, 0, 100 - tinggi)),
        tampil: WAJIB.includes(kunci) ? true : salinan.tampil,
    };
}

/** Geser elemen sebesar delta persen, lalu jepit. */
export function geserElemen(el, kunci, orientasi, dx, dy) {
    return jepitElemen({ ...el, x: el.x + dx, y: el.y + dy }, kunci, orientasi);
}

/** Ubah ukuran: QR menjaga bentuk persegi (satu sisi dalam mm), elemen lain mengubah lebar dan tinggi. */
export function ubahUkuran(el, kunci, orientasi, lebarBaruPersen, tinggiBaruPersen) {
    if (kunci === 'qr') {
        const [w, h] = HALAMAN_MM[orientasi];
        const sisiMm = Math.max((lebarBaruPersen / 100) * w, (tinggiBaruPersen / 100) * h);
        return jepitElemen({ ...el, lebar: (sisiMm / w) * 100, tinggi: (sisiMm / h) * 100 }, kunci, orientasi);
    }
    return jepitElemen({ ...el, lebar: lebarBaruPersen, tinggi: tinggiBaruPersen }, kunci, orientasi);
}

/** Sisi QR dalam mm (untuk label dan peringatan). */
export function sisiQrMm(el, orientasi) {
    return (el.lebar / 100) * HALAMAN_MM[orientasi][0];
}

/** Snap tengah halaman secara horizontal: bila pusat elemen dekat 50%, geser agar tepat di tengah. */
export function snapTengah(el, aktif = true, ambang = AMBANG_SNAP) {
    const pusat = el.x + el.lebar / 2;
    if (!aktif || Math.abs(pusat - 50) > ambang) return { x: el.x, garis: false };
    return { x: bulat(50 - el.lebar / 2), garis: true };
}

/** Apakah dua kotak (persen) saling menutupi. */
export function tumpangTindih(a, b) {
    return a.x < b.x + b.lebar && b.x < a.x + a.lebar && a.y < b.y + b.tinggi && b.y < a.y + a.tinggi;
}

/** Kunci elemen tampil yang menutupi QR (hanya peringatan; tidak memblokir). */
export function menutupiQr(layout) {
    const qr = layout.qr;
    if (!qr) return [];
    return Object.entries(layout)
        .filter(([kunci, el]) => kunci !== 'qr' && el.tampil && tumpangTindih(el, qr))
        .map(([kunci]) => kunci);
}

/** Isi penampung {nama} dst. dengan data contoh; penampung tak dikenal dibiarkan apa adanya. */
export function isiPenampung(teks, data) {
    return String(teks ?? '').replace(/\{([a-z_]+)\}/g, (utuh, nama) => (nama in data ? String(data[nama]) : utuh));
}

/** Penampung yang tidak dikenal dalam sebuah teks (untuk peringatan dini di editor). */
export function penampungAsing(teks, dikenal) {
    return [...String(teks ?? '').matchAll(/\{([^{}]*)\}/g)].map((m) => m[1]).filter((nama) => !dikenal.includes(nama));
}

export const DATA_CONTOH = {
    nama: 'Nama Peserta Contoh',
    asal_institusi: 'Universitas Nahdlatul Ulama Surabaya',
    jenis: 'Peserta',
    predikat: 'Juara 1',
    kegiatan: 'Nama Kegiatan Contoh',
    tanggal: '1 Januari 2026',
    nomor: 'CERT-CONTOH-0000-0000',
};
