import React, { useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import ParticipantLayout from '@/Layouts/ParticipantLayout';

export default function KegiatanShow({
    kegiatan = {},
    myRegistration = null,
    paymentMethods = {}
}) {
    const { auth = {} } = usePage().props;
    const user = auth.user;

    // Delegasi State (default: 1 delegasi = user saat ini)
    const [delegates, setDelegates] = useState([
        {
            name: user?.name || '',
            email: user?.email || '',
            identity: user?.nim || '',
            phone: user?.phone || '',
            seat_id: null,
            seat_code: null,
        },
    ]);
    const [activeDelegateIndex, setActiveDelegateIndex] = useState(0);
    const [paymentMethod, setPaymentMethod] = useState('QRIS');
    const [submitting, setSubmitting] = useState(false);

    // Kelompokkan kursi per baris
    const seatsByRow = {};
    if (kegiatan.seats && kegiatan.seats.length > 0) {
        kegiatan.seats.forEach((s) => {
            if (!seatsByRow[s.row_label]) {
                seatsByRow[s.row_label] = [];
            }
            seatsByRow[s.row_label].push(s);
        });
    }

    const rows = Object.keys(seatsByRow).sort();

    // Cek apakah kursi dipilih oleh salah satu delegasi
    const isSeatSelectedByDelegate = (seatId) => {
        return delegates.some((d) => d.seat_id === seatId);
    };

    const handleSeatClick = (seat) => {
        if (seat.status !== 'available') return;

        // Cek jika kursi sudah dipilih oleh delegasi lain di form
        const existingHolderIndex = delegates.findIndex((d) => d.seat_id === seat.id);
        if (existingHolderIndex !== -1 && existingHolderIndex !== activeDelegateIndex) {
            alert(`Kursi ${seat.seat_code} sudah dipilih untuk delegasi ${existingHolderIndex + 1}.`);
            return;
        }

        // Assign seat ke active delegate
        const updated = [...delegates];
        if (updated[activeDelegateIndex].seat_id === seat.id) {
            // Deselect
            updated[activeDelegateIndex].seat_id = null;
            updated[activeDelegateIndex].seat_code = null;
        } else {
            updated[activeDelegateIndex].seat_id = seat.id;
            updated[activeDelegateIndex].seat_code = seat.seat_code;
        }
        setDelegates(updated);
    };

    const addDelegate = () => {
        if (delegates.length >= 10) {
            alert('Maksimal pemesanan adalah 10 delegasi per transaksi.');
            return;
        }
        setDelegates([
            ...delegates,
            { name: '', email: '', identity: '', phone: '', seat_id: null, seat_code: null },
        ]);
        setActiveDelegateIndex(delegates.length);
    };

    const removeDelegate = (index) => {
        if (delegates.length <= 1) return;
        const updated = delegates.filter((_, i) => i !== index);
        setDelegates(updated);
        setActiveDelegateIndex(Math.max(0, index - 1));
    };

    const handleDelegateChange = (index, field, value) => {
        const updated = [...delegates];
        updated[index][field] = value;
        setDelegates(updated);
    };

    const handleCheckout = (e) => {
        e.preventDefault();
        if (!user) {
            window.location.href = '/masuk';
            return;
        }

        // Validasi tiap delegasi
        for (let i = 0; i < delegates.length; i++) {
            const d = delegates[i];
            if (!d.name.trim()) {
                alert(`Nama lengkap peserta pada delegasi ${i + 1} wajib diisi.`);
                return;
            }
            if (kegiatan.has_seat_selection && !d.seat_id) {
                alert(`Silakan pilih kursi pada denah untuk delegasi ${i + 1} (${d.name || 'Peserta ' + (i + 1)}).`);
                return;
            }
        }

        setSubmitting(true);
        router.post(`/kegiatan/${kegiatan.id}/checkout`, {
            delegates: delegates,
            payment_method: paymentMethod,
        }, {
            onError: () => setSubmitting(false),
        });
    };

    const isFree = Number(kegiatan.biaya) === 0;
    const totalAmount = delegates.length * Number(kegiatan.biaya);

    return (
        <ParticipantLayout title={kegiatan.nama_kegiatan}>
            <div className="space-y-8">
                {/* Hero / Event Header */}
                <div className="rounded-3xl bg-slate-900 border border-slate-800 p-6 md:p-8 overflow-hidden relative shadow-2xl">
                    <div className="absolute top-0 right-0 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                    <div className="flex flex-col md:flex-row gap-6 items-start justify-between relative z-10">
                        <div className="space-y-3 max-w-2xl">
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="px-3 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                    {kegiatan.kategori}
                                </span>
                                <span className="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-slate-800 text-slate-300">
                                    {kegiatan.tipe_kegiatan}
                                </span>
                                {kegiatan.target_peserta === 'kipk_only' && (
                                    <span className="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-amber-500/15 text-amber-400 border border-amber-500/30">
                                        Khusus Mahasiswa KIP-K
                                    </span>
                                )}
                            </div>

                            <h1 className="text-2xl sm:text-3xl font-black text-white tracking-tight">
                                {kegiatan.nama_kegiatan}
                            </h1>

                            {kegiatan.narasumber && (
                                <p className="text-sm font-semibold text-emerald-400 flex items-center gap-2">
                                    <span className="material-symbols-outlined text-base">mic</span>
                                    Narasumber: {kegiatan.narasumber}
                                </p>
                            )}

                            <p className="text-xs text-slate-300 leading-relaxed">
                                {kegiatan.deskripsi || 'Kegiatan resmi diselenggarakan oleh SEMADIKSI Universitas Nahdlatul Ulama Surabaya.'}
                            </p>

                            {/* Details Grid */}
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3">
                                <div className="p-3 rounded-2xl bg-slate-950/70 border border-slate-800/80">
                                    <div className="text-[10px] text-slate-400 font-bold uppercase">Waktu Pelaksanaan</div>
                                    <div className="text-xs font-bold text-white mt-1">
                                        {kegiatan.waktu_mulai ? new Date(kegiatan.waktu_mulai).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'} WIB
                                    </div>
                                </div>
                                <div className="p-3 rounded-2xl bg-slate-950/70 border border-slate-800/80">
                                    <div className="text-[10px] text-slate-400 font-bold uppercase">Lokasi / Gedung</div>
                                    <div className="text-xs font-bold text-white mt-1 truncate" title={kegiatan.lokasi_nama}>
                                        {kegiatan.lokasi_nama}
                                    </div>
                                    {kegiatan.lokasi_detail && (
                                        <div className="text-[10px] text-slate-400 truncate">{kegiatan.lokasi_detail}</div>
                                    )}
                                </div>
                                <div className="p-3 rounded-2xl bg-slate-950/70 border border-slate-800/80">
                                    <div className="text-[10px] text-slate-400 font-bold uppercase">Biaya Pendaftaran</div>
                                    <div className="text-xs font-black text-emerald-400 mt-1">
                                        {isFree ? 'GRATIS' : `Rp ${Number(kegiatan.biaya).toLocaleString('id-ID')}`}
                                    </div>
                                </div>
                                <div className="p-3 rounded-2xl bg-slate-950/70 border border-slate-800/80">
                                    <div className="text-[10px] text-slate-400 font-bold uppercase">Kuota Tersisa</div>
                                    <div className="text-xs font-bold text-white mt-1">
                                        {Math.max(0, kegiatan.kuota_total - kegiatan.kuota_terisi)} dari {kegiatan.kuota_total} Kursi
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Guide File Download Card */}
                        {kegiatan.panduan_file_url && (
                            <div className="w-full md:w-64 p-5 rounded-2xl bg-slate-950 border border-slate-800 space-y-3 text-center">
                                <div className="w-12 h-12 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center mx-auto">
                                    <span className="material-symbols-outlined text-2xl">picture_as_pdf</span>
                                </div>
                                <div>
                                    <div className="text-xs font-bold text-white">Panduan Resmi Kegiatan</div>
                                    <div className="text-[10px] text-slate-400 mt-0.5">Dokumen panduan, susunan acara, &amp; tata tertib.</div>
                                </div>
                                <a
                                    href={kegiatan.panduan_file_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="block w-full py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs transition-colors"
                                >
                                    Unduh PDF Resmi
                                </a>
                            </div>
                        )}
                    </div>
                </div>

                {/* Status jika user sudah terdaftar */}
                {myRegistration && (
                    <div className="p-6 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <span className="material-symbols-outlined text-3xl text-emerald-400">confirmation_number</span>
                            <div>
                                <div className="text-sm font-bold text-white">Anda telah terdaftar pada kegiatan ini!</div>
                                <div className="text-xs text-emerald-300 font-mono mt-0.5">Nomor Tiket: {myRegistration.nomor_tiket}</div>
                            </div>
                        </div>
                        <a
                            href="/dashboard/kegiatan-saya"
                            className="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/20"
                        >
                            Lihat E-Tiket Saya
                        </a>
                    </div>
                )}

                {/* Booking Section */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Left: Interactive Seat Map (2 Cols) */}
                    <div className="lg:col-span-2 space-y-6">
                        {kegiatan.has_seat_selection ? (
                            <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-6">
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-800 pb-4">
                                    <div>
                                        <h3 className="text-base font-black text-white flex items-center gap-2">
                                            <span className="material-symbols-outlined text-emerald-400">chair</span>
                                            Denah Kursi Interaktif
                                        </h3>
                                        <p className="text-xs text-slate-400 mt-0.5">
                                            Pilih kursi untuk <span className="text-emerald-400 font-bold">{delegates[activeDelegateIndex]?.name || `Delegasi ${activeDelegateIndex + 1}`}</span>.
                                        </p>
                                    </div>

                                    {/* Legend */}
                                    <div className="flex items-center gap-3 text-[10px] font-bold text-slate-400">
                                        <div className="flex items-center gap-1">
                                            <div className="w-3.5 h-3.5 rounded bg-emerald-500/20 border border-emerald-500"></div>
                                            <span>Reguler</span>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <div className="w-3.5 h-3.5 rounded bg-amber-500/20 border border-amber-500"></div>
                                            <span>VIP</span>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <div className="w-3.5 h-3.5 rounded bg-sky-500 border border-sky-400"></div>
                                            <span>Dipilih</span>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <div className="w-3.5 h-3.5 rounded bg-rose-500/30 border border-rose-500/50"></div>
                                            <span>Terisi</span>
                                        </div>
                                    </div>
                                </div>

                                {/* Stage Screen */}
                                <div className="text-center">
                                    <div className="w-full max-w-lg mx-auto py-2.5 rounded-xl bg-gradient-to-r from-emerald-600/30 via-teal-500/30 to-emerald-600/30 border border-emerald-500/40 text-emerald-300 font-black text-xs tracking-widest uppercase shadow-inner">
                                        PANGGUNG / LAYAR UTAMA
                                    </div>
                                    <div className="text-[10px] text-slate-500 mt-1">Arah Hadap Peserta</div>
                                </div>

                                {/* Seats Grid */}
                                <div className="space-y-2.5 overflow-x-auto py-4">
                                    {rows.map((r) => {
                                        const rowSeats = seatsByRow[r] || [];
                                        const half = Math.ceil(rowSeats.length / 2);
                                        const leftSide = rowSeats.slice(0, half);
                                        const rightSide = rowSeats.slice(half);

                                        return (
                                            <div key={r} className="flex items-center justify-center gap-3 min-w-[500px]">
                                                <span className="w-6 text-center text-xs font-black text-slate-400">{r}</span>

                                                {/* Left Block */}
                                                <div className="flex items-center gap-1.5">
                                                    {leftSide.map((seat) => {
                                                        const isSelected = delegates[activeDelegateIndex]?.seat_id === seat.id;
                                                        const isTakenByOtherDelegate = isSeatSelectedByDelegate(seat.id) && !isSelected;
                                                        const isBooked = seat.status === 'booked';
                                                        const isReserved = seat.status === 'reserved_panitia';
                                                        const isVip = seat.tier === 'vip';

                                                        let seatClass = 'bg-emerald-500/20 border-emerald-500/50 text-emerald-300 hover:bg-emerald-500/40 cursor-pointer';
                                                        if (isVip) {
                                                            seatClass = 'bg-amber-500/20 border-amber-500/50 text-amber-300 hover:bg-amber-500/40 cursor-pointer';
                                                        }
                                                        if (isBooked || isTakenByOtherDelegate) {
                                                            seatClass = 'bg-rose-500/20 border-rose-500/40 text-rose-400/50 cursor-not-allowed opacity-60';
                                                        }
                                                        if (isReserved) {
                                                            seatClass = 'bg-slate-800 border-slate-700 text-slate-500 cursor-not-allowed opacity-50';
                                                        }
                                                        if (isSelected) {
                                                            seatClass = 'bg-sky-500 border-sky-300 text-white font-black ring-2 ring-sky-400 scale-110 shadow-lg shadow-sky-500/40';
                                                        }

                                                        return (
                                                            <button
                                                                key={seat.id}
                                                                type="button"
                                                                onClick={() => handleSeatClick(seat)}
                                                                disabled={isBooked || isReserved || isTakenByOtherDelegate}
                                                                title={`Kursi ${seat.seat_code} (${seat.tier.toUpperCase()}) - ${seat.status}`}
                                                                className={`w-7 h-7 rounded-lg border text-[10px] font-bold flex items-center justify-center transition-all ${seatClass}`}
                                                            >
                                                                {seat.col_number}
                                                            </button>
                                                        );
                                                    })}
                                                </div>

                                                {/* Center Aisle */}
                                                <div className="w-8 flex items-center justify-center">
                                                    <div className="w-0.5 h-6 bg-slate-800"></div>
                                                </div>

                                                {/* Right Block */}
                                                <div className="flex items-center gap-1.5">
                                                    {rightSide.map((seat) => {
                                                        const isSelected = delegates[activeDelegateIndex]?.seat_id === seat.id;
                                                        const isTakenByOtherDelegate = isSeatSelectedByDelegate(seat.id) && !isSelected;
                                                        const isBooked = seat.status === 'booked';
                                                        const isReserved = seat.status === 'reserved_panitia';
                                                        const isVip = seat.tier === 'vip';

                                                        let seatClass = 'bg-emerald-500/20 border-emerald-500/50 text-emerald-300 hover:bg-emerald-500/40 cursor-pointer';
                                                        if (isVip) {
                                                            seatClass = 'bg-amber-500/20 border-amber-500/50 text-amber-300 hover:bg-amber-500/40 cursor-pointer';
                                                        }
                                                        if (isBooked || isTakenByOtherDelegate) {
                                                            seatClass = 'bg-rose-500/20 border-rose-500/40 text-rose-400/50 cursor-not-allowed opacity-60';
                                                        }
                                                        if (isReserved) {
                                                            seatClass = 'bg-slate-800 border-slate-700 text-slate-500 cursor-not-allowed opacity-50';
                                                        }
                                                        if (isSelected) {
                                                            seatClass = 'bg-sky-500 border-sky-300 text-white font-black ring-2 ring-sky-400 scale-110 shadow-lg shadow-sky-500/40';
                                                        }

                                                        return (
                                                            <button
                                                                key={seat.id}
                                                                type="button"
                                                                onClick={() => handleSeatClick(seat)}
                                                                disabled={isBooked || isReserved || isTakenByOtherDelegate}
                                                                title={`Kursi ${seat.seat_code} (${seat.tier.toUpperCase()}) - ${seat.status}`}
                                                                className={`w-7 h-7 rounded-lg border text-[10px] font-bold flex items-center justify-center transition-all ${seatClass}`}
                                                            >
                                                                {seat.col_number}
                                                            </button>
                                                        );
                                                    })}
                                                </div>

                                                <span className="w-6 text-center text-xs font-black text-slate-400">{r}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ) : (
                            <div className="bg-slate-900 border border-slate-800 rounded-3xl p-8 text-center space-y-3">
                                <span className="material-symbols-outlined text-4xl text-emerald-400">airline_seat_recline_normal</span>
                                <h3 className="text-base font-black text-white">Kegiatan Bebas Kursi (General Admission)</h3>
                                <p className="text-xs text-slate-400 max-w-md mx-auto">
                                    Kegiatan ini tidak memerlukan reservasi denah kursi khusus. Peserta dipersilakan menempati kursi sesuai kedatangan pada hari pelaksanaan.
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Right: Multi-Delegation Checkout Panel (1 Col) */}
                    <div className="space-y-6">
                        <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-5">
                            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                                <div>
                                    <h3 className="text-sm font-black text-white">Pendaftaran &amp; Delegasi</h3>
                                    <p className="text-[11px] text-slate-400">Daftarkan diri sendiri atau bersama tim/rekan.</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={addDelegate}
                                    className="px-2.5 py-1 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-400 text-xs font-bold border border-emerald-500/30 flex items-center gap-1 transition-colors"
                                >
                                    <span className="material-symbols-outlined text-sm">person_add</span>
                                    + Tambah
                                </button>
                            </div>

                            {/* Delegate Selector Tabs */}
                            <div className="flex items-center gap-2 overflow-x-auto pb-1">
                                {delegates.map((d, idx) => (
                                    <button
                                        key={idx}
                                        type="button"
                                        onClick={() => setActiveDelegateIndex(idx)}
                                        className={`px-3 py-1.5 rounded-xl text-xs font-bold shrink-0 transition-all flex items-center gap-1.5 ${
                                            activeDelegateIndex === idx
                                                ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30'
                                                : 'bg-slate-950 border border-slate-800 text-slate-400 hover:text-white'
                                        }`}
                                    >
                                        <span>Peserta {idx + 1}</span>
                                        {d.seat_code && (
                                            <span className="px-1.5 py-0.2 rounded bg-black/40 text-[9px] font-mono text-emerald-300">
                                                {d.seat_code}
                                            </span>
                                        )}
                                    </button>
                                ))}
                            </div>

                            {/* Active Delegate Form */}
                            <div className="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-3 text-xs">
                                <div className="flex items-center justify-between">
                                    <span className="font-extrabold text-white">Data Peserta {activeDelegateIndex + 1}</span>
                                    {delegates.length > 1 && (
                                        <button
                                            type="button"
                                            onClick={() => removeDelegate(activeDelegateIndex)}
                                            className="text-rose-400 hover:text-rose-300 text-[11px] font-bold"
                                        >
                                            Hapus Peserta
                                        </button>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-[11px] font-bold text-slate-400 mb-1">Nama Lengkap *</label>
                                    <input
                                        type="text"
                                        value={delegates[activeDelegateIndex]?.name || ''}
                                        onChange={(e) => handleDelegateChange(activeDelegateIndex, 'name', e.target.value)}
                                        placeholder="Nama sesuai identitas"
                                        className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                        required
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <label className="block text-[11px] font-bold text-slate-400 mb-1">Email</label>
                                        <input
                                            type="email"
                                            value={delegates[activeDelegateIndex]?.email || ''}
                                            onChange={(e) => handleDelegateChange(activeDelegateIndex, 'email', e.target.value)}
                                            placeholder="Untuk kirim tiket"
                                            className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[11px] font-bold text-slate-400 mb-1">NIM / No Identitas</label>
                                        <input
                                            type="text"
                                            value={delegates[activeDelegateIndex]?.identity || ''}
                                            onChange={(e) => handleDelegateChange(activeDelegateIndex, 'identity', e.target.value)}
                                            placeholder="NIM jika mahasiswa"
                                            className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs"
                                        />
                                    </div>
                                </div>

                                {kegiatan.has_seat_selection && (
                                    <div className="p-3 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between">
                                        <div className="text-[11px] text-slate-400">Kursi Dipilih:</div>
                                        <div className="font-mono text-sm font-black text-emerald-400">
                                            {delegates[activeDelegateIndex]?.seat_code || (
                                                <span className="text-slate-500 text-xs italic font-sans">Belum pilih</span>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Payment Methods (if paid) */}
                            {!isFree && (
                                <div className="space-y-3">
                                    <label className="block text-xs font-bold text-slate-300">Metode Pembayaran (Tripay)</label>
                                    <div className="grid grid-cols-2 gap-2">
                                        {Object.entries(paymentMethods).map(([k, m]) => (
                                            <button
                                                key={k}
                                                type="button"
                                                onClick={() => setPaymentMethod(m.code)}
                                                className={`p-3 rounded-2xl border text-left transition-all ${
                                                    paymentMethod === m.code
                                                        ? 'bg-emerald-500/15 border-emerald-500 text-white font-bold'
                                                        : 'bg-slate-950 border-slate-800 text-slate-400 hover:border-slate-700'
                                                }`}
                                            >
                                                <div className="text-xs font-bold text-white">{m.code}</div>
                                                <div className="text-[10px] text-slate-400 truncate">{m.label}</div>
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Cost Summary & Checkout Button */}
                            <div className="pt-3 border-t border-slate-800 space-y-3">
                                <div className="space-y-1.5 text-xs">
                                    <div className="flex justify-between text-slate-400">
                                        <span>Jumlah Peserta</span>
                                        <span className="text-white font-bold">{delegates.length} Orang</span>
                                    </div>
                                    <div className="flex justify-between text-slate-400">
                                        <span>Tarif per Peserta</span>
                                        <span className="text-white font-bold">
                                            {isFree ? 'Gratis' : `Rp ${Number(kegiatan.biaya).toLocaleString('id-ID')}`}
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm font-black text-white pt-2 border-t border-slate-800">
                                        <span>Total Pembayaran</span>
                                        <span className="text-emerald-400">
                                            {isFree ? 'Rp 0 (Gratis)' : `Rp ${totalAmount.toLocaleString('id-ID')}`}
                                        </span>
                                    </div>
                                </div>

                                <button
                                    onClick={handleCheckout}
                                    disabled={submitting}
                                    className="w-full py-3.5 rounded-2xl bg-gradient-to-r from-emerald-600 via-teal-500 to-emerald-600 hover:from-emerald-500 hover:to-teal-400 text-white text-xs font-black tracking-wider uppercase shadow-xl shadow-emerald-600/30 transition-all flex items-center justify-center gap-2"
                                >
                                    <span className="material-symbols-outlined text-sm">lock</span>
                                    {submitting ? 'Memproses Pendaftaran...' : (isFree ? 'Daftar Sekarang (Gratis)' : 'Bayar & Kunci Kursi')}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </ParticipantLayout>
    );
}
