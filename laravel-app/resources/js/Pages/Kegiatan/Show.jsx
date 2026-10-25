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
    const registrasiSayaSendiri = registrasiSaya.find((r) => r.saya_peserta);
    const sayaPerluKursi = registrasiSayaSendiri && (!registrasiSayaSendiri.seat_id || registrasiSayaSendiri.perlu_pilih_ulang);

    const [mode, setMode] = useState(() => {
        if (bukaPilihUlang && perluPilihUlang.length) return 'pilih_ulang';
        if (sudahTerdaftar && bookingAwal.memakai_denah) return 'alokasi_teman';
        return 'daftar';
    });
    const [targetRegistrasi, setTargetRegistrasi] = useState(() => perluPilihUlang[0]?.id ?? null);
    const [kursiBaru, setKursiBaru] = useState(null);

    // Alokasi kursi multi-user (mandiri + teman terdaftar)
    const [daftarAlokasi, setDaftarAlokasi] = useState(() => {
        if (sayaPerluKursi && registrasiSayaSendiri) {
            return [{
                registration_id: registrasiSayaSendiri.id,
                name: user?.name ? `${user.name} (Anda)` : 'Anda',
                nim: user?.nim || '',
                seat_id: null,
                seat_code: null,
                isSelf: true,
            }];
        }
        return [];
    });
    const [aktifAlokasi, setAktifAlokasi] = useState(0);

    // State pencarian teman terdaftar tanpa kursi
    const [kataKunciTeman, setKataKunciTeman] = useState('');
    const [hasilCariTeman, setHasilCariTeman] = useState([]);
    const [mencariTeman, setMencariTeman] = useState(false);
    const [tampilDropdownTeman, setTampilDropdownTeman] = useState(false);
    const [errorCariTeman, setErrorCariTeman] = useState(null);

    const [peserta, setPeserta] = useState(() => [{ ...pesertaKosong(), name: user?.name || '', email: user?.email || '', identity: user?.nim || '', phone: user?.phone || '' }]);
    const [aktif, setAktif] = useState(0);
    const [paymentMethod, setPaymentMethod] = useState('QRIS');
    const [memproses, setMemproses] = useState(false);
    const [pemberitahuan, setPemberitahuan] = useState(null);
    const petaRef = useRef(null);

    const bisaPilih = booking.memakai_denah && booking.bisa_pilih;
    const statusBooking = STATUS_BOOKING[booking.status] || STATUS_BOOKING.ditutup;

    useEffect(() => {
        if (mode === 'pilih_ulang' || mode === 'alokasi_teman') {
            petaRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, [mode]);

    // Nilai terbaru untuk dipakai di callback pembaruan berkala.
    const pesertaRef = useRef(peserta);
    pesertaRef.current = peserta;
    const kursiBaruRef = useRef(kursiBaru);
    kursiBaruRef.current = kursiBaru;
    const daftarAlokasiRef = useRef(daftarAlokasi);
    daftarAlokasiRef.current = daftarAlokasi;

    // Debounced search teman terdaftar tanpa kursi
    useEffect(() => {
        if (!user || !sudahTerdaftar || !kataKunciTeman.trim()) {
            setHasilCariTeman([]);
            setMencariTeman(false);
            return;
        }

        const timer = setTimeout(async () => {
            setMencariTeman(true);
            setErrorCariTeman(null);
            try {
                const { data } = await window.axios.get(`/kegiatan/${kegiatan.id}/peserta-tanpa-kursi`, {
                    params: { search: kataKunciTeman.trim() },
                });
                const idSudahDipilih = new Set(daftarAlokasi.map((a) => a.registration_id));
                const filtered = Array.isArray(data) ? data.filter((p) => !idSudahDipilih.has(p.id)) : [];
                setHasilCariTeman(filtered);
                setTampilDropdownTeman(true);
            } catch (err) {
                setErrorCariTeman(err.response?.data?.message || 'Gagal mencari peserta.');
            } finally {
                setMencariTeman(false);
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [kataKunciTeman, kegiatan.id, sudahTerdaftar, user, daftarAlokasi]);

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

        const alokasiBaru = daftarAlokasiRef.current.map((a) => {
            if (a.seat_id && takTersedia(a.seat_id)) {
                hilang.push(a.seat_code);
                return { ...a, seat_id: null, seat_code: null };
            }
            return a;
        });
        if (hilang.length) setDaftarAlokasi(alokasiBaru);

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
        if (mode === 'alokasi_teman') {
            const hasil = {};
            daftarAlokasi.forEach((item, idx) => {
                if (item.seat_id) {
                    hasil[item.seat_id] = item.isSelf ? 'Anda' : (item.name ? item.name.split(' ')[0] : `T${idx + 1}`);
                }
            });
            return hasil;
        }
        return Object.fromEntries(peserta.filter((p) => p.seat_id).map((p) => [p.seat_id, `P${peserta.indexOf(p) + 1}`]));
    }, [mode, peserta, kursiBaru, daftarAlokasi]);

    const tampilan = (k) => {
        switch (k.status) {
            case 'tersedia':
                return {
                    kelas: GAYA_KURSI.tersedia,
                    status: bisaPilih ? 'tersedia' : 'tersedia, belum bisa dipilih',
                    bisaDipilih: bisaPilih && (
                        mode === 'pilih_ulang'
                            ? Boolean(targetRegistrasi)
                            : mode === 'alokasi_teman'
                                ? daftarAlokasi.length > 0
                                : !sudahTerdaftar && pendaftaran.dibuka
                    ),
                };
            case 'milik':
                return { kelas: GAYA_KURSI.milik, status: `kursi Anda/delegasi Anda (${k.pemegang || '-'})`, bisaDipilih: false, isi: '★' };
            case 'tidak_tersedia':
            case 'panitia':
            case 'diblokir':
                return { kelas: GAYA_KURSI.tidak_tersedia, status: 'tidak tersedia', bisaDipilih: false, isi: '×' };
            default:
                return { kelas: GAYA_KURSI.terisi, status: 'tidak tersedia', bisaDipilih: false };
        }
    };

    const pilihKursi = (k) => {
        setPemberitahuan(null);
        if (mode === 'pilih_ulang') {
            setKursiBaru((lama) => (lama?.id === k.id ? null : k));
            return;
        }

        if (mode === 'alokasi_teman') {
            if (!daftarAlokasi.length) {
                setPemberitahuan('Tambahkan teman atau pilih peserta yang ingin dialokasikan kursinya.');
                return;
            }
            const target = daftarAlokasi[aktifAlokasi] || daftarAlokasi[0];
            if (!target) return;

            const pemilikLain = daftarAlokasi.findIndex((item, i) => item.seat_id === k.id && i !== aktifAlokasi);
            if (pemilikLain !== -1) {
                const namaLain = daftarAlokasi[pemilikLain].name || `Peserta ${pemilikLain + 1}`;
                setPemberitahuan(`Kursi ${k.kode} sudah dipilih untuk ${namaLain}.`);
                return;
            }

            setDaftarAlokasi((lama) => lama.map((item, i) => {
                if (i !== aktifAlokasi) return item;
                return item.seat_id === k.id
                    ? { ...item, seat_id: null, seat_code: null }
                    : { ...item, seat_id: k.id, seat_code: k.kode };
            }));

            // Jika peserta aktif ini baru saja mendapatkan kursi, cari peserta berikutnya yang belum punya kursi
            const nextIndex = daftarAlokasi.findIndex((item, i) => i !== aktifAlokasi && !item.seat_id);
            if (nextIndex !== -1 && target.seat_id !== k.id) {
                setAktifAlokasi(nextIndex);
            }
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

    const tambahTemanAlokasi = (teman) => {
        const itemBaru = {
            registration_id: teman.id,
            name: teman.nama_lengkap,
            nim: teman.nim || '',
            seat_id: null,
            seat_code: null,
            isSelf: false,
        };
        const indexBaru = daftarAlokasi.length;
        setDaftarAlokasi((lama) => [...lama, itemBaru]);
        setAktifAlokasi(indexBaru);
        setKataKunciTeman('');
        setHasilCariTeman([]);
        setTampilDropdownTeman(false);
        setPemberitahuan(`Peserta ${teman.nama_lengkap} ditambahkan. Silakan pilih kursi di denah.`);
    };

    const hapusTemanAlokasi = (index) => {
        setDaftarAlokasi((lama) => lama.filter((_, i) => i !== index));
        setAktifAlokasi((lama) => Math.max(0, lama >= index ? lama - 1 : lama));
    };

    const simpanAlokasi = (e) => {
        e.preventDefault();
        if (!daftarAlokasi.length) {
            setPemberitahuan('Belum ada peserta yang dipilih untuk dialokasikan kursi.');
            return;
        }

        const belumPunya = daftarAlokasi.find((a) => !a.seat_id);
        if (belumPunya) {
            const idx = daftarAlokasi.indexOf(belumPunya);
            setAktifAlokasi(idx);
            setPemberitahuan(`Pilih kursi di denah untuk ${belumPunya.isSelf ? 'Anda' : belumPunya.name}.`);
            petaRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        const payload = {
            allocations: daftarAlokasi.map((a) => ({
                registration_id: a.registration_id,
                seat_id: a.seat_id,
            })),
        };

        router.post(`/kegiatan/${kegiatan.id}/pilih-kursi`, payload, {
            preserveScroll: true,
            onStart: () => setMemproses(true),
            onFinish: () => setMemproses(false),
            onError: (errs) => {
                const bentrok = Object.keys(errs).filter((k) => /^allocations\.\d+\.seat_id$/.test(k));
                if (bentrok.length) {
                    const idxs = bentrok.map((k) => Number(k.split('.')[1]));
                    setDaftarAlokasi((lama) => lama.map((a, i) => (idxs.includes(i) ? { ...a, seat_id: null, seat_code: null } : a)));
                    if (idxs[0] !== undefined) setAktifAlokasi(idxs[0]);
                    muatStatusKursi(bentrok.map((k) => errs[k]).join(' '));
                } else if (errs.allocations) {
                    setPemberitahuan(errs.allocations);
                } else {
                    const pesan = Object.values(errs).flat().join(' ');
                    setPemberitahuan(pesan || 'Terjadi kesalahan saat mengalokasikan kursi.');
                }
            },
        });
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
                        <div className="flex items-center gap-2">
                            {booking.memakai_denah && bisaPilih && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setMode('alokasi_teman');
                                        petaRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                    }}
                                    className="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs text-center transition-colors"
                                    data-btn-pilih-teman
                                >
                                    Pilihkan Kursi Teman
                                </button>
                            )}
                            <a href="/dashboard/kegiatan-saya" className="shrink-0 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs text-center">Lihat E-Tiket</a>
                        </div>
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
                                                    : mode === 'alokasi_teman'
                                                        ? daftarAlokasi[aktifAlokasi]
                                                            ? `Pilih kursi di denah untuk ${daftarAlokasi[aktifAlokasi].isSelf ? 'Anda' : daftarAlokasi[aktifAlokasi].name}.`
                                                            : 'Tambahkan teman atau klik peserta untuk memilihkan kursi.'
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
                                        { label: 'Pilihan Anda', kelas: GAYA_KURSI.dipilih },
                                        { label: 'Kursi saya / delegasi', kelas: GAYA_KURSI.milik },
                                        { label: 'Tidak tersedia', kelas: GAYA_KURSI.tidak_tersedia },
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

                    {/* Panel kanan: alokasi teman, pilih ulang, atau pendaftaran */}
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
                                <button type="button" onClick={() => { setMode('alokasi_teman'); setKursiBaru(null); }} className="w-full text-[11px] text-slate-400 hover:text-white">Beralih ke alokasi teman</button>
                            </form>
                        ) : (mode === 'alokasi_teman' || (sudahTerdaftar && booking.memakai_denah)) ? (
                            <form onSubmit={simpanAlokasi} className="bg-slate-900 border border-sky-500/40 rounded-3xl p-5 space-y-4 shadow-xl" data-form-alokasi-kursi>
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <h3 className="text-sm font-black text-white flex items-center gap-1.5">
                                            <span className="material-symbols-outlined text-sky-400 text-base" aria-hidden="true">group_add</span>
                                            Alokasi Kursi Teman Terdaftar
                                        </h3>
                                        <p className="text-[11px] text-slate-400 mt-0.5">
                                            Pilih kursi Anda dan pilihkan kursi untuk rekan terdaftar tanpa kursi.
                                        </p>
                                    </div>
                                    <span className="px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 text-[10px] font-bold shrink-0">
                                        Terdaftar
                                    </span>
                                </div>

                                {/* Status kursi Anda saat ini jika sudah punya kursi terkonfirmasi */}
                                {registrasiSayaSendiri?.kursi && !registrasiSayaSendiri.perlu_pilih_ulang && (
                                    <div className="p-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-between text-xs">
                                        <div>
                                            <span className="text-slate-400 text-[11px] block">Kursi Anda (Terkonfirmasi)</span>
                                            <span className="font-bold text-white">{registrasiSayaSendiri.nama_peserta}</span>
                                        </div>
                                        <span className="font-mono text-base font-black text-emerald-300" data-kursi-anda-aktif>
                                            {registrasiSayaSendiri.kursi}
                                        </span>
                                    </div>
                                )}

                                {/* Komponen Pencarian Teman Terdaftar */}
                                <div className="space-y-1 relative" data-komponen-cari-teman>
                                    <label htmlFor="cari-teman-input" className="block text-[11px] font-bold text-slate-300">
                                        Pilihkan Kursi untuk Teman Terdaftar
                                    </label>
                                    <div className="relative">
                                        <input
                                            id="cari-teman-input"
                                            type="text"
                                            value={kataKunciTeman}
                                            onChange={(e) => {
                                                setKataKunciTeman(e.target.value);
                                                setTampilDropdownTeman(true);
                                            }}
                                            onFocus={() => {
                                                if (hasilCariTeman.length > 0) setTampilDropdownTeman(true);
                                            }}
                                            placeholder="Cari nama atau NIM teman terdaftar..."
                                            className="w-full pl-9 pr-8 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs placeholder:text-slate-500 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition-all"
                                        />
                                        <span className="material-symbols-outlined absolute left-2.5 top-2.5 text-base text-slate-400 pointer-events-none" aria-hidden="true">
                                            person_search
                                        </span>
                                        {mencariTeman && (
                                            <span className="material-symbols-outlined absolute right-2.5 top-2.5 text-base text-sky-400 animate-spin" aria-hidden="true">
                                                progress_activity
                                            </span>
                                        )}
                                    </div>

                                    {/* Auto-suggest dropdown */}
                                    {tampilDropdownTeman && kataKunciTeman.trim().length > 0 && (
                                        <div className="absolute z-20 left-0 right-0 mt-1 max-h-56 overflow-y-auto rounded-2xl bg-slate-950 border border-slate-700 shadow-2xl p-1.5 space-y-1">
                                            {mencariTeman ? (
                                                <div className="p-3 text-center text-xs text-slate-400">Mencari peserta...</div>
                                            ) : hasilCariTeman.length > 0 ? (
                                                hasilCariTeman.map((teman) => (
                                                    <div
                                                        key={teman.id}
                                                        className="p-2 rounded-xl hover:bg-slate-900 border border-transparent hover:border-slate-800 flex items-center justify-between gap-2 text-xs transition-colors"
                                                    >
                                                        <div className="min-w-0">
                                                            <div className="font-bold text-white truncate">{teman.nama_lengkap}</div>
                                                            <div className="text-[10px] text-slate-400 flex items-center gap-2">
                                                                {teman.nim && <span>NIM: {teman.nim}</span>}
                                                                {teman.email && <span className="font-mono text-slate-500">{teman.email}</span>}
                                                            </div>
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onClick={() => tambahTemanAlokasi(teman)}
                                                            className="px-2.5 py-1 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-bold text-[11px] shrink-0"
                                                            data-btn-tambah-teman={teman.id}
                                                        >
                                                            + Pilih
                                                        </button>
                                                    </div>
                                                ))
                                            ) : (
                                                <div className="p-3 text-center text-xs text-slate-400">
                                                    {errorCariTeman || 'Tidak ada peserta tanpa kursi yang cocok.'}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>

                                {/* Daftar Alokasi Kursi (Cards) */}
                                <div className="space-y-2" data-daftar-alokasi>
                                    <div className="text-[11px] font-bold text-slate-400">
                                        Daftar Kursi yang Dipilihkan ({daftarAlokasi.length} peserta):
                                    </div>
                                    {daftarAlokasi.length === 0 ? (
                                        <div className="p-4 rounded-2xl bg-slate-950 border border-slate-800/80 text-center text-slate-500 text-xs">
                                            Belum ada teman yang ditambahkan. Gunakan kolom pencarian di atas untuk menambahkan teman terdaftar.
                                        </div>
                                    ) : (
                                        daftarAlokasi.map((item, idx) => (
                                            <div
                                                key={item.registration_id}
                                                onClick={() => setAktifAlokasi(idx)}
                                                className={`p-3 rounded-2xl border cursor-pointer transition-all ${
                                                    aktifAlokasi === idx
                                                        ? 'bg-slate-950 border-sky-400 ring-2 ring-sky-400/30'
                                                        : 'bg-slate-950/70 border-slate-800 hover:border-slate-700'
                                                }`}
                                                data-alokasi-peserta={idx}
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <div className="min-w-0">
                                                        <div className="text-xs font-bold text-white flex items-center gap-1.5">
                                                            <span className="material-symbols-outlined text-sm text-sky-400" aria-hidden="true">
                                                                {item.isSelf ? 'person' : 'person_pin'}
                                                            </span>
                                                            <span className="truncate">{item.name}</span>
                                                            {item.nim && <span className="text-[10px] text-slate-400 font-mono">({item.nim})</span>}
                                                        </div>
                                                        <div className="text-xs font-mono font-black text-sky-300 mt-1" data-label-kursi-alokasi={idx}>
                                                            {item.isSelf
                                                                ? `Kursi Anda: ${item.seat_code || 'Belum dipilih (klik denah)'}`
                                                                : `Kursi ${item.name}: ${item.seat_code || 'Belum dipilih (klik denah)'}`}
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-2 shrink-0">
                                                        {aktifAlokasi === idx ? (
                                                            <span className="px-2 py-0.5 rounded-full bg-sky-500/20 text-sky-300 border border-sky-500/30 text-[10px] font-bold">
                                                                Aktif
                                                            </span>
                                                        ) : (
                                                            <span className="text-[10px] text-slate-500">Pilih</span>
                                                        )}
                                                        {!item.isSelf && (
                                                            <button
                                                                type="button"
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    hapusTemanAlokasi(idx);
                                                                }}
                                                                className="p-1 rounded-lg text-rose-400 hover:bg-rose-500/20 hover:text-rose-200 transition-colors"
                                                                title="Hapus teman dari daftar alokasi"
                                                                aria-label={`Hapus ${item.name}`}
                                                            >
                                                                <span className="material-symbols-outlined text-sm" aria-hidden="true">close</span>
                                                            </button>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>

                                {!bisaPilih && (
                                    <p className="text-[11px] text-amber-300">
                                        {keteranganBooking(booking.status, booking.buka, booking.tutup)}
                                    </p>
                                )}

                                {daftarAlokasi.length > 0 && (
                                    <button
                                        type="submit"
                                        disabled={memproses || !daftarAlokasi.every((a) => a.seat_id) || !bisaPilih}
                                        className="w-full py-3.5 rounded-2xl bg-gradient-to-r from-sky-600 to-teal-500 hover:from-sky-500 hover:to-teal-400 disabled:opacity-40 text-white text-xs font-black uppercase tracking-wider transition-all shadow-lg"
                                        data-btn-submit-alokasi
                                    >
                                        {memproses ? 'Menyimpan alokasi…' : `Kunci Kursi (${daftarAlokasi.filter((a) => a.seat_id).length}/${daftarAlokasi.length} Kursi)`}
                                    </button>
                                )}

                                <div className="pt-2 border-t border-slate-800/80 flex items-center justify-between text-[11px]">
                                    <a href="/dashboard/kegiatan-saya" className="text-slate-400 hover:text-emerald-400 transition-colors">
                                        ← Kembali ke Kegiatan Saya
                                    </a>
                                    {perluPilihUlang.length > 0 && (
                                        <button
                                            type="button"
                                            onClick={() => setMode('pilih_ulang')}
                                            className="text-amber-400 hover:text-amber-300 font-bold"
                                        >
                                            Mode Pilih Ulang ({perluPilihUlang.length})
                                        </button>
                                    )}
                                </div>
                            </form>
                        ) : !user ? (
                            <div className="bg-slate-900 border border-slate-800 rounded-3xl p-5 space-y-3 text-center">
                                <p className="text-xs text-slate-300">Masuk untuk mendaftar dan memilih kursi.</p>
                                <a href="/masuk" className="block w-full py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black">Masuk</a>
                            </div>
                        ) : sudahTerdaftar ? (
                            <div className="bg-slate-900 border border-slate-800 rounded-3xl p-5 text-xs text-slate-300 space-y-2">
                                <p>Anda sudah terdaftar di kegiatan ini. Lihat e-tiket Anda di halaman Kegiatan Saya.</p>
                                <a href="/dashboard/kegiatan-saya" className="inline-block px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold">
                                    Buka Kegiatan Saya
                                </a>
                            </div>
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
