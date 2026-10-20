import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import ParticipantLayout from '@/Layouts/ParticipantLayout';
import PetaKursi from '@/Components/PetaKursi';
import { formatWib } from '@/lib/waktuWib';
import { KATEGORI_OPTIONS, METODE_OPTIONS, formatNarasumber, labelDari } from '@/lib/kegiatanForm';
import { GAYA_KURSI, STATUS_BOOKING, keteranganBooking } from '@/lib/kursi';

const INTERVAL_PEMBARUAN_MS = 30000;

const pesertaKosong = () => ({ name: '', email: '', identity: '', phone: '', seat_id: null, seat_code: null });

function Info({ label, children }) {
    return (
        <div className="p-3 rounded-2xl bg-slate-950/70 border border-slate-800/80 min-w-0">
            <div className="text-[10px] text-slate-400 font-bold uppercase">{label}</div>
            <div className="text-xs font-bold text-white mt-1 break-words">{children}</div>
        </div>
    );
}

export default function KegiatanShow({
    kegiatan = {},
    isDraftPreview = false,
    pendaftaran = {},
    booking: bookingAwal = {},
    kursi: kursiAwal = [],
    registrasiSaya = [],
    bukaPilihUlang = false,
    paymentMethods = {},
}) {
    const { auth = {}, errors = {} } = usePage().props;
    const user = auth.user;
    const isFree = Number(kegiatan.biaya) === 0;

    // Status peta & booking diperbarui berkala tanpa memuat ulang halaman.
    const [kursi, setKursi] = useState(kursiAwal);
    const [booking, setBooking] = useState(bookingAwal);
    useEffect(() => setKursi(kursiAwal), [kursiAwal]);
    useEffect(() => setBooking(bookingAwal), [bookingAwal]);

    const perluPilihUlang = registrasiSaya.filter((r) => r.perlu_pilih_ulang && r.saya_pemesan);
    const menungguPemesan = registrasiSaya.filter((r) => r.perlu_pilih_ulang && !r.saya_pemesan);
    const sudahTerdaftar = registrasiSaya.some((r) => r.saya_peserta);

    const [mode, setMode] = useState(() => (bukaPilihUlang && perluPilihUlang.length ? 'pilih_ulang' : 'daftar'));
    const [targetRegistrasi, setTargetRegistrasi] = useState(() => perluPilihUlang[0]?.id ?? null);
    const [kursiBaru, setKursiBaru] = useState(null);

    const [peserta, setPeserta] = useState(() => [{ ...pesertaKosong(), name: user?.name || '', email: user?.email || '', identity: user?.nim || '', phone: user?.phone || '' }]);
    const [aktif, setAktif] = useState(0);
    const [paymentMethod, setPaymentMethod] = useState('QRIS');
    const [memproses, setMemproses] = useState(false);
    const [pemberitahuan, setPemberitahuan] = useState(null);
    const petaRef = useRef(null);

    const bisaPilih = booking.memakai_denah && booking.bisa_pilih;
    const statusBooking = STATUS_BOOKING[booking.status] || STATUS_BOOKING.ditutup;

    useEffect(() => {
        if (mode === 'pilih_ulang') petaRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, [mode]);

    // Nilai terbaru untuk dipakai di callback pembaruan berkala.
    const pesertaRef = useRef(peserta);
    pesertaRef.current = peserta;
    const kursiBaruRef = useRef(kursiBaru);
    kursiBaruRef.current = kursiBaru;

    // Kursi yang sudah tidak tersedia dibuang dari pilihan; pilihan lain tetap.
    const buangPilihanTidakValid = useCallback((statusKursi, pesanAwal = null) => {
        const status = new Map(statusKursi.map((k) => [k.id, k.status]));
        const takTersedia = (id) => status.has(id) && status.get(id) !== 'tersedia';
        const hilang = [];

        const pesertaBaru = pesertaRef.current.map((p) => {
            if (p.seat_id && takTersedia(p.seat_id)) {
                hilang.push(p.seat_code);
                return { ...p, seat_id: null, seat_code: null };
            }
            return p;
        });
        if (hilang.length) setPeserta(pesertaBaru);

        const kb = kursiBaruRef.current;
        if (kb && takTersedia(kb.id)) {
            hilang.push(kb.kode);
            setKursiBaru(null);
        }

        if (pesanAwal || hilang.length) {
            setPemberitahuan(pesanAwal || `Kursi ${[...new Set(hilang)].join(', ')} baru saja diambil peserta lain. Silakan pilih kursi lain; pilihan lain Anda tetap tersimpan.`);
        }
    }, []);

    const muatStatusKursi = useCallback(async (pesan = null) => {
        try {
            const { data } = await window.axios.get(`/kegiatan/${kegiatan.id}/status-kursi`);
            const status = new Map(data.kursi.map((k) => [k.id, k]));
            setKursi((lama) => lama.map((k) => (status.has(k.id) ? { ...k, ...status.get(k.id) } : k)));
            setBooking(data.booking);
            buangPilihanTidakValid(data.kursi, pesan);
        } catch {
            // Gagal memuat: coba lagi pada pembaruan berikutnya.
            if (pesan) setPemberitahuan(pesan);
        }
    }, [kegiatan.id, buangPilihanTidakValid]);

    useEffect(() => {
        if (!booking.memakai_denah) return undefined;
        const id = window.setInterval(() => {
            if (document.visibilityState === 'visible') muatStatusKursi();
        }, INTERVAL_PEMBARUAN_MS);
        return () => window.clearInterval(id);
    }, [booking.memakai_denah, muatStatusKursi]);

    const dipilih = useMemo(() => {
        if (mode === 'pilih_ulang') return kursiBaru ? { [kursiBaru.id]: 'Baru' } : {};
        return Object.fromEntries(peserta.filter((p) => p.seat_id).map((p) => [p.seat_id, `P${peserta.indexOf(p) + 1}`]));
    }, [mode, peserta, kursiBaru]);

    const tampilan = (k) => {
        switch (k.status) {
            case 'tersedia':
                return { kelas: k.tier === 'vip' ? GAYA_KURSI.vip : GAYA_KURSI.tersedia, status: bisaPilih ? 'tersedia' : 'tersedia, belum bisa dipilih', bisaDipilih: bisaPilih && (mode === 'pilih_ulang' ? Boolean(targetRegistrasi) : !sudahTerdaftar && pendaftaran.dibuka) };
            case 'milik':
                return { kelas: GAYA_KURSI.milik, status: `kursi Anda/delegasi Anda (${k.pemegang || '-'})`, bisaDipilih: false, isi: '★' };
            case 'panitia':
                return { kelas: GAYA_KURSI.panitia, status: 'khusus panitia', bisaDipilih: false };
            case 'diblokir':
                return { kelas: GAYA_KURSI.diblokir, status: 'tidak tersedia', bisaDipilih: false, isi: '×' };
            default:
                return { kelas: GAYA_KURSI.terisi, status: 'sudah terisi', bisaDipilih: false };
        }
    };

    const pilihKursi = (k) => {
        setPemberitahuan(null);
        if (mode === 'pilih_ulang') {
            setKursiBaru((lama) => (lama?.id === k.id ? null : k));
            return;
        }
        const pemilikLain = peserta.findIndex((p, i) => p.seat_id === k.id && i !== aktif);
        if (pemilikLain !== -1) {
            setPemberitahuan(`Kursi ${k.kode} sudah dipilih untuk peserta ${pemilikLain + 1}.`);
            return;
        }
        setPeserta((lama) => lama.map((p, i) => {
            if (i !== aktif) return p;
            return p.seat_id === k.id ? { ...p, seat_id: null, seat_code: null } : { ...p, seat_id: k.id, seat_code: k.kode };
        }));
    };

    const ubahPeserta = (i, field, nilai) => setPeserta((lama) => lama.map((p, j) => (j === i ? { ...p, [field]: nilai } : p)));
    const tambahPeserta = () => {
        if (peserta.length >= 10) return setPemberitahuan('Maksimal 10 peserta per pemesanan.');
        setPeserta((lama) => [...lama, pesertaKosong()]);
        setAktif(peserta.length);
    };
    const hapusPeserta = (i) => {
        if (peserta.length <= 1) return;
        setPeserta((lama) => lama.filter((_, j) => j !== i));
        setAktif(Math.max(0, i - 1));
    };

    const daftar = (e) => {
        e.preventDefault();
        if (!user) {
            window.location.href = '/masuk';
            return;
        }
        for (let i = 0; i < peserta.length; i++) {
            if (!peserta[i].name.trim()) return setPemberitahuan(`Nama peserta ${i + 1} wajib diisi.`);
            if (booking.memakai_denah && !peserta[i].seat_id) {
                setAktif(i);
                return setPemberitahuan(`Pilih kursi untuk peserta ${i + 1} pada peta.`);
            }
        }
        router.post(`/kegiatan/${kegiatan.id}/checkout`, { delegates: peserta, payment_method: paymentMethod }, {
            preserveScroll: true,
            onStart: () => setMemproses(true),
            onFinish: () => setMemproses(false),
            onError: (errs) => {
                const bentrok = Object.keys(errs).filter((k) => /^delegates\.\d+\.seat_id$/.test(k));
                if (bentrok.length) {
                    // Buang hanya kursi yang gagal, lalu segarkan status peta.
                    const indeks = bentrok.map((k) => Number(k.split('.')[1]));
                    setPeserta((lama) => lama.map((p, i) => (indeks.includes(i) ? { ...p, seat_id: null, seat_code: null } : p)));
                    setAktif(indeks[0]);
                    muatStatusKursi(bentrok.map((k) => errs[k]).join(' '));
                }
            },
        });
    };

    const simpanPilihUlang = (e) => {
        e.preventDefault();
        if (!targetRegistrasi || !kursiBaru) return setPemberitahuan('Pilih peserta lalu pilih kursi baru pada peta.');
        router.post(`/kegiatan/${kegiatan.id}/reselect-seat`, { registration_id: targetRegistrasi, seat_id: kursiBaru.id }, {
            preserveScroll: true,
            onStart: () => setMemproses(true),
            onFinish: () => setMemproses(false),
            onSuccess: () => setKursiBaru(null),
            onError: (errs) => {
                setKursiBaru(null);
                muatStatusKursi(errs.seat_id || null);
            },
        });
    };

    // Setelah pilih ulang berhasil (masih ada sisa), arahkan ke registrasi berikutnya.
    useEffect(() => {
        if (mode === 'pilih_ulang' && !perluPilihUlang.some((r) => r.id === targetRegistrasi)) {
            setTargetRegistrasi(perluPilihUlang[0]?.id ?? null);
            if (!perluPilihUlang.length) setMode('daftar');
        }
    }, [registrasiSaya]);

    const totalBiaya = peserta.length * Number(kegiatan.biaya);
    const errorKursi = Object.entries(errors).filter(([k]) => k.startsWith('delegates.') || k === 'seat_id').map(([, v]) => v);

    return (
        <ParticipantLayout title={kegiatan.nama_kegiatan}>
            <div className="space-y-6 min-w-0">
                {/* Banner Pratinjau Draft untuk Super Admin */}
                {isDraftPreview && (
                    <div
                        className="p-4 rounded-2xl bg-amber-500/15 border border-amber-500/40 text-amber-200 text-sm font-bold flex items-center gap-3 shadow-lg"
                        data-banner-draft-preview
                    >
                        <span className="material-symbols-outlined text-amber-400 text-2xl" aria-hidden="true">visibility</span>
                        <span>Pratinjau: kegiatan ini belum diterbitkan</span>
                    </div>
                )}

                {/* Banner pilih ulang kursi */}
                {perluPilihUlang.length > 0 && (
                    <section className="p-5 rounded-3xl bg-amber-500/10 border border-amber-400/50 space-y-3" aria-labelledby="judul-pilih-ulang" data-banner-pilih-ulang>
                        <div className="flex items-start gap-3">
                            <span className="material-symbols-outlined text-3xl text-amber-400" aria-hidden="true">sync_problem</span>
                            <div className="min-w-0">
                                <h2 id="judul-pilih-ulang" className="text-sm font-black text-white">Ruangan berubah: {perluPilihUlang.length} peserta perlu kursi baru</h2>
                                <p className="text-xs text-amber-100/90 mt-0.5">Tiket dan status pendaftaran tetap berlaku. Pilih kursi baru untuk setiap peserta di bawah ini.</p>
                            </div>
                        </div>
                        <ul className="flex flex-wrap gap-2">
                            {perluPilihUlang.map((r) => (
                                <li key={r.id}>
                                    <button
                                        type="button"
                                        onClick={() => { setMode('pilih_ulang'); setTargetRegistrasi(r.id); setKursiBaru(null); }}
                                        className={`px-3 py-2 rounded-xl text-xs font-bold border ${mode === 'pilih_ulang' && targetRegistrasi === r.id ? 'bg-amber-400 text-slate-950 border-amber-200' : 'bg-slate-950 text-amber-100 border-amber-400/40 hover:border-amber-300'}`}
                                        data-pilih-ulang-registrasi={r.id}
                                    >
                                        {r.nama_peserta} <span className="font-mono opacity-70">• {r.nomor_tiket}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                        {mode !== 'pilih_ulang' && (
                            <button type="button" onClick={() => setMode('pilih_ulang')} className="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-black">
                                Pilih ulang kursi sekarang
                            </button>
                        )}
                    </section>
                )}
                {menungguPemesan.length > 0 && (
                    <div className="p-4 rounded-2xl bg-sky-500/10 border border-sky-500/30 text-xs text-sky-100">
                        Ruangan kegiatan berubah. Kursi baru untuk tiket Anda ({menungguPemesan.map((r) => r.nomor_tiket).join(', ')}) akan dipilihkan oleh pemesan tiket ({menungguPemesan[0].nama_pemesan || 'pemesan'}).
                    </div>
                )}

                {/* Header kegiatan */}
                <div className="rounded-3xl bg-slate-900 border border-slate-800 p-5 md:p-8 shadow-2xl">
                    <div className="flex flex-col md:flex-row gap-6 items-start justify-between">
                        <div className="space-y-3 max-w-2xl min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="px-3 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                    {labelDari(KATEGORI_OPTIONS, kegiatan.kategori)}
                                </span>
                                <span className="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-slate-800 text-slate-300">
                                    {labelDari(METODE_OPTIONS, kegiatan.metode_pelaksanaan)}
                                </span>
                                {kegiatan.sasaran_peserta === 'kipk_only' && (
                                    <span className="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-amber-500/15 text-amber-400 border border-amber-500/30">Khusus Mahasiswa KIP-K</span>
                                )}
                            </div>
                            <h1 className="text-2xl sm:text-3xl font-black text-white tracking-tight break-words">{kegiatan.nama_kegiatan}</h1>
                            {formatNarasumber(kegiatan.narasumber) && (
                                <p className="text-sm font-semibold text-emerald-400">Narasumber: {formatNarasumber(kegiatan.narasumber)}</p>
                            )}
                            <p className="text-xs text-slate-300 leading-relaxed">{kegiatan.deskripsi || 'Kegiatan resmi SEMADIKSI Universitas Nahdlatul Ulama Surabaya.'}</p>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                                <Info label="Waktu">{kegiatan.tanggal_mulai ? `${formatWib(kegiatan.tanggal_mulai)} WIB` : '-'}</Info>
                                <Info label="Lokasi">{kegiatan.lokasi_nama || '-'}</Info>
                                <Info label="Biaya">{isFree ? 'Gratis' : `Rp ${Number(kegiatan.biaya).toLocaleString('id-ID')}`}</Info>
                                <Info label="Kuota tersisa">{pendaftaran.kuota_tersisa} dari {kegiatan.kuota}</Info>
                            </div>
                        </div>
                        {kegiatan.file_panduan_url && (
                            <a href={kegiatan.file_panduan_url} target="_blank" rel="noreferrer" className="w-full md:w-56 p-4 rounded-2xl bg-slate-950 border border-slate-800 text-center text-xs font-bold text-white hover:border-slate-600">
                                <span className="material-symbols-outlined text-2xl text-rose-400 block" aria-hidden="true">picture_as_pdf</span>
                                Unduh Panduan Resmi (PDF)
                            </a>
                        )}
                    </div>
                </div>

                {registrasiSaya.length > 0 && (
                    <div className="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3" data-registrasi-saya>
                        <div className="text-xs text-emerald-100 min-w-0">
                            <div className="font-bold text-white text-sm">Tiket Anda di kegiatan ini</div>
                            <ul className="mt-1 space-y-0.5">
                                {registrasiSaya.map((r) => (
                                    <li key={r.id}>{r.nama_peserta} • <span className="font-mono">{r.nomor_tiket}</span> • kursi {r.perlu_pilih_ulang ? <strong className="text-amber-300">perlu pilih ulang</strong> : (r.kursi || 'tanpa nomor')}</li>
                                ))}
                            </ul>
                        </div>
                        <a href="/dashboard/kegiatan-saya" className="shrink-0 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs text-center">Lihat E-Tiket</a>
                    </div>
                )}

                {pemberitahuan && (
                    <div role="alert" className="p-4 rounded-2xl bg-rose-500/15 border border-rose-500/40 text-rose-100 text-xs font-medium flex items-start gap-2" data-pemberitahuan-kursi>
                        <span className="material-symbols-outlined text-base" aria-hidden="true">error</span>
                        <span className="flex-1">{pemberitahuan}</span>
                        <button type="button" onClick={() => setPemberitahuan(null)} className="text-rose-200 hover:text-white" aria-label="Tutup pemberitahuan">
                            <span className="material-symbols-outlined text-sm" aria-hidden="true">close</span>
                        </button>
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Peta kursi */}
                    <div className="lg:col-span-2 min-w-0" ref={petaRef} id="peta-kursi">
                        {booking.memakai_denah ? (
                            <section className="bg-slate-900 border border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xl space-y-4 min-w-0" aria-labelledby="judul-peta">
                                <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h2 id="judul-peta" className="text-base font-black text-white">Denah Kursi</h2>
                                        <p className="text-xs text-slate-400 mt-0.5" data-keterangan-booking>{keteranganBooking(booking.status, booking.buka, booking.tutup)}</p>
                                        {bisaPilih && (
                                            <p className="text-xs text-emerald-300 mt-1">
                                                {mode === 'pilih_ulang'
                                                    ? `Pilih kursi baru untuk ${perluPilihUlang.find((r) => r.id === targetRegistrasi)?.nama_peserta || 'peserta'}.`
                                                    : (sudahTerdaftar || !pendaftaran.dibuka ? '' : `Pilih kursi untuk peserta ${aktif + 1}${peserta[aktif]?.name ? ` (${peserta[aktif].name})` : ''}.`)}
                                            </p>
                                        )}
                                    </div>
                                    <span className={`self-start inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-[11px] font-black uppercase tracking-wider ${statusBooking.kelas}`} data-status-booking={booking.status}>
                                        <span className="material-symbols-outlined text-sm" aria-hidden="true">{statusBooking.ikon}</span>
                                        {statusBooking.label}
                                    </span>
                                </div>
                                <PetaKursi
                                    kursi={kursi}
                                    tampilan={tampilan}
                                    dipilih={dipilih}
                                    onPilih={pilihKursi}
                                    labelPeta={`Denah kursi ${kegiatan.nama_kegiatan}`}
                                    aisleAfterCol={kegiatan.seat_layout_config?.aisle_after_col}
                                    legenda={[
                                        { label: 'Tersedia', kelas: GAYA_KURSI.tersedia },
                                        { label: 'VIP', kelas: GAYA_KURSI.vip },
                                        { label: 'Pilihan Anda', kelas: GAYA_KURSI.dipilih },
                                        { label: 'Kursi saya / delegasi', kelas: GAYA_KURSI.milik },
                                        { label: 'Terisi', kelas: GAYA_KURSI.terisi },
                                        { label: 'Panitia', kelas: GAYA_KURSI.panitia },
                                        { label: 'Tidak tersedia', kelas: GAYA_KURSI.diblokir },
                                    ]}
                                />
                            </section>
                        ) : (
                            <div className="bg-slate-900 border border-slate-800 rounded-3xl p-8 text-center space-y-2">
                                <h2 className="text-base font-black text-white">Tanpa kursi bernomor</h2>
                                <p className="text-xs text-slate-400">Kegiatan ini tidak memakai pemilihan kursi. Peserta menempati kursi sesuai kedatangan.</p>
                            </div>
                        )}
                    </div>

                    {/* Panel kanan: pilih ulang atau pendaftaran */}
                    <div className="min-w-0">
                        {mode === 'pilih_ulang' && perluPilihUlang.length > 0 ? (
                            <form onSubmit={simpanPilihUlang} className="bg-slate-900 border border-amber-400/40 rounded-3xl p-5 space-y-4" data-form-pilih-ulang>
                                <h3 className="text-sm font-black text-white">Pilih Ulang Kursi</h3>
                                <div>
                                    <label htmlFor="registrasi-pilih-ulang" className="block text-[11px] font-bold text-slate-400 mb-1">Peserta</label>
                                    <select id="registrasi-pilih-ulang" value={targetRegistrasi ?? ''} onChange={(e) => { setTargetRegistrasi(Number(e.target.value)); setKursiBaru(null); }} className="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs">
                                        {perluPilihUlang.map((r) => <option key={r.id} value={r.id}>{r.nama_peserta} ({r.nomor_tiket})</option>)}
                                    </select>
                                </div>
                                <div className="p-3 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between text-xs">
                                    <span className="text-slate-400">Kursi baru</span>
                                    <span className="font-mono text-sm font-black text-sky-300" data-kursi-baru>{kursiBaru?.kode || <span className="font-sans text-slate-500 italic text-xs">Belum dipilih</span>}</span>
                                </div>
                                {!bisaPilih && <p className="text-[11px] text-amber-300">{keteranganBooking(booking.status, booking.buka, booking.tutup)}</p>}
                                {errorKursi.length > 0 && <p className="text-[11px] text-rose-300">{errorKursi[0]}</p>}
                                <button type="submit" disabled={memproses || !kursiBaru || !bisaPilih} className="w-full py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 disabled:opacity-40 text-slate-950 text-xs font-black">
                                    {memproses ? 'Menyimpan…' : 'Simpan kursi baru'}
                                </button>
                                <button type="button" onClick={() => { setMode('daftar'); setKursiBaru(null); }} className="w-full text-[11px] text-slate-400 hover:text-white">Tutup alur pilih ulang</button>
                            </form>
                        ) : !user ? (
                            <div className="bg-slate-900 border border-slate-800 rounded-3xl p-5 space-y-3 text-center">
                                <p className="text-xs text-slate-300">Masuk untuk mendaftar dan memilih kursi.</p>
                                <a href="/masuk" className="block w-full py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black">Masuk</a>
                            </div>
                        ) : sudahTerdaftar ? (
                            <div className="bg-slate-900 border border-slate-800 rounded-3xl p-5 text-xs text-slate-300">Anda sudah terdaftar di kegiatan ini. Lihat e-tiket Anda di halaman Kegiatan Saya.</div>
                        ) : !pendaftaran.dibuka ? (
                            <div className="bg-slate-900 border border-slate-800 rounded-3xl p-5 text-xs text-slate-300">Pendaftaran kegiatan ini sudah ditutup.</div>
                        ) : (
                            <form onSubmit={daftar} className="bg-slate-900 border border-slate-800 rounded-3xl p-5 space-y-4" data-form-daftar>
                                <div className="flex items-center justify-between gap-2">
                                    <div>
                                        <h3 className="text-sm font-black text-white">Pendaftaran &amp; Delegasi</h3>
                                        <p className="text-[11px] text-slate-400">Daftarkan diri sendiri atau bersama rekan.</p>
                                    </div>
                                    <button type="button" onClick={tambahPeserta} className="px-2.5 py-1.5 rounded-xl bg-emerald-500/15 text-emerald-300 text-xs font-bold border border-emerald-500/30">+ Peserta</button>
                                </div>

                                <div className="flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Peserta">
                                    {peserta.map((p, i) => (
                                        <button
                                            key={i}
                                            type="button"
                                            role="tab"
                                            aria-selected={aktif === i}
                                            onClick={() => setAktif(i)}
                                            className={`px-3 py-1.5 rounded-xl text-xs font-bold shrink-0 ${aktif === i ? 'bg-emerald-600 text-white' : 'bg-slate-950 border border-slate-800 text-slate-400'}`}
                                            data-tab-peserta={i}
                                        >
                                            P{i + 1}{p.seat_code ? ` • ${p.seat_code}` : ''}
                                        </button>
                                    ))}
                                </div>

                                <div className="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-3 text-xs">
                                    <div className="flex items-center justify-between">
                                        <span className="font-extrabold text-white">Peserta {aktif + 1}{aktif === 0 ? ' (Anda)' : ''}</span>
                                        {peserta.length > 1 && <button type="button" onClick={() => hapusPeserta(aktif)} className="text-rose-400 text-[11px] font-bold">Hapus</button>}
                                    </div>
                                    <div>
                                        <label htmlFor="peserta-nama" className="block text-[11px] font-bold text-slate-400 mb-1">Nama lengkap *</label>
                                        <input id="peserta-nama" type="text" value={peserta[aktif]?.name || ''} onChange={(e) => ubahPeserta(aktif, 'name', e.target.value)} className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white" required />
                                    </div>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <div>
                                            <label htmlFor="peserta-email" className="block text-[11px] font-bold text-slate-400 mb-1">Email</label>
                                            <input id="peserta-email" type="email" value={peserta[aktif]?.email || ''} onChange={(e) => ubahPeserta(aktif, 'email', e.target.value)} className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white" />
                                        </div>
                                        <div>
                                            <label htmlFor="peserta-identitas" className="block text-[11px] font-bold text-slate-400 mb-1">NIM / identitas</label>
                                            <input id="peserta-identitas" type="text" value={peserta[aktif]?.identity || ''} onChange={(e) => ubahPeserta(aktif, 'identity', e.target.value)} className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white" />
                                        </div>
                                    </div>
                                    {booking.memakai_denah && (
                                        <div className="p-3 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between">
                                            <span className="text-[11px] text-slate-400">Kursi</span>
                                            <span className="font-mono text-sm font-black text-sky-300" data-kursi-peserta={aktif}>
                                                {peserta[aktif]?.seat_code || <span className="font-sans text-slate-500 italic text-xs">Pilih di peta</span>}
                                            </span>
                                        </div>
                                    )}
                                    {errors[`delegates.${aktif}.seat_id`] && <p className="text-[11px] text-rose-300">{errors[`delegates.${aktif}.seat_id`]}</p>}
                                </div>

                                {!isFree && (
                                    <div className="space-y-2">
                                        <div className="text-xs font-bold text-slate-300">Metode pembayaran</div>
                                        <div className="grid grid-cols-2 gap-2">
                                            {Object.entries(paymentMethods).map(([k, m]) => (
                                                <button key={k} type="button" onClick={() => setPaymentMethod(m.code)} aria-pressed={paymentMethod === m.code} className={`p-3 rounded-2xl border text-left ${paymentMethod === m.code ? 'bg-emerald-500/15 border-emerald-500' : 'bg-slate-950 border-slate-800'}`}>
                                                    <div className="text-xs font-bold text-white">{m.code}</div>
                                                    <div className="text-[10px] text-slate-400 truncate">{m.label}</div>
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="pt-3 border-t border-slate-800 space-y-3">
                                    <div className="flex justify-between text-sm font-black text-white">
                                        <span>Total ({peserta.length} peserta)</span>
                                        <span className="text-emerald-400">{isFree ? 'Gratis' : `Rp ${totalBiaya.toLocaleString('id-ID')}`}</span>
                                    </div>
                                    {booking.memakai_denah && !bisaPilih && <p className="text-[11px] text-amber-300">{keteranganBooking(booking.status, booking.buka, booking.tutup)}</p>}
                                    <button type="submit" disabled={memproses || (booking.memakai_denah && !bisaPilih)} className="w-full py-3.5 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-500 disabled:opacity-40 text-white text-xs font-black uppercase tracking-wider">
                                        {memproses ? 'Memproses…' : isFree ? 'Daftar sekarang' : 'Bayar & kunci kursi'}
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </ParticipantLayout>
    );
}
