import React from 'react';

/**
 * Menangkap galat saat render komponen di bawahnya supaya pengguna tidak melihat halaman putih kosong.
 * Dipasang di app.jsx, di atas komponen halaman Inertia: galat yang dilempar halaman itu sendiri
 * tidak bisa ditangkap oleh boundary yang berada di dalam layout.
 */
export default class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { galat: null };
    }

    static getDerivedStateFromError(galat) {
        return { galat };
    }

    componentDidCatch(galat, info) {
        console.error('Halaman gagal dimuat:', galat, info?.componentStack);
    }

    render() {
        if (!this.state.galat) return this.props.children;

        const diAdmin = typeof window !== 'undefined' && window.location.pathname.startsWith('/admin');

        return (
            <div className="min-h-screen flex items-center justify-center p-6 bg-slate-100 text-slate-900">
                <div role="alert" className="w-full max-w-md bg-white border border-slate-200 rounded-xl p-6 space-y-4">
                    <h1 className="text-lg font-bold">Halaman gagal dimuat</h1>
                    <p className="text-sm text-slate-600">
                        Terjadi kesalahan saat menampilkan halaman ini. Muat ulang halaman, atau kembali ke daftar.
                        Bila masalah berulang, hubungi admin SEMADIKSI dan sebutkan halaman yang sedang Anda buka.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() => window.location.reload()}
                            className="px-4 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-600 text-white text-sm font-bold"
                        >
                            Muat Ulang
                        </button>
                        <a
                            href={diAdmin ? '/admin/kegiatan' : '/dashboard'}
                            className="px-4 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-800 text-sm font-bold"
                        >
                            {diAdmin ? 'Kembali ke Daftar Kegiatan' : 'Kembali ke Dasbor'}
                        </a>
                    </div>
                </div>
            </div>
        );
    }
}
