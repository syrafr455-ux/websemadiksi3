@extends('errors.layout')

@section('code', '429')
@section('icon', 'speed')
@section('title', 'Terlalu Banyak Permintaan')
@section('message', 'Sistem menerima terlalu banyak permintaan dalam waktu singkat. Mohon tunggu beberapa saat sebelum mencoba kembali.')

@section('actions')
    <button onclick="window.location.reload()" type="button" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-[#e2e2e2] hover:bg-[#f3f3f3] text-[#1a1c1c] text-sm font-semibold transition-all">
        <span class="material-symbols-outlined text-lg">refresh</span>
        Coba Lagi
    </button>
@endsection
