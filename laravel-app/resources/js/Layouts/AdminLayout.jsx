import React, { useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';

export default function AdminLayout({ children, title = 'Panel Administrator' }) {
    const { auth = {}, flash = {} } = usePage().props;
    const user = auth.user || {};
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [flashVisible, setFlashVisible] = useState(true);

    const navItems = [
        { label: 'Ringkasan Utama', href: '/admin', icon: 'dashboard', active: window.location.pathname === '/admin' },
        { label: 'Kegiatan Enterprise', href: '/admin/kegiatan', icon: 'event', active: window.location.pathname.startsWith('/admin/kegiatan') },
        { label: 'Penilaian Karya', href: '/admin/penilaian', icon: 'rate_review', active: window.location.pathname.startsWith('/admin/penilaian') },
        { label: 'Data Pengguna', href: '/admin/pengguna', icon: 'group', active: window.location.pathname.startsWith('/admin/pengguna') },
        { label: 'Data Mahasiswa UNUSA', href: '/admin/mahasiswa-unusa', icon: 'school', active: window.location.pathname.startsWith('/admin/mahasiswa-unusa') },
        { label: 'Daftar Penerima KIP-K', href: '/admin/penerima-kipk', icon: 'badge', active: window.location.pathname.startsWith('/admin/penerima-kipk') },
        { label: 'Presensi & Kehadiran', href: '/admin/absensi', icon: 'how_to_reg', active: window.location.pathname.startsWith('/admin/absensi') },
        { label: 'Sertifikat Digital', href: '/admin/sertifikat', icon: 'workspace_premium', active: window.location.pathname.startsWith('/admin/sertifikat') },
        { label: 'Verifikasi Berkas KIP-K', href: '/admin/berkas', icon: 'folder_shared', active: window.location.pathname.startsWith('/admin/berkas') },
    ];

    const handleLogout = (e) => {
        e.preventDefault();
        router.post('/keluar');
    };

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 flex font-sans selection:bg-emerald-500 selection:text-white">
            {/* Mobile Sidebar Overlay */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/70 backdrop-blur-sm lg:hidden transition-opacity"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Sidebar */}
            <aside
                className={`fixed top-0 bottom-0 left-0 z-50 w-72 bg-slate-900 border-r border-slate-800/80 flex flex-col transition-transform duration-300 ease-in-out lg:translate-x-0 ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                {/* Logo & Header */}
                <div className="p-6 border-b border-slate-800/80 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/20 font-black text-white text-lg tracking-wider">
                            SD
                        </div>
                        <div>
                            <div className="text-base font-black tracking-wide bg-gradient-to-r from-emerald-400 via-teal-300 to-white bg-clip-text text-transparent">
                                SEMADIKSI
                            </div>
                            <div className="text-[10px] uppercase font-bold text-slate-400 tracking-widest">
                                Admin Portal • UNUSA
                            </div>
                        </div>
                    </div>
                    <button
                        className="lg:hidden text-slate-400 hover:text-white p-1"
                        onClick={() => setSidebarOpen(false)}
                    >
                        <span className="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>

                {/* User Chip */}
                <div className="p-4 mx-4 my-3 rounded-2xl bg-slate-950/70 border border-slate-800/60 flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 font-black flex items-center justify-center text-sm">
                        {user.name ? user.name.charAt(0).toUpperCase() : 'A'}
                    </div>
                    <div className="flex-1 min-w-0">
                        <div className="text-xs font-bold text-white truncate">{user.name || 'Admin'}</div>
                        <div className="text-[10px] text-slate-400 truncate">{user.email || '-'}</div>
                        <div className="mt-1">
                            <span className="inline-block px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-wider bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                {user.role_label || user.role || 'Staff'}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Navigation Links */}
                <nav className="flex-1 px-4 py-2 space-y-1 overflow-y-auto">
                    {navItems.map((item) => (
                        <a
                            key={item.href}
                            href={item.href}
                            className={`flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all ${
                                item.active
                                    ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/25 font-bold'
                                    : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                            }`}
                        >
                            <span className="material-symbols-outlined text-lg">{item.icon}</span>
                            <span>{item.label}</span>
                        </a>
                    ))}
                </nav>

                {/* Footer / Quick Actions */}
                <div className="p-4 border-t border-slate-800/80 space-y-2">
                    <a
                        href="/dashboard"
                        className="flex items-center justify-center gap-2 w-full px-3 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-white hover:bg-slate-800/80 transition-colors"
                    >
                        <span className="material-symbols-outlined text-base">dashboard_customize</span>
                        <span>Portal Mahasiswa</span>
                    </a>
                    <button
                        onClick={handleLogout}
                        className="flex items-center justify-center gap-2 w-full px-3 py-2 rounded-xl text-xs font-bold text-rose-400 hover:bg-rose-500/10 hover:text-rose-300 transition-colors"
                    >
                        <span className="material-symbols-outlined text-base">logout</span>
                        <span>Keluar Sistem</span>
                    </button>
                </div>
            </aside>

            {/* Main Content Area */}
            <div className="flex-1 lg:pl-72 flex flex-col min-w-0">
                {/* Topbar */}
                <header className="sticky top-0 z-30 h-16 bg-slate-900/80 backdrop-blur-md border-b border-slate-800/80 px-6 flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <button
                            onClick={() => setSidebarOpen(true)}
                            className="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 focus:outline-none"
                        >
                            <span className="material-symbols-outlined">menu</span>
                        </button>
                        <h1 className="text-sm font-bold text-white tracking-wide">{title}</h1>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium">
                            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>Sistem Terhubung</span>
                        </div>
                    </div>
                </header>

                {/* Flash Messages */}
                {flashVisible && (flash.success || flash.error || flash.info) && (
                    <div className="p-4 px-6">
                        {flash.success && (
                            <div className="flex items-center justify-between p-4 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-medium shadow-md">
                                <div className="flex items-center gap-2">
                                    <span className="material-symbols-outlined text-emerald-400">check_circle</span>
                                    <span>{flash.success}</span>
                                </div>
                                <button onClick={() => setFlashVisible(false)} className="text-emerald-400 hover:text-white">
                                    <span className="material-symbols-outlined text-sm">close</span>
                                </button>
                            </div>
                        )}
                        {flash.error && (
                            <div className="flex items-center justify-between p-4 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs font-medium shadow-md">
                                <div className="flex items-center gap-2">
                                    <span className="material-symbols-outlined text-rose-400">error</span>
                                    <span>{flash.error}</span>
                                </div>
                                <button onClick={() => setFlashVisible(false)} className="text-rose-400 hover:text-white">
                                    <span className="material-symbols-outlined text-sm">close</span>
                                </button>
                            </div>
                        )}
                    </div>
                )}

                {/* Body Content */}
                <main className="flex-1 p-6 lg:p-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
