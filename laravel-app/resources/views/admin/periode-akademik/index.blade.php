@extends('layouts.admin')

@section('title', 'Periode Akademik')
@section('pageTitle', 'Periode Akademik')

@section('content')
@php
    $kelasInput = 'w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-white text-sm';
    $kelasTombol = 'px-4 py-2 rounded-xl text-sm font-bold';
@endphp
<div class="space-y-6 max-w-5xl">
    <div>
        <h2 class="text-xl font-black text-white">Periode akademik</h2>
        <p class="text-sm text-slate-400 mt-1">
            Batas {{ $batas }} kegiatan yang dihadiri berlaku per periode dan dihitung ulang dari nol saat periode berganti.
            Isi tanggal dari kalender akademik UNUSA. Hari ini: {{ $hariIni }}.
        </p>
    </div>

    @if(! $periodeBerjalan)
        <div role="status" class="px-4 py-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-sm text-amber-200">
            Belum ada periode akademik aktif; batas kegiatan belum berlaku.
        </div>
    @else
        <div role="status" class="px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-sm text-emerald-200">
            Periode berjalan: <strong>{{ $periodeBerjalan->nama }}</strong>
            ({{ $periodeBerjalan->tanggal_mulai->translatedFormat('d M Y') }} sampai {{ $periodeBerjalan->tanggal_selesai->translatedFormat('d M Y') }}).
        </div>
    @endif

    @if($errors->any())
        <div role="alert" class="px-4 py-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-sm text-rose-200">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $galat)
                    <li>{{ $galat }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <h3 class="text-base font-bold text-white mb-3">Tambah periode</h3>
        <form method="POST" action="{{ route('admin.periode-akademik.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            @csrf
            <label class="md:col-span-2 text-sm text-slate-300">Nama (mis. Ganjil 2026/2027)
                <input type="text" name="nama" value="{{ old('nama') }}" maxlength="100" required class="{{ $kelasInput }} mt-1">
            </label>
            <label class="text-sm text-slate-300">Tanggal mulai
                <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}" required class="{{ $kelasInput }} mt-1">
            </label>
            <label class="text-sm text-slate-300">Tanggal selesai
                <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai') }}" required class="{{ $kelasInput }} mt-1">
            </label>
            <div class="md:col-span-4">
                <button type="submit" class="{{ $kelasTombol }} bg-emerald-700 hover:bg-emerald-600 text-white">Tambah</button>
            </div>
        </form>
    </section>

    <section class="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-4">
        <h3 class="text-base font-bold text-white">Daftar periode</h3>
        @forelse($periodes as $periode)
            <div class="rounded-xl border border-slate-800 bg-slate-950 p-4">
                <form method="POST" action="{{ route('admin.periode-akademik.update', $periode->id) }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    @csrf
                    @method('PUT')
                    <label class="md:col-span-2 text-sm text-slate-300">Nama
                        <input type="text" name="nama" value="{{ $periode->nama }}" maxlength="100" required class="{{ $kelasInput }} mt-1">
                    </label>
                    <label class="text-sm text-slate-300">Mulai
                        <input type="date" name="tanggal_mulai" value="{{ $periode->tanggal_mulai->toDateString() }}" required class="{{ $kelasInput }} mt-1">
                    </label>
                    <label class="text-sm text-slate-300">Selesai
                        <input type="date" name="tanggal_selesai" value="{{ $periode->tanggal_selesai->toDateString() }}" required class="{{ $kelasInput }} mt-1">
                    </label>
                    <div class="md:col-span-4 flex flex-wrap gap-2">
                        <button type="submit" class="{{ $kelasTombol }} bg-emerald-700 hover:bg-emerald-600 text-white">Simpan</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.periode-akademik.destroy', $periode->id) }}" class="mt-2" onsubmit="return confirm('Hapus periode ini? Batas kegiatan tidak berlaku bila tidak ada periode yang memuat hari ini.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="{{ $kelasTombol }} bg-rose-700 hover:bg-rose-600 text-white">Hapus</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-slate-300">Belum ada periode. Tambahkan periode Ganjil dan Genap dari kalender akademik di atas.</p>
        @endforelse
    </section>
</div>
@endsection
