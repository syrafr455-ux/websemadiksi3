@extends('layouts.admin')

@section('content')
<div class="p-6 md:p-8 space-y-8">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-black text-white">Data Pengguna &amp; Mahasiswa</h2>
            <p class="text-xs text-slate-400 mt-1">Manajemen akun mahasiswa KIP-K, status verifikasi berkas, dan hak akses.</p>
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4">
        <form action="{{ route('admin.users') }}" method="GET" class="flex flex-col sm:flex-row gap-4 items-stretch sm:items-center justify-between">
            <div class="relative flex-1">
                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-lg">search</span>
                <input 
                    type="text" 
                    name="q" 
                    value="{{ request('q') }}" 
                    placeholder="Cari berdasarkan nama, NIM, atau email mahasiswa..."
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500"
                >
            </div>

            <div class="flex items-center gap-3">
                <select name="role" onchange="this.form.submit()" class="px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-xs focus:outline-none focus:border-amber-500">
                    <option value="">Semua Peran</option>
                    @foreach (\App\Enums\UserRole::options() as $opsi)
                        <option value="{{ $opsi['value'] }}" {{ request('role') === $opsi['value'] ? 'selected' : '' }}>{{ $opsi['label'] }}</option>
                    @endforeach
                </select>

                <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs">
                    Cari
                </button>

                @if(request()->hasAny(['q', 'role']))
                    <a href="{{ route('admin.users') }}" class="px-4 py-2.5 rounded-xl border border-slate-800 text-slate-400 hover:text-white text-xs font-semibold">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Users Table --}}
    <div class="bg-slate-950 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-900 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-bold">
                    <tr>
                        <th class="px-6 py-4">Mahasiswa / Akun</th>
                        <th class="px-6 py-4">NIM &amp; Angkatan</th>
                        <th class="px-6 py-4">Program Studi</th>
                        <th class="px-6 py-4">Status Akun</th>
                        <th class="px-6 py-4">Verifikasi</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-300">
                    @forelse($users as $u)
                        <tr class="hover:bg-slate-900/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center font-bold text-amber-500 shrink-0 border border-slate-700">
                                        {{ strtoupper(substr($u->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-white text-sm flex items-center gap-2">
                                            {{ $u->name }}
                                            @if($u->isStaff())
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">{{ $u->role->label() }}</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-slate-400">{{ $u->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-200">{{ $u->nim ?: '-' }}</div>
                                <div class="text-[11px] text-slate-400">Angkatan {{ $u->angkatan ?: '-' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-300">{{ $u->prodi ?: 'Umum' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $u->university }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @if($u->is_blocked)
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        Dinonaktifkan
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        Aktif
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($u->verification_status === 'Verified')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-sky-500/10 text-sky-400 border border-sky-500/20">
                                        Terverifikasi
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        Pending
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if($u->verification_status !== 'Verified')
                                        <form action="{{ route('admin.users.verify', $u->id) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-sky-600/20 hover:bg-sky-600/30 text-sky-400 font-bold text-[11px] transition-colors" title="Verifikasi Akun">
                                                Verifikasi
                                            </button>
                                        </form>
                                    @endif

                                    <form action="{{ route('admin.users.toggle-block', $u->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mengubah status aktif akun {{ $u->name }}?')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="p-1.5 rounded-lg {{ $u->is_blocked ? 'text-emerald-400 hover:bg-emerald-950/40' : 'text-rose-400 hover:bg-rose-950/40' }} transition-colors" title="{{ $u->is_blocked ? 'Aktifkan Akun' : 'Nonaktifkan Akun' }}">
                                            <span class="material-symbols-outlined text-base">
                                                {{ $u->is_blocked ? 'lock_open' : 'block' }}
                                            </span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                Tidak ada data pengguna yang sesuai dengan pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
