import React, { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import PetaKursi from '@/Components/PetaKursi';
import { keInputWib } from '@/lib/waktuWib';
import { GAYA_KURSI, STATUS_BOOKING, daftarDariTeks, kapasitasDenah, keteranganBooking } from '@/lib/kursi';
import { LOKASI_TYPE_OPTIONS } from '@/lib/kegiatanForm';

const kelasInput = 'w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs focus:border-emerald-500 focus:outline-none';

const STATUS_ADMIN = {
    available: 'tersedia',
    reserved_panitia: 'kursi panitia',
    booked: 'terisi',
    blocked: 'diblokir',
};

function jadwalAwal(kegiatan) {
    return {
        buka: keInputWib(kegiatan.booking_opens_at),
        tutup: keInputWib(kegiatan.booking_closes_at),
        aktif: Boolean(kegiatan.is_seat_booking_active),
    };
}

function denahAwal(kegiatan, denah) {
    const cols = denah.cols_per_row ?? 10;
    return {
        rows: (denah.rows || []).join(', '),
        cols: cols,
        aisle_after_col: denah.aisle_after_col ?? Math.floor(cols / 2),
        vip: (denah.vip_rows || []).join(', '),
        panitia: (denah.panitia_rows || []).join(', '),
        blokir: denah.blocked_seats || [],
        lokasi_type: kegiatan.lokasi_type || 'unusa_campus',
        lokasi_kampus: kegiatan.lokasi_kampus || '',
        lokasi_gedung: kegiatan.lokasi_gedung || '',
        lokasi_ruangan: kegiatan.lokasi_ruangan || '',
        lokasi_nama: kegiatan.lokasi_nama || '',
        lokasi_detail: kegiatan.lokasi_detail || '',
    };
}

function Field({ label, htmlFor, children, error, bantuan }) {
    return (
        <div>
            <label htmlFor={htmlFor} className="block text-[11px] font-bold text-slate-400 mb-1">{label}</label>
            {children}
            {bantuan && <p className="text-[10px] text-slate-500 mt-1">{bantuan}</p>}
            {error && <p className="text-[10px] text-rose-400 mt-1">{error}</p>}
        </div>
    );
}

export default function DenahBookingKegiatan({ kegiatan, kursi = [], denah = {}, pendaftar = [], unusaFacilities = [] }) {
    const { errors = {} } = usePage().props;
    const [jadwal, setJadwal] = useState(() => jadwalAwal(kegiatan));
    const [form, setForm] = useState(() => denahAwal(kegiatan, denah));
    const [modeBlokir, setModeBlokir] = useState(false);
    const [konfirmasi, setKonfirmasi] = useState(null);
    const [pesanTolak, setPesanTolak] = useState(null);
    const [errorPratinjau, setErrorPratinjau] = useState({});
    const [pesanGalatJadwal, setPesanGalatJadwal] = useState(null);
    const [memproses, setMemproses] = useState(false);

    // Setelah tersimpan, server mengirim data terbaru: samakan form dengannya.
    useEffect(() => setJadwal(jadwalAwal(kegiatan)), [kegiatan.booking_opens_at, kegiatan.booking_closes_at, kegiatan.is_seat_booking_active]);
    // Dibandingkan per isi: respons penolakan (isi sama) tidak menghapus isian admin.
    const sidikDenah = JSON.stringify([denah, kegiatan.lokasi_type, kegiatan.lokasi_nama, kegiatan.lokasi_detail, kegiatan.lokasi_ruangan]);
    useEffect(() => setForm(denahAwal(kegiatan, denah)), [sidikDenah]);

    const layoutForm = useMemo(() => ({
        rows: daftarDariTeks(form.rows),
        cols_per_row: parseInt(form.cols, 10) || 0,
        aisle_after_col: parseInt(form.aisle_after_col, 10) || Math.floor((parseInt(form.cols, 10) || 0) / 2),
        vip_rows: daftarDariTeks(form.vip),
        panitia_rows: daftarDariTeks(form.panitia),
        blocked_seats: [...form.blokir].sort(),
    }), [form]);
    const kapasitasBaru = kapasitasDenah(layoutForm);
    const pilihUlang = pendaftar.filter((p) => p.needs_seat_reselection && ['free', 'pending', 'paid'].includes(p.status_pembayaran));
    const statusBooking = STATUS_BOOKING[kegiatan.status_booking] || STATUS_BOOKING.ditutup;
    const url = `/admin/kegiatan/${kegiatan.id}/venue-config`;

    const ubah = (field, nilai) => setForm((f) => ({ ...f, [field]: nilai }));

    const pilihFasilitas = (kampusNama, gedungNama = null, ruanganNama = null) => {
        const kampus = unusaFacilities.find((c) => c.name === kampusNama);
        const gedung = kampus?.buildings?.find((b) => b.name === gedungNama) || kampus?.buildings?.[0];
        const ruangan = gedung?.rooms?.find((r) => r.name === ruanganNama) || gedung?.rooms?.[0];
        setForm((f) => ({
            ...f,
            lokasi_kampus: kampusNama,
            lokasi_gedung: gedung?.name || '',
            lokasi_ruangan: ruangan?.name || '',
            lokasi_nama: `${gedung?.name || ''} - ${kampusNama}`,
            lokasi_detail: `${ruangan?.name || ''}, ${kampus?.address || ''}`,
        }));
    };

    const payload = (layoutBerubah) => ({
        layout_changed: layoutBerubah,
        is_seat_booking_active: jadwal.aktif,
        booking_opens_at: jadwal.buka || null,
        booking_closes_at: jadwal.tutup || null,
        ...(layoutBerubah ? {
            layout_config: layoutForm,
            lokasi_type: form.lokasi_type,
            lokasi_nama: form.lokasi_nama,
            lokasi_detail: form.lokasi_detail || null,
            ...(form.lokasi_type === 'unusa_campus' ? {
                lokasi_kampus: form.lokasi_kampus,
                lokasi_gedung: form.lokasi_gedung,
                lokasi_ruangan: form.lokasi_ruangan,
            } : {}),
        } : {}),
    });

    const opsiKirim = {
        preserveScroll: true,
        preserveState: true,
        onStart: () => setMemproses(true),
        onFinish: () => setMemproses(false),
    };

    const simpanJadwal = (e) => {
        e.preventDefault();
        setPesanGalatJadwal(null);
        if (jadwal.buka && jadwal.tutup && jadwal.tutup <= jadwal.buka) {
            setPesanGalatJadwal('Waktu batas akhir booking kursi harus setelah waktu buka.');
            return;
        }
        router.put(url, payload(false), opsiKirim);
    };

    const kirimDenah = () => {
        router.put(url, payload(true), {
            ...opsiKirim,
            onSuccess: () => { setKonfirmasi(null); setModeBlokir(false); },
            onError: () => setKonfirmasi(null),
        });
    };

    // Cek dampak ke server dulu; bila kursi peserta akan direset, minta konfirmasi.
    const simpanDenah = async (e) => {
        e.preventDefault();
        setPesanTolak(null);
        setErrorPratinjau({});
        setMemproses(true);
        try {
            const { data } = await window.axios.post(`${url}/pratinjau`, payload(true));
            if (!data.cukup) {
                setPesanTolak(data.pesan_tolak);
            } else if (data.kursi_bentrok?.length) {
                setPesanTolak(`Kursi ${data.kursi_bentrok.join(', ')} sedang dipakai peserta sehingga tidak bisa dijadikan kursi panitia atau diblokir.`);
            } else if (data.akan_reset) {
                setKonfirmasi(data);
            } else {
                kirimDenah();
            }
        } catch (err) {
            const daftar = err.response?.data?.errors || {};
            setErrorPratinjau(Object.fromEntries(Object.entries(daftar).map(([k, v]) => [k, v[0]])));
            if (!Object.keys(daftar).length) setPesanTolak('Gagal memeriksa perubahan denah. Coba lagi.');
        } finally {
            setMemproses(false);
        }
    };

    const semuaError = { ...errors, ...errorPratinjau };
    const errorDenah = semuaError.capacity || semuaError.layout || Object.entries(semuaError).find(([k]) => k.startsWith('layout_config'))?.[1];

    const tampilan = (k) => {
        if (modeBlokir) {
            const diblokir = form.blokir.includes(k.kode);
            if (k.status === 'booked') return { kelas: GAYA_KURSI.terisi, status: `terisi oleh ${k.pemegang || 'peserta'}, tidak bisa diblokir`, bisaDipilih: false };
            if (diblokir) return { kelas: GAYA_KURSI.diblokir, status: 'akan diblokir', bisaDipilih: true, isi: '×' };
            return { kelas: k.tier === 'vip' ? GAYA_KURSI.vip : GAYA_KURSI.tersedia, status: 'bisa diblokir', bisaDipilih: true };
        }
        switch (k.status) {
            case 'booked': return { kelas: GAYA_KURSI.terisi, status: `terisi oleh ${k.pemegang || 'peserta'}`, bisaDipilih: true };
            case 'reserved_panitia': return { kelas: GAYA_KURSI.panitia, status: 'kursi panitia', bisaDipilih: true };
            case 'blocked': return { kelas: GAYA_KURSI.diblokir, status: 'diblokir', bisaDipilih: true, isi: '×' };
            default: return { kelas: k.tier === 'vip' ? GAYA_KURSI.vip : GAYA_KURSI.tersedia, status: k.tier === 'vip' ? 'tersedia (VIP)' : 'tersedia', bisaDipilih: true };
        }
    };

    const info = (k) => {
        const t = tampilan(k);
        return `Kursi ${k.kode}: ${modeBlokir ? t.status : STATUS_ADMIN[k.status] || k.status}${k.status === 'booked' ? ` oleh ${k.pemegang || '-'}${k.tiket ? ` (${k.tiket})` : ''}` : ''}${k.tier === 'vip' ? ' • VIP' : ''}`;
    };

    const toggleBlokir = (k) => {
        if (!modeBlokir) return;
        ubah('blokir', form.blokir.includes(k.kode) ? form.blokir.filter((c) => c !== k.kode) : [...form.blokir, k.kode]);
    };

    const kampus = unusaFacilities.find((c) => c.name === form.lokasi_kampus);
    const gedung = kampus?.buildings?.find((b) => b.name === form.lokasi_gedung);

    return (
        <div className="space-y-6" data-tab-denah>
            {/* Status & jadwal booking */}
            <form onSubmit={simpanJadwal} className="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-4" data-form-jadwal-booking>
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 className="text-sm font-black text-white flex items-center gap-2">
                            <span className="material-symbols-outlined text-emerald-400 text-lg">event_seat</span>
                            Jadwal Booking Kursi
                        </h3>
                        <p className="text-[11px] text-slate-400 mt-0.5" data-keterangan-booking>
                            {keteranganBooking(kegiatan.status_booking, kegiatan.booking_opens_at, kegiatan.booking_closes_at)}
                        </p>
                    </div>
                    <span className={`self-start inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-[11px] font-black uppercase tracking-wider ${statusBooking.kelas}`} data-status-booking={kegiatan.status_booking}>
                        <span className="material-symbols-outlined text-sm">{statusBooking.ikon}</span>
                        {statusBooking.label}
                    </span>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                    <Field label="Buka booking (WIB)" htmlFor="booking-buka" error={errors.booking_opens_at}>
                        <input id="booking-buka" type="datetime-local" step="1" value={jadwal.buka} onChange={(e) => { setPesanGalatJadwal(null); setJadwal({ ...jadwal, buka: e.target.value }); }} className={kelasInput} />
                    </Field>
                    <Field label="Tutup booking (WIB)" htmlFor="booking-tutup" error={errors.booking_closes_at || pesanGalatJadwal} bantuan="Tertutup tepat pada jam ini. Kosongkan bila tanpa batas.">
                        <input id="booking-tutup" type="datetime-local" step="1" value={jadwal.tutup} onChange={(e) => { setPesanGalatJadwal(null); setJadwal({ ...jadwal, tutup: e.target.value }); }} className={kelasInput} />
                    </Field>
                    <label className="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-200 cursor-pointer">
                        <span>
                            <span className="font-bold block">Booking kursi aktif</span>
                            <span className="text-[10px] text-slate-500">Nonaktif = dibekukan; kursi yang ada tetap tersimpan.</span>
                        </span>
                        <input id="booking-aktif" type="checkbox" checked={jadwal.aktif} onChange={(e) => setJadwal({ ...jadwal, aktif: e.target.checked })} className="w-5 h-5 rounded text-emerald-600 bg-slate-900 border-slate-700" />
                    </label>
                </div>
                <div className="flex justify-end">
                    <button type="submit" disabled={memproses} className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-xs font-bold">
                        Simpan Jadwal
                    </button>
                </div>
            </form>

            {/* Peta kursi */}
            <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-4 min-w-0">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 className="text-sm font-black text-white">Peta Kursi</h3>
                        <p className="text-[11px] text-slate-400">{kursi.length} kursi • {kursi.filter((k) => k.status === 'booked').length} terisi</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setModeBlokir((m) => !m)}
                        aria-pressed={modeBlokir}
                        className={`px-3 py-2 rounded-xl text-xs font-bold border ${modeBlokir ? 'bg-slate-200 text-slate-900 border-white' : 'bg-slate-950 text-slate-300 border-slate-700 hover:text-white'}`}
                        data-mode-blokir
                    >
                        <span className="material-symbols-outlined text-sm align-middle mr-1">block</span>
                        {modeBlokir ? 'Selesai memilih kursi diblokir' : 'Pilih kursi yang diblokir'}
                    </button>
                </div>
                {modeBlokir && (
                    <p className="text-[11px] text-amber-300">Klik kursi untuk menandai/membatalkan blokir, lalu simpan lewat form denah di bawah.</p>
                )}
                {kursi.length > 0 ? (
                    <PetaKursi
                        kursi={kursi}
                        tampilan={tampilan}
                        onPilih={toggleBlokir}
                        info={info}
                        labelPeta="Peta kursi kegiatan (admin)"
                        aisleAfterCol={denah.aisle_after_col ?? Math.floor((denah.cols_per_row || 10) / 2)}
                        legenda={[
                            { label: 'Tersedia', kelas: GAYA_KURSI.tersedia },
                            { label: 'VIP', kelas: GAYA_KURSI.vip },
                            { label: 'Panitia', kelas: GAYA_KURSI.panitia },
                            { label: 'Terisi', kelas: GAYA_KURSI.terisi },
                            { label: 'Diblokir', kelas: GAYA_KURSI.diblokir },
                            { label: 'Perlu pilih ulang', kelas: GAYA_KURSI.pilihUlang },
                        ]}
                    />
                ) : (
                    <p className="text-xs text-slate-500">Kegiatan ini belum punya denah kursi. Atur denah di bawah untuk membuatnya.</p>
                )}

                {pilihUlang.length > 0 && (
                    <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-400/40 border-dashed" data-antrean-pilih-ulang>
                        <div className="text-[11px] font-bold text-amber-200 mb-2">Perlu pilih ulang kursi ({pilihUlang.length}) — belum punya kursi di denah ini:</div>
                        <ul className="flex flex-wrap gap-1.5">
                            {pilihUlang.map((p) => (
                                <li key={p.id} className="px-2 py-1 rounded-md text-[10px] font-bold bg-amber-400/15 text-amber-100 border border-amber-300/40">
                                    {p.nama_peserta}{p.booker ? ` (dipesankan ${p.booker.name})` : ''}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>

            {/* Denah & venue */}
            <form onSubmit={simpanDenah} className="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-4" data-form-denah>
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 className="text-sm font-black text-white">Denah &amp; Venue</h3>
                        <p className="text-[11px] text-slate-400">
                            Mengubah label baris atau jumlah kolom membuat ulang semua kursi: peserta yang sudah memilih akan diminta memilih ulang lewat email.
                            Mengubah baris VIP/panitia atau kursi diblokir tidak mereset kursi peserta.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => {
                            setForm((f) => ({
                                ...f,
                                rows: 'A, B, C, D, E, F, G, H',
                                cols: 12,
                                aisle_after_col: 6,
                                vip: 'A, B',
                            }));
                        }}
                        className="shrink-0 px-3 py-1.5 rounded-xl bg-sky-500/15 border border-sky-500/30 text-sky-300 text-[11px] font-bold hover:bg-sky-500/25 transition-all"
                    >
                        Preset Bioskop (12 Kolom, Lorong Tengah)
                    </button>
                </div>

                {(pesanTolak || errorDenah) && (
                    <div role="alert" className="p-3 rounded-xl bg-rose-500/15 border border-rose-500/40 text-rose-200 text-xs font-medium flex items-start gap-2" data-pesan-tolak-denah>
                        <span className="material-symbols-outlined text-sm">error</span>
                        <span>{pesanTolak || errorDenah}</span>
                    </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <Field label="Label baris (urut dari panggung)" htmlFor="denah-rows" bantuan="Pisahkan dengan koma, mis. A, B, C">
                        <input id="denah-rows" type="text" value={form.rows} onChange={(e) => ubah('rows', e.target.value)} className={kelasInput} required />
                    </Field>
                    <Field label="Kursi per baris" htmlFor="denah-cols" error={semuaError['layout_config.cols_per_row']}>
                        <input id="denah-cols" type="number" min="1" max="50" value={form.cols} onChange={(e) => {
                            const c = parseInt(e.target.value, 10) || 0;
                            ubah('cols', e.target.value);
                            ubah('aisle_after_col', Math.floor(c / 2));
                        }} className={kelasInput} required />
                    </Field>
                    <Field label="Posisi Gang Tengah (setelah kolom ke-...)" htmlFor="denah-aisle" bantuan="Pemisah lorong visual (default: tengah baris)">
                        <input id="denah-aisle" type="number" min="1" max={form.cols || 50} value={form.aisle_after_col || ''} onChange={(e) => ubah('aisle_after_col', e.target.value)} placeholder="mis. 6" className={kelasInput} />
                    </Field>
                    <Field label="Baris VIP" htmlFor="denah-vip">
                        <input id="denah-vip" type="text" value={form.vip} onChange={(e) => ubah('vip', e.target.value)} placeholder="mis. A" className={kelasInput} />
                    </Field>
                    <Field label="Baris panitia" htmlFor="denah-panitia">
                        <input id="denah-panitia" type="text" value={form.panitia} onChange={(e) => ubah('panitia', e.target.value)} placeholder="mis. H, I" className={kelasInput} />
                    </Field>
                    <Field label="Kursi diblokir" htmlFor="denah-blokir" bantuan="Kode kursi, mis. A01, B10. Bisa juga dipilih lewat peta.">
                        <input
                            id="denah-blokir"
                            type="text"
                            value={form.blokir.join(', ')}
                            onChange={(e) => ubah('blokir', daftarDariTeks(e.target.value))}
                            className={kelasInput}
                        />
                    </Field>
                    <div className="p-3 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-300 flex items-center justify-between">
                        <span>Kapasitas peserta (tanpa panitia &amp; diblokir)</span>
                        <span className="text-lg font-black text-white" data-kapasitas-baru>{kapasitasBaru}</span>
                    </div>
                </div>

                <fieldset className="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                    <legend className="px-1 text-[11px] font-bold text-slate-300">Venue</legend>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <Field label="Tipe lokasi" htmlFor="venue-tipe">
                            <select id="venue-tipe" value={form.lokasi_type} onChange={(e) => ubah('lokasi_type', e.target.value)} className={kelasInput}>
                                {LOKASI_TYPE_OPTIONS.map((o) => <option key={o.id} value={o.id}>{o.label}</option>)}
                            </select>
                        </Field>
                        {form.lokasi_type === 'unusa_campus' && (
                            <>
                                <Field label="Kampus" htmlFor="venue-kampus">
                                    <select id="venue-kampus" value={form.lokasi_kampus} onChange={(e) => pilihFasilitas(e.target.value)} className={kelasInput}>
                                        <option value="">-- Pilih kampus --</option>
                                        {unusaFacilities.map((c) => <option key={c.name} value={c.name}>{c.name}</option>)}
                                    </select>
                                </Field>
                                <Field label="Gedung" htmlFor="venue-gedung" error={semuaError.lokasi_gedung}>
                                    <select id="venue-gedung" value={form.lokasi_gedung} onChange={(e) => pilihFasilitas(form.lokasi_kampus, e.target.value)} className={kelasInput}>
                                        {(kampus?.buildings || []).map((b) => <option key={b.name} value={b.name}>{b.name}</option>)}
                                    </select>
                                </Field>
                                <Field label="Ruangan" htmlFor="venue-ruangan" error={semuaError.lokasi_ruangan}>
                                    <select id="venue-ruangan" value={form.lokasi_ruangan} onChange={(e) => pilihFasilitas(form.lokasi_kampus, form.lokasi_gedung, e.target.value)} className={kelasInput}>
                                        {(gedung?.rooms || []).map((r) => <option key={r.name} value={r.name}>{r.name} (kapasitas {r.capacity})</option>)}
                                    </select>
                                </Field>
                            </>
                        )}
                        <Field label="Nama lokasi tampilan" htmlFor="venue-nama">
                            <input id="venue-nama" type="text" value={form.lokasi_nama} onChange={(e) => ubah('lokasi_nama', e.target.value)} className={kelasInput} required />
                        </Field>
                        <Field label="Detail ruangan / alamat" htmlFor="venue-detail">
                            <input id="venue-detail" type="text" value={form.lokasi_detail} onChange={(e) => ubah('lokasi_detail', e.target.value)} className={kelasInput} />
                        </Field>
                    </div>
                </fieldset>

                <div className="flex justify-end">
                    <button type="submit" disabled={memproses} className="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 disabled:opacity-50 text-white text-xs font-black">
                        {memproses ? 'Memeriksa…' : 'Simpan Denah & Venue'}
                    </button>
                </div>
            </form>

            {/* Dialog konfirmasi reset kursi */}
            {konfirmasi && (
                <div className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
                    <div role="dialog" aria-modal="true" aria-labelledby="judul-konfirmasi-denah" className="bg-slate-900 border border-amber-500/40 rounded-3xl w-full max-w-md p-6 space-y-4 text-xs" data-dialog-konfirmasi-denah>
                        <h3 id="judul-konfirmasi-denah" className="text-base font-black text-white flex items-center gap-2">
                            <span className="material-symbols-outlined text-amber-400">warning</span>
                            Kursi peserta akan direset
                        </h3>
                        <p className="text-slate-300 leading-relaxed">
                            Denah baru membuat ulang semua kursi. <strong className="text-white" data-peserta-terdampak={konfirmasi.peserta_terdampak}>{konfirmasi.peserta_terdampak} peserta</strong> akan
                            kehilangan kursinya dan diminta memilih ulang.
                        </p>
                        <ul className="space-y-1 text-slate-300">
                            <li>• <strong className="text-white" data-email-dikirim={konfirmasi.email_dikirim}>{konfirmasi.email_dikirim} email</strong> akan dikirim:</li>
                            <li className="pl-3">{konfirmasi.email_pemesan} ke pemesan (berisi tautan pilih ulang kursi)</li>
                            <li className="pl-3">{konfirmasi.email_delegasi} pemberitahuan ke delegasi</li>
                            <li>• Kapasitas baru {konfirmasi.kapasitas} kursi untuk {konfirmasi.kursi_terisi} peserta berkursi.</li>
                        </ul>
                        <div className="flex justify-end gap-2 pt-2">
                            <button type="button" onClick={() => setKonfirmasi(null)} className="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 font-bold">Batal</button>
                            <button type="button" onClick={kirimDenah} disabled={memproses} className="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold" data-konfirmasi-ya>
                                Ya, simpan &amp; kirim email
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
