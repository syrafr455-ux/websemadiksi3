import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function PenilaianIndex({
    title = 'Penilaian Karya & Penjurian',
    kegiatans = [],
    selectedKegiatanId = null,
    submissions = {},
    filters = {}
}) {
    const [search, setSearch] = useState(filters.q || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [gradeModalOpen, setGradeModalOpen] = useState(false);
    const [selectedSubmission, setSelectedSubmission] = useState(null);
    const [finalScore, setFinalScore] = useState('');
    const [judgeNotes, setJudgeNotes] = useState('');
    const [importModalOpen, setImportModalOpen] = useState(false);
    const [csvFile, setCsvFile] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    const handleKegiatanChange = (e) => {
        const kid = e.target.value;
        router.get('/admin/penilaian', { kegiatan_id: kid }, { preserveState: true });
    };

    const openGradeModal = (sub) => {
        setSelectedSubmission(sub);
        setFinalScore(sub.final_score !== null ? sub.final_score : '');
        setJudgeNotes(sub.judge_notes || '');
        setGradeModalOpen(true);
    };

    const handleSaveScore = (e) => {
        e.preventDefault();
        if (!selectedSubmission) return;

        setSubmitting(true);
        router.post(`/admin/penilaian/${selectedSubmission.id}/score`, {
            final_score: parseFloat(finalScore),
            judge_notes: judgeNotes,
        }, {
            onSuccess: () => {
                setGradeModalOpen(false);
                setSubmitting(false);
            },
            onError: () => setSubmitting(false),
        });
    };

    const handleImportCsv = (e) => {
        e.preventDefault();
        if (!csvFile || !selectedKegiatanId) return;

        setSubmitting(true);
        const formData = new FormData();
        formData.append('csv_file', csvFile);

        router.post(`/admin/penilaian/${selectedKegiatanId}/import`, formData, {
            onSuccess: () => {
                setImportModalOpen(false);
                setCsvFile(null);
                setSubmitting(false);
            },
            onError: () => setSubmitting(false),
        });
    };

    return (
        <AdminLayout title={title}>
            <div className="space-y-6">
                {/* Header & Quick Action Buttons */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-black text-white flex items-center gap-2">
                            <span className="material-symbols-outlined text-amber-400">rate_review</span>
                            Penjurian &amp; Penilaian Karya Peserta
                        </h2>
                        <p className="text-xs text-slate-400 mt-1">
                            Evaluasi karya kompetisi mahasiswa, input skor rubrik juri, dan ekspor/impor massal via format CSV.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {selectedKegiatanId && (
                            <>
                                <a
                                    href={`/admin/penilaian/${selectedKegiatanId}/export`}
                                    className="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold flex items-center gap-2 border border-slate-700 transition-all"
                                >
                                    <span className="material-symbols-outlined text-sm">download</span>
                                    Ekspor CSV Nilai
                                </a>
                                <button
                                    onClick={() => setImportModalOpen(true)}
                                    className="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold flex items-center gap-2 shadow-lg shadow-emerald-600/20 transition-all"
                                >
                                    <span className="material-symbols-outlined text-sm">upload_file</span>
                                    Impor CSV Nilai
                                </button>
                            </>
                        )}
                    </div>
                </div>

                {/* Event Selector & Filter Bar */}
                <div className="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label className="block text-[11px] font-bold text-slate-400 mb-1">Pilih Kegiatan Kompetisi / Karya</label>
                            <select
                                value={selectedKegiatanId || ''}
                                onChange={handleKegiatanChange}
                                className="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs font-semibold focus:border-amber-500 focus:outline-none"
                            >
                                {kegiatans.map((k) => (
                                    <option key={k.id} value={k.id}>
                                        {k.nama_kegiatan} ({k.kategori})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-[11px] font-bold text-slate-400 mb-1">Pencarian Peserta / Karya</label>
                            <form method="GET" action="/admin/penilaian" className="flex gap-2">
                                <input type="hidden" name="kegiatan_id" value={selectedKegiatanId || ''} />
                                <input
                                    type="text"
                                    name="q"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Nama peserta, tiket, atau judul..."
                                    className="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs focus:border-amber-500 focus:outline-none"
                                />
                                <button type="submit" className="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold">
                                    Cari
                                </button>
                            </form>
                        </div>

                        <div>
                            <label className="block text-[11px] font-bold text-slate-400 mb-1">Status Penilaian</label>
                            <select
                                value={statusFilter}
                                onChange={(e) => {
                                    setStatusFilter(e.target.value);
                                    router.get('/admin/penilaian', {
                                        kegiatan_id: selectedKegiatanId,
                                        status: e.target.value,
                                    }, { preserveState: true });
                                }}
                                className="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs"
                            >
                                <option value="">Semua Status</option>
                                <option value="submitted">Menunggu Penilaian</option>
                                <option value="graded">Telah Dinilai</option>
                            </select>
                        </div>
                    </div>
                </div>

                {/* Submissions Table */}
                <div className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-950 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                                <tr>
                                    <th className="px-6 py-4">Peserta &amp; Tiket</th>
                                    <th className="px-6 py-4">Karya yang Diajukan</th>
                                    <th className="px-6 py-4">Berkas Karya</th>
                                    <th className="px-6 py-4">Nilai Akhir</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800/80 text-slate-300">
                                {submissions.data && submissions.data.length > 0 ? (
                                    submissions.data.map((sub) => (
                                        <tr key={sub.id} className="hover:bg-slate-800/40 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="font-bold text-white text-sm">{sub.registration?.participant_name || '-'}</div>
                                                <div className="text-[11px] text-amber-400 font-mono mt-0.5">{sub.registration?.nomor_tiket}</div>
                                                <div className="text-[10px] text-slate-500">NIM: {sub.registration?.participant_identity || '-'}</div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="font-bold text-white">{sub.title}</div>
                                                {sub.description && (
                                                    <div className="text-[11px] text-slate-400 line-clamp-2 mt-0.5">{sub.description}</div>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                {sub.file_url ? (
                                                    <a
                                                        href={sub.file_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-teal-400 text-xs font-bold border border-slate-700/80"
                                                    >
                                                        <span className="material-symbols-outlined text-sm">attachment</span>
                                                        Lihat Berkas
                                                    </a>
                                                ) : (
                                                    <span className="text-slate-500">-</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                {sub.final_score !== null ? (
                                                    <div>
                                                        <div className="text-base font-black text-emerald-400">{sub.final_score} <span className="text-xs text-slate-500">/ 100</span></div>
                                                        {sub.graded_by && (
                                                            <div className="text-[10px] text-slate-500">Oleh: {sub.graded_by}</div>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-500 italic">Belum dinilai</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                                                    sub.status === 'graded'
                                                        ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
                                                        : 'bg-amber-500/15 text-amber-400 border border-amber-500/30'
                                                }`}>
                                                    {sub.status === 'graded' ? 'Selesai Dinilai' : 'Menunggu Nilai'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <button
                                                    onClick={() => openGradeModal(sub)}
                                                    className="px-3 py-1.5 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-400 font-bold text-xs inline-flex items-center gap-1.5 border border-amber-500/30 transition-all"
                                                >
                                                    <span className="material-symbols-outlined text-sm">edit_note</span>
                                                    {sub.final_score !== null ? 'Ubah Nilai' : 'Beri Nilai'}
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-12 text-center text-slate-500">
                                            Tidak ada karya peserta yang diajukan untuk kegiatan ini.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Grade Input Modal */}
                {gradeModalOpen && selectedSubmission && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                        <div className="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-lg shadow-2xl p-6 space-y-4">
                            <div className="flex items-center justify-between border-b border-slate-800 pb-4">
                                <div>
                                    <h3 className="text-base font-black text-white">Form Penilaian Juri</h3>
                                    <p className="text-xs text-slate-400">{selectedSubmission.title}</p>
                                </div>
                                <button
                                    onClick={() => setGradeModalOpen(false)}
                                    className="p-1 rounded-lg text-slate-400 hover:text-white"
                                >
                                    <span className="material-symbols-outlined">close</span>
                                </button>
                            </div>

                            <form onSubmit={handleSaveScore} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-300 mb-1">Skor Akhir (Skala 0 – 100) *</label>
                                    <input
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.1"
                                        value={finalScore}
                                        onChange={(e) => setFinalScore(e.target.value)}
                                        className="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white font-mono text-lg font-black focus:border-amber-500 focus:outline-none"
                                        placeholder="Contoh: 87.5"
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-bold text-slate-300 mb-1">Catatan &amp; Umpan Balik Juri</label>
                                    <textarea
                                        rows="4"
                                        value={judgeNotes}
                                        onChange={(e) => setJudgeNotes(e.target.value)}
                                        className="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs focus:border-amber-500 focus:outline-none"
                                        placeholder="Tuliskan evaluasi kekuatan karya dan saran penyempurnaan..."
                                    ></textarea>
                                </div>

                                <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setGradeModalOpen(false)}
                                        className="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-bold hover:bg-slate-700"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={submitting}
                                        className="px-5 py-2 rounded-xl bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-400 text-white text-xs font-bold shadow-lg shadow-amber-600/30"
                                    >
                                        {submitting ? 'Menyimpan...' : 'Simpan Penilaian'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Import CSV Modal */}
                {importModalOpen && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                        <div className="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-md shadow-2xl p-6 space-y-4">
                            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                                <div>
                                    <h3 className="text-base font-black text-white">Impor Nilai via CSV</h3>
                                    <p className="text-xs text-slate-400">Mass-update nilai dari berkas CSV Excel.</p>
                                </div>
                                <button
                                    onClick={() => setImportModalOpen(false)}
                                    className="p-1 rounded-lg text-slate-400 hover:text-white"
                                >
                                    <span className="material-symbols-outlined">close</span>
                                </button>
                            </div>

                            <form onSubmit={handleImportCsv} className="space-y-4">
                                <div className="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-2">
                                    <label className="block text-xs font-bold text-slate-300">Pilih Berkas CSV Nilai *</label>
                                    <input
                                        type="file"
                                        accept=".csv,text/csv,text/plain"
                                        onChange={(e) => setCsvFile(e.target.files[0])}
                                        className="w-full text-xs text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-500"
                                        required
                                    />
                                    <p className="text-[10px] text-slate-500">
                                        Pastikan kolom ID_SUBMISSION dan NILAI_AKHIR tersedia persis seperti hasil ekspor.
                                    </p>
                                </div>

                                <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setImportModalOpen(false)}
                                        className="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-bold"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={submitting || !csvFile}
                                        className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 text-white text-xs font-bold shadow-lg shadow-emerald-600/30"
                                    >
                                        {submitting ? 'Mengimpor...' : 'Unggah & Perbarui Nilai'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
