@extends('errors.layout')

@section('code', '419')
@section('icon', 'history')
@section('title', 'Sesi Telah Berakhir')
@section('message', 'Halaman atau formulir telah kedaluwarsa karena tidak ada aktivitas dalam waktu tertentu. Silakan muat ulang halaman dan coba kembali.')

@section('actions')
    <button onclick="window.location.reload()" type="button" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-[#e2e2e2] hover:bg-[#f3f3f3] text-[#1a1c1c] text-sm font-semibold transition-all">
        <span class="material-symbols-outlined text-lg">refresh</span>
        Muat Ulang Halaman
    </button>
@endsection
