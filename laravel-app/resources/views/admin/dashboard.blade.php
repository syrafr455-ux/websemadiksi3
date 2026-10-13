<x-layouts.admin title="Dashboard Pengelola" pageTitle="Dashboard Utama Pengurus" pageSubtitle="Ringkasan aktivitas beasiswa & basis data PostgreSQL">
    <div class="space-y-8">
        {{-- Stats Grid --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-950 text-blue-400 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-2xl">group</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block font-medium">Total Mahasiswa</span>
                    <span class="text-xl font-bold text-white mt-0.5 block">{{ number_format($stats['total_students']) }}</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-950 text-amber-400 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-2xl">pending_actions</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block font-medium">Perlu Verifikasi</span>
                    <span class="text-xl font-bold text-amber-400 mt-0.5 block">{{ number_format($stats['pending_verifications']) }}</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-950 text-emerald-400 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-2xl">event</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block font-medium">Kegiatan Aktif</span>
                    <span class="text-xl font-bold text-emerald-400 mt-0.5 block">{{ number_format($stats['total_activities']) }}</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-purple-950 text-purple-400 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-2xl">workspace_premium</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block font-medium">Sertifikat Terbit</span>
                    <span class="text-xl font-bold text-purple-400 mt-0.5 block">{{ number_format($stats['total_certificates']) }}</span>
                </div>
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
