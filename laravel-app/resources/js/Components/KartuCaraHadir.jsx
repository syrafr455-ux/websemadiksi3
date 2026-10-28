import React from 'react';

/**
 * Status dua pilihan cara hadir untuk kegiatan hybrid, dihitung dari props server (KuotaHybridService::status).
 * Hybrid lama (tanpa pembagian kuota) membuka kedua cara hadir sejak awal.
 */
export function statusCaraHadir(hybrid) {
    if (!hybrid) return null;
    if (!hybrid.berpembagian) {
        return { onsite: { tersedia: true, teks: hybrid.lokasi || 'Hadir di lokasi kegiatan.' }, daring: { tersedia: true, teks: 'Pertemuan daring lewat Zoom.' }, tersedia: true };
    }

    const { onsite, daring } = hybrid;
    const teksDaring = {
        belum_dibuka: `Dibuka otomatis setelah kuota onsite penuh. Kuota daring: ${daring.kuota}.`,
        terbuka: `Sisa kuota: ${daring.sisa} dari ${daring.kuota}`,
        penuh: 'Kuota penuh',
    }[daring.status];

    return {
        onsite: {
            tersedia: onsite.sisa > 0,
            teks: onsite.sisa > 0 ? `${hybrid.lokasi || 'Lokasi kegiatan'}. Sisa kursi: ${onsite.sisa} dari ${onsite.kuota}` : `Kuota onsite penuh (${onsite.kuota} dari ${onsite.kuota}).`,
            label: onsite.sisa > 0 ? null : 'Penuh',
        },
        daring: {
            tersedia: daring.status === 'terbuka',
            teks: teksDaring,
            label: { belum_dibuka: 'Belum dibuka', penuh: 'Penuh' }[daring.status] ?? null,
        },
        tersedia: onsite.sisa > 0 || daring.status === 'terbuka',
    };
}

function Kartu({ nama, nilai, judul, status, terpilih, onPilih, ikon }) {
    const mati = !status.tersedia;
    return (
        <label
            className={`flex items-start gap-3 min-h-[44px] p-4 rounded-2xl border text-left ${
                mati ? 'bg-slate-900 border-dashed border-slate-600 cursor-not-allowed' : terpilih ? 'bg-emerald-500/15 border-emerald-500 cursor-pointer' : 'bg-slate-950 border-slate-700 cursor-pointer hover:border-slate-500'
            }`}
        >
            <input
                type="radio"
                name={nama}
                value={nilai}
                checked={terpilih}
                disabled={mati}
                onChange={() => onPilih?.(nilai)}
                className="mt-1 w-5 h-5 shrink-0 accent-emerald-500"
            />
            <span className="block">
                <span className="flex flex-wrap items-center gap-2 text-sm font-bold text-white">
                    <span className="material-symbols-outlined text-base" aria-hidden="true">{ikon}</span>
                    {judul}
                    {status.label && <span className="px-2 py-0.5 rounded-full text-xs font-bold bg-slate-700 text-slate-100">{status.label}</span>}
                </span>
                <span className="block mt-1 text-sm text-slate-300" data-keterangan-cara-hadir={nilai}>{status.teks}</span>
            </span>
        </label>
    );
}

export default function KartuCaraHadir({ hybrid, nilai = null, onPilih, galat = null }) {
    const status = statusCaraHadir(hybrid);
    if (!status) return null;

    return (
        <fieldset className="space-y-2" data-pilihan-cara-hadir>
            <legend className="text-sm font-bold text-white mb-1">Cara hadir *</legend>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Kartu nama="cara_hadir" nilai="onsite" judul="Hadir langsung (onsite)" status={status.onsite} terpilih={nilai === 'onsite'} onPilih={onPilih} ikon="location_on" />
                <Kartu nama="cara_hadir" nilai="daring" judul="Ikut via Zoom" status={status.daring} terpilih={nilai === 'daring'} onPilih={onPilih} ikon="videocam" />
            </div>
            {!status.tersedia && <p className="text-sm font-bold text-amber-200">Kuota penuh</p>}
            {galat && <p role="alert" className="text-sm text-rose-300">{galat}</p>}
        </fieldset>
    );
}
