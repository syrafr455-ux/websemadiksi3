import React, { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatWib } from '@/lib/waktuWib';
import { KATEGORI_OPTIONS, METODE_OPTIONS, SASARAN_OPTIONS, labelDari } from '@/lib/kegiatanForm';
import DenahBookingKegiatan from '@/Components/Admin/DenahBookingKegiatan';

const LOKASI_LABEL = { unusa_campus: 'Kampus UNUSA', external: 'Venue Eksternal', online: 'Online' };

const STATUS_BAYAR_KELAS = {
    free: 'bg-sky-500/15 text-sky-300 border border-sky-500/30',
    paid: 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30',
    pending: 'bg-amber-500/15 text-amber-300 border border-amber-500/30',
    expired: 'bg-slate-700 text-slate-300',
    failed: 'bg-rose-500/15 text-rose-300 border border-rose-500/30',
};

const KEHADIRAN = {
    registered: { label: 'Terdaftar', kelas: 'bg-slate-700 text-slate-300' },
    checked_in: { label: 'Hadir', kelas: 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' },
    absent: { label: 'Tidak Hadir', kelas: 'bg-rose-500/15 text-rose-300 border border-rose-500/30' },
};

/** Waktu UTC dari server sebagai teks WIB, mis. "1 Nov 2026, 08.00 WIB"; "-" bila kosong. */
const wib = (iso) => (iso ? `${formatWib(iso)} WIB` : '-');

function Kartu({ icon, label, children }) {
    return (
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-4">
            <div className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                <span className="material-symbols-outlined text-base text-emerald-400">{icon}</span>
                {label}
            </div>
            <div className="mt-2 text-xs text-slate-300 space-y-1">{children}</div>
        </div>
    );
}

function Baris({ label, children }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-slate-500">{label}</span>
            <span className="text-right text-slate-200 font-medium">{children}</span>
        </div>
    );
}

const TAB = [
    { id: 'pendaftar', label: 'Pendaftar', ikon: 'groups' },
    { id: 'denah-booking', label: 'Denah & Booking', ikon: 'event_seat' },
];

