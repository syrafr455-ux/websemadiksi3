import React from 'react';
import { Head, Link } from '@inertiajs/react';

export default function Error({ status, message }) {
    const errorDetails = {
        403: {
            title: 'Akses Dibatasi',
            description: message || 'Anda tidak memiliki hak akses untuk membuka halaman ini. Pastikan Anda telah masuk dengan akun yang sesuai.',
            icon: 'lock',
        },
        404: {
            title: 'Halaman Tidak Ditemukan',
            description: message || 'Alamat yang Anda tuju tidak ditemukan atau telah dipindahkan. Periksa kembali tautan yang Anda buka.',
            icon: 'search_off',
        },
        419: {
            title: 'Sesi Telah Berakhir',
            description: message || 'Halaman telah kedaluwarsa karena tidak ada aktivitas dalam waktu tertentu. Silakan muat ulang halaman.',
            icon: 'history',
        },
        429: {
            title: 'Terlalu Banyak Permintaan',
            description: message || 'Sistem menerima terlalu banyak permintaan dalam waktu singkat. Mohon tunggu beberapa saat sebelum mencoba kembali.',
            icon: 'speed',
        },
        500: {
            title: 'Kendala pada Sistem',
            description: message || 'Terjadi kendala saat memproses permintaan Anda. Tim teknis sedang menindaklanjuti. Silakan coba kembali nanti.',
            icon: 'dns',
        },
    };

    const info = errorDetails[status] || {
        title: 'Terjadi Kesalahan',
        description: message || 'Permintaan Anda tidak dapat diproses saat ini.',
        icon: 'error',
    };

    return (
        <div className="min-h-screen flex flex-col items-center justify-center p-6 bg-[#f9f9f9] text-[#1a1c1c] font-sans antialiased">
            <Head title={`${info.title} (${status}) - SEMADIKSI UNUSA`} />

            <div className="w-full max-w-lg bg-white border border-[#e2e2e2] rounded-2xl p-8 sm:p-10 shadow-sm text-center">
                <div className="w-14 h-14 rounded-xl bg-[#0d631b]/10 text-[#0d631b] mx-auto flex items-center justify-center mb-6">
                    <span className="material-symbols-outlined text-3xl">{info.icon}</span>
                </div>

                <span className="inline-block px-3 py-1 text-xs font-bold tracking-wider uppercase text-[#0d631b] bg-[#0d631b]/10 rounded-full mb-3">
                    Galat {status}
                </span>

                <h1 className="text-2xl font-bold tracking-tight text-[#1a1c1c] mb-2">
                    {info.title}
                </h1>

                <p className="text-sm text-[#707a6c] leading-relaxed mb-8">
                    {info.description}
                </p>

                <div className="flex flex-col sm:flex-row gap-3 justify-center">
                    {status === 419 || status === 429 ? (
                        <button
                            type="button"
                            onClick={() => window.location.reload()}
                            className="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-[#e2e2e2] hover:bg-[#f3f3f3] text-[#1a1c1c] text-sm font-semibold transition-all"
                        >
                            <span className="material-symbols-outlined text-lg">refresh</span>
                            Muat Ulang
                        </button>
                    ) : (
                        <Link
                            href="/dashboard"
                            className="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-[#e2e2e2] hover:bg-[#f3f3f3] text-[#1a1c1c] text-sm font-semibold transition-all"
                        >
                            <span className="material-symbols-outlined text-lg">dashboard</span>
                            Ke Dashboard
                        </Link>
                    )}

                    <Link
                        href="/"
                        className="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-[#0d631b] hover:bg-[#2e7d32] text-white text-sm font-semibold transition-all"
                    >
                        <span className="material-symbols-outlined text-lg">home</span>
                        Ke Beranda
                    </Link>
                </div>
            </div>

            <footer className="mt-8 text-xs text-[#707a6c] text-center">
                &copy; {new Date().getFullYear()} SEMADIKSI Universitas Nahdlatul Ulama Surabaya.
            </footer>
        </div>
    );
}
