<x-layouts.admin title="Dashboard Pengelola" pageTitle="Dashboard Utama Pengurus" pageSubtitle="Ringkasan Utama aktivitas beasiswa & basis data PostgreSQL">
    <div class="space-y-8">
        {{-- Kartu Data Terkini Sistem (Pindahan dari Landing Page, Khusus Super Admin) --}}
        <div class="bg-slate-950 border border-slate-800 rounded-3xl p-6 sm:p-8 space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold">
                        <span class="material-symbols-outlined text-xl">analytics</span>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Data Terkini Sistem</h3>
                        <p class="text-xs text-slate-400">Statistik internal basis data untuk Super Administrator</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-900 border border-slate-700 text-slate-300 text-[11px] font-semibold">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    Super Admin
                </span>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80">
                    <span class="text-xs font-semibold text-slate-400 block">Mahasiswa Aktif</span>
                    <span class="text-2xl font-black text-amber-400 mt-1 block">{{ number_format($stats['active_students']) }}</span>
                    <span class="text-[10px] text-slate-500 mt-0.5 block">Dari total {{ number_format($stats['total_students']) }} terdaftar</span>
                </div>
                <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80">
                    <span class="text-xs font-semibold text-slate-400 block">Total Kegiatan</span>
                    <span class="text-2xl font-black text-emerald-400 mt-1 block">{{ number_format($stats['total_activities']) }}</span>
                    <span class="text-[10px] text-slate-500 mt-0.5 block">Seminar, Workshop & Agenda</span>
                </div>
                <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80">
                    <span class="text-xs font-semibold text-slate-400 block">Sertifikat Terbit</span>
                    <span class="text-2xl font-black text-blue-400 mt-1 block">{{ number_format($stats['total_certificates']) }}</span>
                    <span class="text-[10px] text-slate-500 mt-0.5 block">Terverifikasi Digital</span>
                </div>
                <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80">
                    <span class="text-xs font-semibold text-slate-400 block">Presensi Tercatat</span>
                    <span class="text-2xl font-black text-purple-400 mt-1 block">{{ number_format($stats['total_attendances']) }}</span>
                    <span class="text-[10px] text-slate-500 mt-0.5 block">Kehadiran Valid Peserta</span>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-amber-950/20 border border-amber-800/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center">
                        <span class="material-symbols-outlined text-base">payments</span>
                    </div>
                    <div>
                        <span class="font-bold text-white block">Status Pencairan KIP-K Periode Ini:</span>
                        <span class="text-[11px] text-slate-400">Verifikasi berkas & pengajuan mahasiswa</span>
                    </div>
                </div>
                <span class="font-bold text-amber-400 bg-amber-950/60 px-3 py-1.5 rounded-xl border border-amber-800/40">{{ $stats['pencairan_status'] }}</span>
            </div>
        </div>

        {{-- Two Tables: Recent Students & Recent Attendances --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Students --}}
            <div class="bg-slate-950 border border-slate-800 rounded-3xl p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-400 text-lg">person_add</span>
                        Pendaftar Mahasiswa Terbaru
                    </h3>
                    <a href="{{ route('admin.users') }}" class="text-xs font-bold text-amber-500 hover:underline">Kelola Semua</a>
                </div>

                <div class="divide-y divide-slate-800/60">
                    @forelse($recentStudents as $s)
                        <div class="py-3 flex items-center justify-between text-xs">
                            <div>
                                <span class="font-bold text-white block">{{ $s->name }}</span>
                                <span class="text-slate-400">{{ $s->nim }} &bull; {{ $s->prodi }}</span>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $s->verification_status === 'Verified' ? 'bg-emerald-950 text-emerald-400' : 'bg-amber-950 text-amber-400' }}">
                                {{ $s->verification_status }}
                            </span>
                        </div>
                    @empty
                        <div class="py-6 text-center text-slate-500 text-xs">Belum ada mahasiswa terdaftar.</div>
                    @endforelse
                </div>
            </div>

            {{-- Attendances --}}
            <div class="bg-slate-950 border border-slate-800 rounded-3xl p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-emerald-400 text-lg">camera_enhance</span>
                        Presensi Terkini
                    </h3>
                    <a href="{{ route('admin.attendances') }}" class="text-xs font-bold text-amber-500 hover:underline">Verifikasi Semua</a>
                </div>

                <div class="divide-y divide-slate-800/60">
                    @forelse($recentAttendances as $att)
                        <div class="py-3 flex items-center justify-between text-xs">
                            <div>
                                <span class="font-bold text-white block">{{ $att->student_name }}</span>
                                <span class="text-slate-400">{{ $att->activity_title }}</span>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-950 text-emerald-400">
                                {{ $att->status }}
                            </span>
                        </div>
                    @empty
                        <div class="py-6 text-center text-slate-500 text-xs">Belum ada presensi masuk.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
