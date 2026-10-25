import React, { useState } from 'react';
import { usePage, router } from '@inertiajs/react';

export default function ParticipantLayout({ children, title = 'Portal Kemahasiswaan' }) {
    const { auth = {}, flash = {} } = usePage().props;
    const user = auth.user;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    const handleLogout = (e) => {
        e.preventDefault();
        if (typeof router.clearHistory === 'function') {
            router.clearHistory();
        }
        router.post('/keluar');
    };

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans selection:bg-emerald-500 selection:text-white">
            {/* Navbar */}
            <header className="sticky top-0 z-40 bg-slate-900/90 backdrop-blur-md border-b border-slate-800/80">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <a href="/" className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/20 font-black text-white text-lg">
                                SD
                            </div>
                            <div>
                                <div className="text-base font-black tracking-wide bg-gradient-to-r from-emerald-400 via-teal-300 to-white bg-clip-text text-transparent">
                                    SEMADIKSI
                                </div>
                                <div className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">
                                    UNUSA • Official Portal
                                </div>
                            </div>
                        </a>
                    </div>

                    {/* Desktop Navigation */}
                    <nav className="hidden md:flex items-center gap-6 text-xs font-semibold">
                        <a href="/" className="text-slate-300 hover:text-emerald-400 transition-colors">Beranda</a>
                        <a href="/profil-semadiksi" className="text-slate-300 hover:text-emerald-400 transition-colors">Profil</a>
                        <a href="/dashboard" className="text-slate-300 hover:text-emerald-400 transition-colors">Dashboard</a>
                        <a href="/dashboard/kegiatan-saya" className="text-emerald-400 font-bold hover:text-emerald-300 transition-colors">Kegiatan Saya</a>
                        <a href="/berita-acara" className="text-slate-300 hover:text-emerald-400 transition-colors">Berita Acara</a>
                    </nav>

                    {/* User Action / Auth */}
                    <div className="hidden md:flex items-center gap-3">
                        {user ? (
                            <div className="flex items-center gap-3">
                                <div className="text-right">
                                    <div className="text-xs font-bold text-white">{user.name}</div>
                                    <div className="text-[10px] text-emerald-400 font-medium">
                                        {user.role_label || user.role || 'Mahasiswa'}
                                    </div>
                                </div>
                                <button
                                    onClick={handleLogout}
                                    className="p-2 rounded-xl bg-slate-800 hover:bg-rose-500/10 text-slate-400 hover:text-rose-400 transition-colors"
                                    title="Keluar"
                                >
                                    <span className="material-symbols-outlined text-sm">logout</span>
                                </button>
                            </div>
                        ) : (
                            <div className="flex items-center gap-2">
                                <a
                                    href="/masuk"
                                    className="px-4 py-2 rounded-xl text-xs font-bold text-slate-300 hover:text-white hover:bg-slate-800 transition-colors"
                                >
                                    Masuk
                                </a>
                                <a
                                    href="/daftar"
                                    className="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-md shadow-emerald-600/20 transition-all"
                                >
                                    Daftar Akun
                                </a>
                            </div>
                        )}
                    </div>

                    {/* Mobile Hamburger */}
                    <div className="md:hidden flex items-center">
                        <button
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800"
                        >
                            <span className="material-symbols-outlined">{mobileMenuOpen ? 'close' : 'menu'}</span>
                        </button>
                    </div>
                </div>

                {/* Mobile Drawer */}
                {mobileMenuOpen && (
                    <div className="md:hidden border-t border-slate-800 bg-slate-900 px-4 pt-3 pb-6 space-y-3 text-xs font-semibold">
                        <a href="/" className="block py-2 text-slate-300">Beranda</a>
                        <a href="/dashboard" className="block py-2 text-slate-300">Dashboard</a>
                        <a href="/dashboard/kegiatan-saya" className="block py-2 text-emerald-400 font-bold">Kegiatan Saya</a>
                        <a href="/berita-acara" className="block py-2 text-slate-300">Berita Acara</a>
                        {user ? (
                            <div className="pt-3 border-t border-slate-800 flex items-center justify-between">
                                <div>
                                    <div className="font-bold text-white">{user.name}</div>
                                    <div className="text-[10px] text-slate-400">{user.email}</div>
                                </div>
                                <button
                                    onClick={handleLogout}
                                    className="px-3 py-1.5 rounded-lg bg-rose-500/10 text-rose-400 font-bold"
                                >
                                    Keluar
                                </button>
                            </div>
                        ) : (
                            <div className="pt-3 border-t border-slate-800 flex gap-2">
                                <a href="/masuk" className="flex-1 py-2 text-center rounded-xl bg-slate-800 text-white">Masuk</a>
                                <a href="/daftar" className="flex-1 py-2 text-center rounded-xl bg-emerald-600 text-white font-bold">Daftar</a>
                            </div>
                        )}
                    </div>
                )}
            </header>

            {/* Flash Messages */}
            {(flash?.success || flash?.error || flash?.info) && (
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
                    {flash.success && (
                        <div className="p-4 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-medium flex items-center gap-2">
                            <span className="material-symbols-outlined text-emerald-400">check_circle</span>
                            <span>{flash.success}</span>
                        </div>
                    )}
                    {flash.error && (
                        <div className="p-4 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs font-medium flex items-center gap-2">
                            <span className="material-symbols-outlined text-rose-400">error</span>
                            <span>{flash.error}</span>
                        </div>
                    )}
                </div>
            )}

            {/* Main Content */}
            <main className="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
                {children}
            </main>

            {/* Footer */}
            <footer className="border-t border-slate-800/80 bg-slate-900 py-8 text-center text-xs text-slate-500">
                <div className="max-w-7xl mx-auto px-4">
                    <p className="font-medium text-slate-400">&copy; {new Date().getFullYear()} SEMADIKSI - Sahabat Mahasiswa Bidikmisi &amp; KIP-Kuliah UNUSA.</p>
                    <p className="mt-1 text-[11px]">Universitas Nahdlatul Ulama Surabaya • Membangun Generasi Rahmatan Lil 'Alamin</p>
                </div>
            </footer>
        </div>
    );
}
