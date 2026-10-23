import React from 'react';

export default function GuestLayout({ judul, subjudul, children }) {
    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col items-center justify-center px-4 py-12 font-sans">
            <a href="/" className="flex items-center gap-3 mb-8">
                <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center font-black text-white text-lg">
                    SD
                </div>
                <div className="text-lg font-black tracking-wide text-white">SEMADIKSI</div>
            </a>

            <div className="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-10 shadow-2xl">
                <h1 className="text-2xl font-extrabold text-white">{judul}</h1>
                {subjudul && <p className="text-sm text-slate-400 mt-1.5">{subjudul}</p>}
                <div className="mt-6">{children}</div>
            </div>
        </div>
    );
}