export default function KegiatanShow({
    title = 'Detail Kegiatan',
    kegiatan = {},
    ringkasan = {},
    approval = {},
    pendaftar = [],
    kursi = [],
    denah = {},
    unusaFacilities = [],
}) {
    const [hanyaPilihUlang, setHanyaPilihUlang] = useState(false);
    const [modalTolakOpen, setModalTolakOpen] = useState(false);
    const [catatanTolak, setCatatanTolak] = useState('');
    // Tab aktif tersimpan di hash URL (#denah-booking) agar bertahan saat halaman dimuat ulang.
    const [tab, setTab] = useState(() => (typeof window !== 'undefined' && window.location.hash === '#denah-booking' ? 'denah-booking' : 'pendaftar'));
    const gantiTab = (id) => {
        setTab(id);
        window.history.replaceState(null, '', id === 'pendaftar' ? window.location.pathname + window.location.search : `#${id}`);
    };
    const daftar = useMemo(
        () => (hanyaPilihUlang ? pendaftar.filter((p) => p.needs_seat_reselection) : pendaftar),
        [pendaftar, hanyaPilihUlang],
    );

    const fasilitas = [kegiatan.lokasi_kampus, kegiatan.lokasi_gedung, kegiatan.lokasi_ruangan].filter(Boolean).join(' / ');

    return (
        <AdminLayout title={title}>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <a href="/admin/kegiatan" className="text-[11px] text-slate-400 hover:text-white flex items-center gap-1">
                            <span className="material-symbols-outlined text-sm">arrow_back</span>
                            Kembali ke daftar kegiatan
                        </a>
                        <h2 className="text-xl font-black text-white flex items-center gap-2 mt-1">
                            <span className="material-symbols-outlined text-emerald-400">event_seat</span>
                            {kegiatan.nama_kegiatan}
                        </h2>
                        <p className="text-xs text-emerald-400 font-medium mt-1">
                            {labelDari(KATEGORI_OPTIONS, kegiatan.kategori)} • {labelDari(METODE_OPTIONS, kegiatan.metode_pelaksanaan)} • {labelDari(SASARAN_OPTIONS, kegiatan.sasaran_peserta)}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                            kegiatan.status === 'published'
                                ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
                                : 'bg-slate-700 text-slate-300'
                        }`}>
                            {kegiatan.status}
                        </span>
                        <a
                            href={`/admin/kegiatan/${kegiatan.id}/pindai-tiket`}
                            className="px-3 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold flex items-center gap-1.5 shadow-lg shadow-emerald-600/20 transition-all"
                        >
                            <span className="material-symbols-outlined text-sm">qr_code_scanner</span>
                            Pindai Tiket
                        </a>
                        <a
                            href={`/kegiatan/${kegiatan.id}`}
                            target="_blank"
                            rel="noreferrer"
                            className="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-bold flex items-center gap-1.5"
                        >
                            <span className="material-symbols-outlined text-sm">visibility</span>
                            Halaman Publik
                        </a>
                    </div>
                </div>

                {/* Panel Persetujuan Kegiatan */}
                <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-4">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
                        <div className="flex items-center gap-2">
                            <span className="material-symbols-outlined text-emerald-400">verified_user</span>
                            <div>
                                <h3 className="text-sm font-bold text-white">Alur Persetujuan Kegiatan</h3>
                                <p className="text-[11px] text-slate-400">
                                    Pembuat: <span className="text-slate-200 font-medium">{approval?.created_by?.name || 'Super Admin'}</span>
                                    {approval?.created_by?.email && ` (${approval.created_by.email})`}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            {approval?.can_submit && (
                                <button
                                    onClick={() => router.post(`/admin/kegiatan/${kegiatan.id}/ajukan`)}
                                    className="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold flex items-center gap-1.5 shadow-lg shadow-indigo-600/20 transition-all"
                                >
                                    <span className="material-symbols-outlined text-sm">send</span>
                                    Ajukan Persetujuan
                                </button>
                            )}
                            {approval?.can_approve && (
                                <button
                                    onClick={() => router.post(`/admin/kegiatan/${kegiatan.id}/setujui`)}
                                    className="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold flex items-center gap-1.5 shadow-lg shadow-emerald-600/20 transition-all"
                                >
                                    <span className="material-symbols-outlined text-sm">check_circle</span>
                                    Setujui Kegiatan
                                </button>
                            )}
                            {approval?.can_reject && (
                                <button
                                    onClick={() => {
                                        setCatatanTolak('');
                                        setModalTolakOpen(true);
                                    }}
                                    className="px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold flex items-center gap-1.5 shadow-lg shadow-rose-600/20 transition-all"
                                >
                                    <span className="material-symbols-outlined text-sm">cancel</span>
                                    Tolak Kegiatan
                                </button>
                            )}
                        </div>
                    </div>

                    {!approval?.bisa_terbit && (
                        <div className="bg-amber-500/10 border border-amber-500/30 rounded-xl p-3 flex items-start gap-2.5 text-xs text-amber-300">
                            <span className="material-symbols-outlined text-base text-amber-400 shrink-0 mt-0.5">warning</span>
                            <div>
                                <span className="font-bold">Kegiatan tidak dapat diterbitkan:</span> Penerbitan kegiatan membutuhkan persetujuan seluruh Super Admin aktif selain pembuat. Saat ini tidak ada Super Admin lain yang aktif dalam sistem.
                            </div>
                        </div>
                    )}

                    {approval?.penyetuju_wajib && approval.penyetuju_wajib.length > 0 && (
                        <div>
                            <div className="text-xs font-bold text-slate-300 mb-2">Daftar Penyetuju Wajib ({approval.penyetuju_wajib.length} Super Admin):</div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                                {approval.penyetuju_wajib.map((p) => (
                                    <div key={p.id} className="bg-slate-950 border border-slate-800 rounded-xl p-3 flex items-center justify-between gap-2">
                                        <div>
                                            <div className="text-xs font-semibold text-slate-200">{p.name}</div>
                                            <div className="text-[11px] text-slate-500">{p.email}</div>
                                            {p.catatan && <div className="text-[11px] text-rose-300 mt-1 italic">&ldquo;{p.catatan}&rdquo;</div>}
                                        </div>
                                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider shrink-0 ${
                                            p.status === 'setuju'
                                                ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
                                                : p.status === 'tolak'
                                                ? 'bg-rose-500/15 text-rose-300 border border-rose-500/30'
                                                : 'bg-amber-500/15 text-amber-300 border border-amber-500/30'
                                        }`}>
                                            {p.status === 'setuju' ? 'Disetujui' : p.status === 'tolak' ? 'Ditolak' : 'Menunggu'}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* Ringkasan */}
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    <Kartu icon="schedule" label="Jadwal (WIB)">
                        <Baris label="Mulai">{wib(kegiatan.tanggal_mulai)}</Baris>
                        <Baris label="Selesai">{wib(kegiatan.tanggal_selesai)}</Baris>
                        <Baris label="Batas daftar">{wib(kegiatan.registration_deadline)}</Baris>
                        {kegiatan.is_submission_active && (
                            <Baris label="Batas karya">{wib(kegiatan.submission_deadline)}</Baris>
                        )}
                        <Baris label="Pendaftaran">{kegiatan.registration_open ? 'Dibuka' : 'Ditutup'}</Baris>
                    </Kartu>

                    <Kartu icon="location_on" label="Lokasi">
                        <Baris label="Tipe">{LOKASI_LABEL[kegiatan.lokasi_type] || kegiatan.lokasi_type || '-'}</Baris>
                        <div className="text-slate-200 font-medium">{kegiatan.lokasi_nama || '-'}</div>
                        {kegiatan.lokasi_detail && <div className="text-slate-400">{kegiatan.lokasi_detail}</div>}
                        {fasilitas && <div className="text-[11px] text-teal-300">{fasilitas}</div>}
                    </Kartu>

                    <Kartu icon="groups" label="Kuota">
                        <div className="text-2xl font-black text-white">
                            {ringkasan.pendaftar_aktif ?? 0}
                            <span className="text-sm text-slate-400 font-bold"> / {kegiatan.kuota}</span>
                        </div>
                        <div className="text-slate-400">Peserta aktif (gratis, menunggu bayar, lunas)</div>
                        <Baris label="Total tiket tercatat">{ringkasan.pendaftar_total ?? 0}</Baris>
                        <Baris label="Hadir">{ringkasan.hadir ?? 0}</Baris>
                    </Kartu>

                    <Kartu icon="event_seat" label="Booking Kursi">
                        {kegiatan.is_seat_booking_active || ringkasan.kursi_total > 0 ? (
                            <>
                                <Baris label="Status">
                                    {kegiatan.seat_booking_open ? (
                                        <span className="text-emerald-400">Dibuka</span>
                                    ) : (
                                        <span className="text-amber-300">{kegiatan.is_seat_booking_active ? 'Di luar jadwal' : 'Dinonaktifkan'}</span>
                                    )}
                                </Baris>
                                <Baris label="Kursi terisi">{ringkasan.kursi_terisi ?? 0} / {ringkasan.kursi_total ?? 0}</Baris>
                                <Baris label="Buka">{wib(kegiatan.booking_opens_at)}</Baris>
                                <Baris label="Tutup">{wib(kegiatan.booking_closes_at)}</Baris>
                                <Baris label="Pindah ruangan">{kegiatan.venue_change_count ?? 0}×</Baris>
                            </>
                        ) : (
                            <div className="text-slate-500">Kegiatan ini tidak memakai denah kursi.</div>
                        )}
                    </Kartu>
                </div>

                {ringkasan.perlu_pilih_ulang > 0 && (
                    <div className="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/40 text-amber-200 text-xs font-medium flex items-start gap-3">
                        <span className="material-symbols-outlined text-amber-400">warning</span>
                        <div>
                            <div className="font-bold">{ringkasan.perlu_pilih_ulang} peserta harus memilih ulang kursi</div>
                            <div className="text-amber-200/80 mt-0.5">
                                Denah ruangan berubah sehingga kursi mereka direset. Email pemilihan ulang sudah dikirim ke pemesan.
                            </div>
                        </div>
                    </div>
                )}

                {/* Tab */}
                <div role="tablist" aria-label="Bagian detail kegiatan" className="flex gap-2 border-b border-slate-800">
                    {TAB.map((t) => (
                        <button
                            key={t.id}
                            type="button"
                            role="tab"
                            id={`tab-${t.id}`}
                            aria-selected={tab === t.id}
                            aria-controls={`panel-${t.id}`}
                            onClick={() => gantiTab(t.id)}
                            className={`px-4 py-2.5 -mb-px border-b-2 text-xs font-bold flex items-center gap-1.5 ${tab === t.id ? 'border-emerald-400 text-white' : 'border-transparent text-slate-400 hover:text-white'}`}
                        >
                            <span className="material-symbols-outlined text-base">{t.ikon}</span>
                            {t.label}
                        </button>
                    ))}
                </div>

                {tab === 'denah-booking' && (
                    <div role="tabpanel" id="panel-denah-booking" aria-labelledby="tab-denah-booking">
                        <DenahBookingKegiatan kegiatan={kegiatan} kursi={kursi} denah={denah} pendaftar={pendaftar} unusaFacilities={unusaFacilities} />
                    </div>
                )}

                {/* Tabel pendaftar */}
                {tab === 'pendaftar' && (
                <div role="tabpanel" id="panel-pendaftar" aria-labelledby="tab-pendaftar">
                <div className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
                    <div className="px-6 py-4 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div className="text-sm font-bold text-white">Daftar Pendaftar ({pendaftar.length})</div>
                        <label className="flex items-center gap-2 text-xs text-slate-300">
                            <input
                                type="checkbox"
                                checked={hanyaPilihUlang}
                                onChange={(e) => setHanyaPilihUlang(e.target.checked)}
                                className="w-4 h-4 rounded text-emerald-600 bg-slate-900 border-slate-700"
                            />
                            Hanya yang perlu pilih ulang kursi
                        </label>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-950 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                                <tr>
                                    <th className="px-6 py-4">Peserta</th>
                                    <th className="px-6 py-4">Dipesankan Oleh</th>
                                    <th className="px-6 py-4">Nomor Tiket</th>
                                    <th className="px-6 py-4">Kursi</th>
                                    <th className="px-6 py-4">Pembayaran</th>
                                    <th className="px-6 py-4">Kehadiran</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800/80 text-slate-300">
                                {daftar.length > 0 ? (
                                    daftar.map((p) => (
                                        <tr
                                            key={p.id}
                                            data-pilih-ulang={p.needs_seat_reselection ? 'ya' : undefined}
                                            className={p.needs_seat_reselection ? 'bg-amber-500/5 hover:bg-amber-500/10' : 'hover:bg-slate-800/40 transition-colors'}
                                        >
                                            <td className="px-6 py-4">
                                                <div className="font-bold text-white">{p.nama_peserta || '-'}</div>
                                                <div className="text-[11px] text-slate-400">
                                                    {[p.identitas_peserta, p.email_peserta].filter(Boolean).join(' • ') || '-'}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                {p.booker ? (
                                                    <>
                                                        <div className="text-slate-200">{p.booker.name}</div>
                                                        <div className="text-[11px] text-slate-500">Delegasi • {p.booker.email}</div>
                                                    </>
                                                ) : (
                                                    <span className="text-slate-500">Pesan sendiri</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 font-mono text-[11px] text-slate-200">{p.nomor_tiket || '-'}</td>
                                            <td className="px-6 py-4">
                                                {p.needs_seat_reselection ? (
                                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                                        <span className="material-symbols-outlined text-xs">sync_problem</span>
                                                        Perlu pilih ulang kursi
                                                    </span>
                                                ) : p.kursi ? (
                                                    <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-teal-500/15 text-teal-400 border border-teal-500/20">{p.kursi}</span>
                                                ) : (
                                                    <span className="text-slate-500">-</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold ${STATUS_BAYAR_KELAS[p.status_pembayaran] || 'bg-slate-700 text-slate-300'}`}>
                                                    {p.status_pembayaran_label || p.status_pembayaran || '-'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold ${(KEHADIRAN[p.status_kehadiran] || {}).kelas || 'bg-slate-700 text-slate-300'}`}>
                                                    {(KEHADIRAN[p.status_kehadiran] || {}).label || p.status_kehadiran || '-'}
                                                </span>
                                                {p.checked_in_at && (
                                                    <div className="text-[10px] text-slate-500 mt-1">{wib(p.checked_in_at)}</div>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-12 text-center text-slate-500">
                                            {hanyaPilihUlang ? 'Tidak ada peserta yang perlu memilih ulang kursi.' : 'Belum ada pendaftar.'}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
                </div>
                )}

                {/* Modal Konfirmasi Penolakan */}
                {modalTolakOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
                        <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-5 space-y-4 shadow-2xl">
                            <h3 className="text-base font-bold text-white flex items-center gap-2">
                                <span className="material-symbols-outlined text-rose-400">cancel</span>
                                Tolak Persetujuan Kegiatan
                            </h3>
                            <p className="text-xs text-slate-300">
                                Penolakan akan mengembalikan status kegiatan menjadi <strong>Draft</strong> dan membatalkan seluruh persetujuan yang telah diberikan. Masukkan alasan penolakan:
                            </p>
                            <textarea
                                value={catatanTolak}
                                onChange={(e) => setCatatanTolak(e.target.value)}
                                placeholder="Tuliskan catatan alasan penolakan kegiatan secara rinci..."
                                rows={3}
                                className="w-full bg-slate-950 border border-slate-700 rounded-xl p-3 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-rose-500"
                            />
                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setModalTolakOpen(false)}
                                    className="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition-all"
                                >
                                    Batal
                                </button>
                                <button
                                    type="button"
                                    disabled={!catatanTolak.trim()}
                                    onClick={() => {
                                        router.post(
                                            `/admin/kegiatan/${kegiatan.id}/tolak`,
                                            { catatan: catatanTolak },
                                            {
                                                onSuccess: () => setModalTolakOpen(false),
                                            }
                                        );
                                    }}
                                    className="px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 disabled:opacity-50 text-white text-xs font-bold transition-all"
                                >
                                    Konfirmasi Penolakan
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
