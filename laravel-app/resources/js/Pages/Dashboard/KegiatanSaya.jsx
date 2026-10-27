import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import ParticipantLayout from '@/Layouts/ParticipantLayout';
import { formatWib } from '@/lib/waktuWib';

export default function KegiatanSaya({
    title = 'Kegiatan Saya & E-Tiket',
    enterpriseRegistrations = [],
    legacyRegistrations = {}
}) {
    const [activeTicket, setActiveTicket] = useState(null);
    const [uploadModalOpen, setUploadModalOpen] = useState(false);
    const [activeRegForUpload, setActiveRegForUpload] = useState(null);
    const [submissionTitle, setSubmissionTitle] = useState('');
    const [submissionDesc, setSubmissionDesc] = useState('');
    const [submissionFile, setSubmissionFile] = useState(null);
    const [uploading, setUploading] = useState(false);

    // Modal kredensial rapat daring
    const [meetingModal, setMeetingModal] = useState({ open: false, data: null, error: null });
    const [joiningRegId, setJoiningRegId] = useState(null);
    const [copiedKey, setCopiedKey] = useState(null);

    const handleGabung = async (reg) => {
        setJoiningRegId(reg.id);
        try {
            const response = await window.axios.post(`/kegiatan/${reg.kegiatan_id}/gabung`);
            const data = response.data;
            if (data && data.success) {
                // Perbarui status lokal pada objek registrasi
                reg.sudah_gabung = true;
                reg.jumlah_gabung = data.jumlah;
                if (data.pertama_pada && !reg.pertama_gabung_pada) {
                    reg.pertama_gabung_pada = data.pertama_pada;
                }

                // Buka tautan rapat di tab baru
                if (data.join_url) {
                    window.open(data.join_url, '_blank');
                }

                // Tampilkan modal info kredensial (Meeting ID dan Passcode untuk disalin)
                setMeetingModal({
                    open: true,
                    data: data,
                    kegiatanName: reg.kegiatan?.nama_kegiatan || 'Pertemuan Daring',
                    error: null,
                });
            } else {
                alert(data?.message || 'Gagal mengakses ruang pertemuan daring.');
            }
        } catch (err) {
            const msg = err.response?.data?.message || 'Akses ruang pertemuan belum dibuka atau tidak dapat diakses saat ini.';
            alert(msg);
        } finally {
            setJoiningRegId(null);
        }
    };

    const copyText = (text, key) => {
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            setCopiedKey(key);
            setTimeout(() => setCopiedKey(null), 2000);
        });
    };

    const openTicketModal = (reg) => {
        setActiveTicket(reg);
    };

    const openUploadModal = (reg) => {
        setActiveRegForUpload(reg);
        const existingSub = reg.submission;
        setSubmissionTitle(existingSub?.title || '');
        setSubmissionDesc(existingSub?.description || '');
        setSubmissionFile(null);
        setUploadModalOpen(true);
    };

    const handleUploadKarya = (e) => {
        e.preventDefault();
        if (!activeRegForUpload || !submissionFile) {
            alert('Silakan pilih berkas karya yang akan diunggah.');
            return;
        }

        setUploading(true);
        const formData = new FormData();
        formData.append('title', submissionTitle);
        formData.append('description', submissionDesc);
        formData.append('submission_file', submissionFile);

        router.post(`/dashboard/kegiatan-saya/${activeRegForUpload.id}/upload-karya`, formData, {
            onSuccess: () => {
                setUploadModalOpen(false);
                setUploading(false);
            },
            onError: () => setUploading(false),
        });
    };

    const handlePrintTicket = () => {
        window.print();
    };

    return (
        <ParticipantLayout title={title}>
            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <h2 className="text-2xl font-black text-white flex items-center gap-2">
                            <span className="material-symbols-outlined text-emerald-400">confirmation_number</span>
                            Kegiatan Saya &amp; E-Tiket Digital
                        </h2>
                        <p className="text-xs text-slate-400 mt-1">
                            Akses tiket resmi, nomor kursi reservasi, status pembayaran, serta pengumpulan karya lomba Anda.
                        </p>
                    </div>

                    <a
                        href="/dashboard"
                        className="px-4 py-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-300 hover:text-white text-xs font-bold transition-colors"
                    >
                        Kembali ke Dashboard
                    </a>
                </div>

                {/* Enterprise Registrations List */}
                <div className="space-y-4">
                    <h3 className="text-sm font-black text-emerald-400 tracking-wider uppercase">
                        Pendaftaran Kegiatan Terbaru
                    </h3>

                    {enterpriseRegistrations && enterpriseRegistrations.length > 0 ? (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {enterpriseRegistrations.map((reg) => {
                                const kegiatan = reg.kegiatan || {};
                                const seat = reg.seat;
                                const isPaidOrFree = reg.payment_status === 'paid' || reg.payment_status === 'free';
                                const isPending = reg.payment_status === 'pending';
                                const qrCodeUrl = `/tiket/${encodeURIComponent(reg.nomor_tiket)}/qr`;

                                return (
                                    <div
                                        key={reg.id}
                                        className="rounded-3xl bg-slate-900 border border-slate-800 p-6 shadow-xl space-y-4 hover:border-slate-700 transition-all flex flex-col justify-between"
                                    >
                                        <div className="space-y-3">
                                            <div className="flex items-center justify-between">
                                                <span className="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-slate-800 text-slate-300 font-mono">
                                                    {reg.nomor_tiket}
                                                </span>
                                                <span className={`px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider ${
                                                    isPaidOrFree
                                                        ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
                                                        : isPending
                                                        ? 'bg-amber-500/15 text-amber-400 border border-amber-500/30'
                                                        : 'bg-rose-500/15 text-rose-400 border border-rose-500/30'
                                                }`}>
                                                    {isPaidOrFree ? 'Tiket Aktif' : (isPending ? 'Menunggu Bayar' : reg.payment_status)}
                                                </span>
                                            </div>

                                            <div>
                                                <h4 className="text-base font-black text-white">{kegiatan.nama_kegiatan}</h4>
                                                <div className="text-[11px] text-emerald-400 font-semibold mt-0.5">
                                                    {kegiatan.kategori}
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 gap-2 text-xs pt-1">
                                                <div className={`p-2.5 rounded-xl border ${reg.perlu_pilih_ulang ? 'bg-amber-500/10 border-amber-400/50' : 'bg-slate-950 border-slate-800/80'}`} data-kursi-tiket={reg.id}>
                                                    <div className="text-[10px] text-slate-500 font-bold uppercase">Nomor Kursi</div>
                                                    {reg.perlu_pilih_ulang ? (
                                                        <div className="mt-0.5 text-[11px] font-bold text-amber-300 flex items-center gap-1" data-perlu-pilih-ulang>
                                                            <span className="material-symbols-outlined text-sm">sync_problem</span>
                                                            Perlu pilih ulang kursi
                                                        </div>
                                                    ) : (
                                                        <div className="font-mono font-black text-emerald-400 mt-0.5 text-sm">
                                                            {seat ? seat.seat_code : 'Tanpa kursi bernomor'}
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="p-2.5 rounded-xl bg-slate-950 border border-slate-800/80">
                                                    <div className="text-[10px] text-slate-500 font-bold uppercase">Peserta</div>
                                                    <div className="font-bold text-white truncate mt-0.5">
                                                        {reg.participant_name}
                                                    </div>
                                                </div>
                                            </div>

                                            {reg.perlu_pilih_ulang && (
                                                reg.bisa_pilih_ulang ? (
                                                    <a
                                                        href={`/kegiatan/${reg.kegiatan_id}?pilih_ulang=1`}
                                                        className="flex items-center justify-center gap-1.5 w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-black"
                                                        data-tombol-pilih-ulang={reg.id}
                                                    >
                                                        <span className="material-symbols-outlined text-sm">event_seat</span>
                                                        Pilih ulang kursi
                                                    </a>
                                                ) : (
                                                    <p className="text-[11px] text-amber-200/90">
                                                        Ruangan kegiatan berubah. Kursi baru untuk tiket ini akan dipilihkan oleh pemesan tiket.
                                                    </p>
                                                )
                                            )}

                                            <div className="text-[11px] text-slate-400 space-y-1">
                                                <div className="flex items-center gap-1.5">
                                                    <span className="material-symbols-outlined text-xs text-slate-500">schedule</span>
                                                    <span>{kegiatan.tanggal_mulai ? `${formatWib(kegiatan.tanggal_mulai)} WIB` : '-'}</span>
                                                </div>
                                                <div className="flex items-center gap-1.5">
                                                    <span className="material-symbols-outlined text-xs text-slate-500">location_on</span>
                                                    <span className="truncate">{kegiatan.lokasi_nama} {kegiatan.lokasi_detail ? `(${kegiatan.lokasi_detail})` : ''}</span>
                                                </div>
                                            </div>

                                            {/* Submission Status Chip */}
                                            {kegiatan.has_submission && (
                                                <div className="p-3 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between text-xs">
                                                    <div>
                                                        <div className="font-bold text-slate-300">Status Karya Lomba:</div>
                                                        <div className="text-[11px] text-slate-400">
                                                            {reg.submission ? (
                                                                reg.submission.status === 'graded' ? (
                                                                    <span className="text-emerald-400 font-bold">Nilai: {reg.submission.final_score} / 100</span>
                                                                ) : (
                                                                    <span className="text-amber-400">Karya terkirim (Menunggu Penjurian)</span>
                                                                )
                                                            ) : (
                                                                <span className="text-rose-400">Belum mengunggah karya</span>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <button
                                                        onClick={() => openUploadModal(reg)}
                                                        className="px-3 py-1.5 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 text-amber-400 text-xs font-bold border border-amber-500/30"
                                                    >
                                                        {reg.submission ? 'Ubah Karya' : 'Unggah Karya'}
                                                    </button>
                                                </div>
                                            )}

                                            {/* Ruang Pertemuan Daring */}
                                            {(kegiatan.metode_pelaksanaan === 'online' || kegiatan.metode_pelaksanaan === 'hybrid' || kegiatan.lokasi_type === 'online' || reg.status_akses_gabung) && (
                                                <div className="p-3.5 rounded-2xl bg-teal-950/40 border border-teal-800/40 space-y-2.5" data-blok-daring={reg.id}>
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex items-center gap-1.5 text-xs font-bold text-teal-300">
                                                            <span className="material-symbols-outlined text-sm">videocam</span>
                                                            <span>Ruang Pertemuan Daring ({kegiatan.platform_daring ? kegiatan.platform_daring.toUpperCase() : 'ONLINE'})</span>
                                                        </div>
                                                        {reg.status_akses_gabung === 'dibuka' ? (
                                                            <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 flex items-center gap-1">
                                                                <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                                                Akses Dibuka
                                                            </span>
                                                        ) : reg.status_akses_gabung === 'ditutup' ? (
                                                            <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/15 text-rose-400 border border-rose-500/30">
                                                                Akses Ditutup
                                                            </span>
                                                        ) : (
                                                            <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30">
                                                                Belum Dibuka
                                                            </span>
                                                        )}
                                                    </div>

                                                    <div className="text-[11px] text-slate-300">
                                                        {reg.status_akses_gabung === 'dibuka' ? (
                                                            <span>Ruang pertemuan telah dibuka. Silakan klik tombol di bawah untuk bergabung.</span>
                                                        ) : reg.status_akses_gabung === 'ditutup' ? (
                                                            <span className="text-slate-400">Akses ruang pertemuan daring telah ditutup oleh penyelenggara.</span>
                                                        ) : (
                                                            <span className="text-amber-200/90">
                                                                Akses tombol gabung akan dibuka pada {reg.waktu_buka_gabung_wib ? `${reg.waktu_buka_gabung_wib} WIB` : '15 menit sebelum acara dimulai'}.
                                                            </span>
                                                        )}
                                                    </div>

                                                    {reg.sudah_gabung && (
                                                        <div className="text-[10px] text-teal-400 font-medium flex items-center gap-1">
                                                            <span className="material-symbols-outlined text-xs">check_circle</span>
                                                            <span>Tercatat bergabung ({reg.jumlah_gabung || 1}x){reg.pertama_gabung_pada ? ` • Pertama: ${reg.pertama_gabung_pada}` : ''}</span>
                                                        </div>
                                                    )}

                                                    <button
                                                        type="button"
                                                        onClick={() => handleGabung(reg)}
                                                        disabled={!isPaidOrFree || reg.status_akses_gabung !== 'dibuka' || joiningRegId === reg.id}
                                                        className={`w-full py-2.5 px-3 rounded-xl text-xs font-bold flex items-center justify-center gap-2 transition-all ${
                                                            reg.status_akses_gabung === 'dibuka' && isPaidOrFree
                                                                ? 'bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-500 hover:to-emerald-500 text-white shadow-md shadow-teal-600/25'
                                                                : 'bg-slate-800 text-slate-500 cursor-not-allowed border border-slate-700/50'
                                                        }`}
                                                        data-tombol-gabung={reg.id}
                                                    >
                                                        <span className="material-symbols-outlined text-sm">
                                                            {joiningRegId === reg.id ? 'progress_activity' : 'videocam'}
                                                        </span>
                                                        <span>
                                                            {joiningRegId === reg.id
                                                                ? 'Menghubungkan...'
                                                                : reg.status_akses_gabung === 'dibuka'
                                                                ? (reg.sudah_gabung ? 'Gabung Ulang Sekarang' : 'Gabung Sekarang')
                                                                : reg.status_akses_gabung === 'ditutup'
                                                                ? 'Akses Gabung Ditutup'
                                                                : (reg.waktu_buka_gabung_wib ? `Dibuka ${reg.waktu_buka_gabung_wib} WIB` : 'Belum Dibuka')}
                                                        </span>
                                                    </button>
                                                </div>
                                            )}
                                        </div>

                                        {/* Action Buttons */}
                                        <div className="pt-3 border-t border-slate-800 flex flex-col gap-2">
                                            {isPaidOrFree ? (
                                                <>
                                                    <div className="flex items-center gap-2 w-full">
                                                        <button
                                                            type="button"
                                                            onClick={() => openTicketModal(reg)}
                                                            className="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white font-bold text-xs shadow-md shadow-emerald-600/20 flex items-center justify-center gap-1.5 transition-all"
                                                            data-lihat-tiket={reg.id}
                                                        >
                                                            <span className="material-symbols-outlined text-sm">qr_code</span>
                                                            Tampilkan E-Tiket
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setActiveTicket(reg);
                                                                setTimeout(() => window.print(), 350);
                                                            }}
                                                            className="px-3 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-emerald-400 font-bold text-xs border border-emerald-500/30 flex items-center justify-center gap-1.5 transition-all shrink-0"
                                                            data-unduh-tiket={reg.id}
                                                        >
                                                            <span className="material-symbols-outlined text-sm">download</span>
                                                            Unduh E-Tiket
                                                        </button>
                                                    </div>

                                                    {/* Status / Unduh Sertifikat */}
                                                    {reg.sertifikat_status === 'terbit' ? (
                                                        <a
                                                            href={reg.sertifikat_url || reg.sertifikat_verification_url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="w-full py-2 px-3 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/40 text-amber-300 font-bold text-xs flex items-center justify-center gap-1.5 transition-all"
                                                            data-sertifikat-terbit={reg.id}
                                                        >
                                                            <span className="material-symbols-outlined text-sm">workspace_premium</span>
                                                            Unduh Sertifikat ({reg.sertifikat_nomor})
                                                        </a>
                                                    ) : reg.sertifikat_status === 'menunggu_verifikasi' ? (
                                                        <div className="w-full py-2 px-3 rounded-xl bg-slate-800/80 border border-slate-700 text-slate-400 text-xs flex items-center justify-center gap-1.5" data-sertifikat-menunggu={reg.id}>
                                                            <span className="material-symbols-outlined text-sm text-amber-400">hourglass_top</span>
                                                            <span>Menunggu verifikasi panitia</span>
                                                        </div>
                                                    ) : null}
                                                </>
                                            ) : (
                                                <div className="text-xs text-amber-400 font-bold flex items-center gap-1">
                                                    <span className="material-symbols-outlined text-sm">pending</span>
                                                    Selesaikan Pembayaran via Tripay
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="p-8 rounded-2xl bg-slate-900 border border-slate-800 text-center text-slate-400 text-xs">
                            Belum ada riwayat pendaftaran kegiatan. Silakan telusuri jadwal kegiatan untuk mendaftar.
                        </div>
                    )}
                </div>

                {/* E-Ticket Modal */}
                {activeTicket && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/85 backdrop-blur-md flex items-center justify-center p-4">
                        <div className="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-md shadow-2xl overflow-hidden relative print:shadow-none print:border-none print:w-full">
                            {/* Close button (hidden during print) */}
                            <button
                                onClick={() => setActiveTicket(null)}
                                className="absolute top-4 right-4 z-10 p-2 rounded-xl bg-black/40 hover:bg-black/60 text-white print:hidden"
                            >
                                <span className="material-symbols-outlined">close</span>
                            </button>

                            {/* Ticket Header */}
                            <div className="bg-gradient-to-tr from-emerald-700 via-emerald-600 to-teal-500 p-6 text-white text-center relative overflow-hidden">
                                <div className="absolute -top-12 -left-12 w-32 h-32 bg-white/10 rounded-full blur-xl pointer-events-none"></div>
                                <div className="text-[10px] uppercase font-black tracking-widest opacity-80">E-Tiket Resmi Masuk Acara</div>
                                <h3 className="text-lg font-black mt-1 leading-snug">{activeTicket.kegiatan?.nama_kegiatan}</h3>
                                <p className="text-xs opacity-90 mt-0.5">{activeTicket.kegiatan?.kategori}</p>
                            </div>

                            {/* Ticket Body */}
                            <div className="p-6 space-y-6 bg-slate-950">
                                {/* Ticket QR Code */}
                                <div className="text-center p-4 rounded-2xl bg-white text-slate-900 w-48 mx-auto shadow-xl">
                                    <img
                                        src={`/tiket/${encodeURIComponent(activeTicket.nomor_tiket)}/qr`}
                                        alt="QR Tiket"
                                        className="w-full h-auto mx-auto rounded-lg"
                                    />
                                    <div className="text-[11px] font-mono font-black mt-2 tracking-wider text-slate-800">
                                        {activeTicket.nomor_tiket}
                                    </div>
                                </div>

                                {/* Information Details */}
                                <div className="space-y-2 text-xs divide-y divide-slate-800/80">
                                    <div className="flex justify-between py-1.5">
                                        <span className="text-slate-400">Nama Peserta</span>
                                        <span className="font-bold text-white">{activeTicket.participant_name}</span>
                                    </div>
                                    <div className="flex justify-between py-1.5">
                                        <span className="text-slate-400">NIM / Identitas</span>
                                        <span className="font-bold text-white">{activeTicket.participant_identity || '-'}</span>
                                    </div>
                                    <div className="flex justify-between py-1.5">
                                        <span className="text-slate-400">Kursi Reservasi</span>
                                        <span className="font-mono font-black text-emerald-400 text-sm">
                                            {activeTicket.perlu_pilih_ulang ? 'Perlu pilih ulang' : (activeTicket.seat ? activeTicket.seat.seat_code : 'Tanpa kursi bernomor')}
                                        </span>
                                    </div>
                                    <div className="flex justify-between py-1.5">
                                        <span className="text-slate-400">Waktu &amp; Tanggal</span>
                                        <span className="font-bold text-white">
                                            {activeTicket.kegiatan?.tanggal_mulai ? `${formatWib(activeTicket.kegiatan.tanggal_mulai)} WIB` : '-'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between py-1.5">
                                        <span className="text-slate-400">Lokasi / Gedung</span>
                                        <span className="font-bold text-white text-right max-w-[200px] truncate">
                                            {activeTicket.kegiatan?.lokasi_nama}
                                        </span>
                                    </div>
                                    <div className="flex justify-between py-1.5">
                                        <span className="text-slate-400">Status Tiket</span>
                                        <span className="font-black text-emerald-400">LUNAS / VALID</span>
                                    </div>
                                </div>

                                {/* Panduan Presensi Akhir */}
                                <div className="p-3.5 rounded-2xl bg-emerald-950/40 border border-emerald-800/50 text-[11px] text-emerald-300 flex items-start gap-2.5">
                                    <span className="material-symbols-outlined text-emerald-400 text-base shrink-0 mt-0.5">qr_code_scanner</span>
                                    <div>
                                        <div className="font-bold text-emerald-200">Presensi Akhir Acara:</div>
                                        <p className="text-emerald-400/90 mt-0.5 leading-relaxed">
                                            Pindai QR di layar acara untuk presensi akhir dan penerbitan e-sertifikat resmi.
                                        </p>
                                    </div>
                                </div>

                                {/* Print & Download Action (hidden during print) */}
                                <div className="pt-3 print:hidden">
                                    <button
                                        onClick={handlePrintTicket}
                                        className="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/25 transition-all"
                                    >
                                        <span className="material-symbols-outlined text-sm">print</span>
                                        Cetak / Simpan E-Tiket (PDF)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Upload Submission Modal */}
                {uploadModalOpen && activeRegForUpload && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                        <div className="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-lg shadow-2xl p-6 space-y-4">
                            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                                <div>
                                    <h3 className="text-base font-black text-white">Pengumpulan Karya Kompetisi</h3>
                                    <p className="text-xs text-slate-400">{activeRegForUpload.kegiatan?.nama_kegiatan}</p>
                                </div>
                                <button
                                    onClick={() => setUploadModalOpen(false)}
                                    className="p-1 rounded-lg text-slate-400 hover:text-white"
                                >
                                    <span className="material-symbols-outlined">close</span>
                                </button>
                            </div>

                            <form onSubmit={handleUploadKarya} className="space-y-3 text-xs">
                                <div>
                                    <label className="block font-bold text-slate-300 mb-1">Judul Karya / Proyek *</label>
                                    <input
                                        type="text"
                                        value={submissionTitle}
                                        onChange={(e) => setSubmissionTitle(e.target.value)}
                                        placeholder="Judul karya ilmiah, esai, atau produk lomba"
                                        className="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white"
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-300 mb-1">Deskripsi Singkat / Abstrak</label>
                                    <textarea
                                        rows="3"
                                        value={submissionDesc}
                                        onChange={(e) => setSubmissionDesc(e.target.value)}
                                        placeholder="Jelaskan ringkasan karya Anda..."
                                        className="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white"
                                    ></textarea>
                                </div>

                                <div className="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-2">
                                    <label className="block font-bold text-slate-300">Unggah Berkas Karya (Maks. 20MB) *</label>
                                    <input
                                        type="file"
                                        onChange={(e) => setSubmissionFile(e.target.files[0])}
                                        className="w-full text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-500"
                                        required={!activeRegForUpload.submission}
                                    />
                                </div>

                                <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setUploadModalOpen(false)}
                                        className="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 font-bold hover:bg-slate-700"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={uploading}
                                        className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold shadow-lg shadow-emerald-600/30"
                                    >
                                        {uploading ? 'Mengunggah...' : 'Kirim Karya untuk Dinilai'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Meeting Credentials Modal */}
                {meetingModal.open && meetingModal.data && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/85 backdrop-blur-md flex items-center justify-center p-4">
                        <div className="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-md shadow-2xl p-6 space-y-5">
                            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                                <div className="flex items-center gap-2.5">
                                    <div className="w-10 h-10 rounded-xl bg-teal-600/20 text-teal-400 flex items-center justify-center border border-teal-500/30">
                                        <span className="material-symbols-outlined text-xl">videocam</span>
                                    </div>
                                    <div>
                                        <h3 className="text-base font-black text-white">Ruang Pertemuan Daring</h3>
                                        <p className="text-xs text-teal-400 font-semibold uppercase">{meetingModal.data.platform || 'Online'}</p>
                                    </div>
                                </div>
                                <button
                                    onClick={() => setMeetingModal({ open: false, data: null, error: null })}
                                    className="p-1.5 rounded-lg bg-slate-800 text-slate-400 hover:text-white"
                                >
                                    <span className="material-symbols-outlined">close</span>
                                </button>
                            </div>

                            <div className="space-y-3 text-xs">
                                <div className="p-3.5 rounded-2xl bg-teal-950/40 border border-teal-800/50 text-teal-200">
                                    <p className="font-semibold leading-relaxed">
                                        Tautan rapat telah dibuka di tab baru peramban Anda. Anda dapat menggunakan informasi kredensial di bawah bila aplikasi rapat meminta verifikasi manual:
                                    </p>
                                </div>

                                {meetingModal.data.meeting_id && (
                                    <div className="p-3 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between gap-3">
                                        <div>
                                            <div className="text-[10px] text-slate-500 uppercase font-bold">Meeting ID</div>
                                            <div className="font-mono font-bold text-white text-sm select-all">
                                                {meetingModal.data.meeting_id}
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => copyText(meetingModal.data.meeting_id, 'id')}
                                            className="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-teal-400 font-bold text-xs flex items-center gap-1 border border-slate-700"
                                        >
                                            <span className="material-symbols-outlined text-xs">
                                                {copiedKey === 'id' ? 'check' : 'content_copy'}
                                            </span>
                                            <span>{copiedKey === 'id' ? 'Tersalin' : 'Salin'}</span>
                                        </button>
                                    </div>
                                )}

                                {meetingModal.data.passcode && (
                                    <div className="p-3 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between gap-3">
                                        <div>
                                            <div className="text-[10px] text-slate-500 uppercase font-bold">Passcode / Kata Sandi</div>
                                            <div className="font-mono font-bold text-white text-sm select-all">
                                                {meetingModal.data.passcode}
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => copyText(meetingModal.data.passcode, 'passcode')}
                                            className="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-teal-400 font-bold text-xs flex items-center gap-1 border border-slate-700"
                                        >
                                            <span className="material-symbols-outlined text-xs">
                                                {copiedKey === 'passcode' ? 'check' : 'content_copy'}
                                            </span>
                                            <span>{copiedKey === 'passcode' ? 'Tersalin' : 'Salin'}</span>
                                        </button>
                                    </div>
                                )}
                            </div>

                            <div className="pt-2 flex flex-col gap-2">
                                {meetingModal.data.join_url && (
                                    <a
                                        href={meetingModal.data.join_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="w-full py-3 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-lg shadow-teal-600/25 transition-all text-center"
                                    >
                                        <span className="material-symbols-outlined text-sm">open_in_new</span>
                                        Buka Ulang Tautan Rapat
                                    </a>
                                )}
                                <button
                                    type="button"
                                    onClick={() => setMeetingModal({ open: false, data: null, error: null })}
                                    className="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition-colors"
                                >
                                    Tutup
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </ParticipantLayout>
    );
}
