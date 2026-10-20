import React, { useMemo, useRef, useState } from 'react';

/**
 * Peta kursi bersama (admin & peserta).
 *
 * - kursi: [{ id, kode, baris, kolom, tier, status, pemegang }]
 * - tampilan(kursi) -> { kelas, status (teks untuk pembaca layar), bisaDipilih, isi? }
 * - dipilih: { [seatId]: 'label singkat' } kursi yang sedang dipilih di form
 * - onPilih(kursi): dipanggil saat kursi yang bisa dipilih diklik / ditekan Enter atau Spasi
 * - info(kursi): teks keterangan saat kursi disorot/difokus (mis. pemegang kursi untuk admin)
 * - legenda: [{ label, kelas }]
 *
 * Peta digulir horizontal di dalam wadahnya (halaman tidak ikut melebar). Kursi bisa dipilih dengan
 * keyboard: Tab masuk ke peta, panah untuk berpindah kursi, Enter/Spasi untuk memilih.
 */
export default function PetaKursi({
    kursi = [],
    tampilan,
    dipilih = {},
    onPilih,
    info,
    legenda = [],
    judulPanggung = 'PANGGUNG / LAYAR UTAMA',
    labelPeta = 'Denah kursi',
}) {
    const wadah = useRef(null);
    const [sorot, setSorot] = useState(null);
    const [fokusId, setFokusId] = useState(null);

    // Kelompokkan per baris dengan urutan dari server.
    const baris = useMemo(() => {
        const peta = new Map();
        kursi.forEach((k) => {
            if (!peta.has(k.baris)) peta.set(k.baris, []);
            peta.get(k.baris).push(k);
        });
        return [...peta.entries()].map(([label, isi]) => ({ label, isi: [...isi].sort((a, b) => a.kolom - b.kolom) }));
    }, [kursi]);

    // Roving tabindex: hanya satu kursi yang ada di urutan Tab.
    const idTab = fokusId ?? (kursi.find((k) => tampilan(k).bisaDipilih) ?? kursi[0])?.id;

    const pindahFokus = (e, k) => {
        const arah = { ArrowLeft: [0, -1], ArrowRight: [0, 1], ArrowUp: [-1, 0], ArrowDown: [1, 0] }[e.key];
        if (!arah) return;
        e.preventDefault();
        const iBaris = baris.findIndex((b) => b.label === k.baris);
        const iKolom = baris[iBaris].isi.findIndex((x) => x.id === k.id);
        const barisTujuan = baris[Math.min(baris.length - 1, Math.max(0, iBaris + arah[0]))];
        const tujuan = barisTujuan.isi[Math.min(barisTujuan.isi.length - 1, Math.max(0, iKolom + arah[1]))];
        if (!tujuan) return;
        setFokusId(tujuan.id);
        wadah.current?.querySelector(`[data-kursi-id="${tujuan.id}"]`)?.focus();
    };

    const tombol = (k) => {
        const t = tampilan(k);
        const label = dipilih[k.id];
        const kelas = label ? 'bg-sky-500 border-sky-200 text-white ring-2 ring-sky-300' : t.kelas;

        return (
            <button
                key={k.id}
                type="button"
                data-kursi-id={k.id}
                data-kursi-kode={k.kode}
                data-kursi-status={k.status}
                tabIndex={k.id === idTab ? 0 : -1}
                aria-label={`Kursi ${k.kode}, ${t.status}${k.tier === 'vip' ? ', VIP' : ''}${label ? `, dipilih untuk ${label}` : ''}`}
                aria-pressed={label ? true : undefined}
                aria-disabled={!t.bisaDipilih}
                onClick={() => {
                    setSorot(k);
                    setFokusId(k.id);
                    if (t.bisaDipilih) onPilih?.(k);
                }}
                onKeyDown={(e) => pindahFokus(e, k)}
                onMouseEnter={() => setSorot(k)}
                onFocus={() => setSorot(k)}
                className={`w-10 h-10 sm:w-8 sm:h-8 shrink-0 rounded-lg border text-[11px] sm:text-[10px] font-bold flex items-center justify-center transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-white ${kelas} ${t.bisaDipilih ? 'cursor-pointer' : 'cursor-not-allowed'}`}
            >
                {label || t.isi || k.kolom}
            </button>
        );
    };

    return (
        <div className="space-y-4 min-w-0">
            <div className="text-center">
                <div className="w-full max-w-lg mx-auto py-2 rounded-xl bg-emerald-600/20 border border-emerald-500/40 text-emerald-300 font-black text-[11px] tracking-widest uppercase">
                    {judulPanggung}
                </div>
            </div>

            {/* Area peta: gulir horizontal di dalam wadah ini saja. */}
            <div ref={wadah} className="overflow-x-auto overscroll-x-contain pb-2 -mx-1 px-1" role="group" aria-label={labelPeta} data-peta-kursi>
                <div className="w-max mx-auto space-y-2">
                    {baris.map(({ label, isi }) => {
                        const tengah = Math.ceil(isi.length / 2);
                        return (
                            <div key={label} className="flex items-center gap-2">
                                <span className="w-6 text-center text-xs font-black text-slate-400" aria-hidden="true">{label}</span>
                                <div className="flex items-center gap-1.5">{isi.slice(0, tengah).map(tombol)}</div>
                                <div className="w-5 flex justify-center" aria-hidden="true"><div className="w-0.5 h-6 bg-slate-800" /></div>
                                <div className="flex items-center gap-1.5">{isi.slice(tengah).map(tombol)}</div>
                                <span className="w-6 text-center text-xs font-black text-slate-400" aria-hidden="true">{label}</span>
                            </div>
                        );
                    })}
                </div>
            </div>

            <div className="min-h-[1.25rem] text-[11px] text-slate-300 text-center" aria-live="polite" data-info-kursi>
                {sorot ? (info ? info(sorot) : `Kursi ${sorot.kode}: ${tampilan(sorot).status}`) : 'Arahkan kursor atau ketuk kursi untuk melihat keterangannya.'}
            </div>

            {legenda.length > 0 && (
                <ul className="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-[10px] font-bold text-slate-400">
                    {legenda.map((l) => (
                        <li key={l.label} className="flex items-center gap-1.5">
                            <span className={`w-3.5 h-3.5 rounded border ${l.kelas}`} aria-hidden="true" />
                            {l.label}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
