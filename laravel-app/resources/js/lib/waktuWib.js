// Aturan waktu tunggal (sama dengan App\Support\Wib di backend):
// server mengirim waktu UTC (ISO-8601), tampilan selalu dalam WIB,
// dan nilai form datetime-local dikirim balik sebagai jam WIB.

export const ZONA_WIB = 'Asia/Jakarta';

const FORMAT_TAMPIL = {
    timeZone: ZONA_WIB,
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
};

// Laravel mengirim 6 digit pecahan detik ("...00.000000Z"); potong ke 3 digit agar aman di semua browser.
function keDate(iso) {
    if (!iso) return null;
    const date = new Date(String(iso).replace(/\.(\d{3})\d+/, '.$1'));
    return Number.isNaN(date.getTime()) ? null : date;
}

/**
 * Format waktu UTC dari server menjadi teks WIB, mis. "1 Nov 2026, 08.00".
 * Mengembalikan `kosong` bila nilai tidak ada atau tidak valid.
 */
export function formatWib(iso, kosong = '-') {
    const date = keDate(iso);
    return date ? new Intl.DateTimeFormat('id-ID', FORMAT_TAMPIL).format(date) : kosong;
}

/**
 * Ubah waktu UTC dari server menjadi nilai <input type="datetime-local"> dalam WIB ("YYYY-MM-DDTHH:mm").
 * Detik ikut disertakan ("...:ss") bila bukan 00, agar membuka lalu menyimpan form tidak memotong detik.
 */
export function keInputWib(iso) {
    const date = keDate(iso);
    if (!date) return '';

    const bagian = Object.fromEntries(
        new Intl.DateTimeFormat('en-GB', {
            timeZone: ZONA_WIB,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hourCycle: 'h23',
        })
            .formatToParts(date)
            .map(({ type, value }) => [type, value]),
    );

    const detik = bagian.second && bagian.second !== '00' ? `:${bagian.second}` : '';

    return `${bagian.year}-${bagian.month}-${bagian.day}T${bagian.hour}:${bagian.minute}${detik}`;
}
