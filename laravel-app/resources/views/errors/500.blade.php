@extends('errors.layout')

@section('code', '500')
@section('icon', 'dns')
@section('title', 'Kendala pada Sistem')
@section('message', 'Terjadi kendala saat memproses permintaan Anda. Tim teknis telah menerima laporan ini. Silakan coba kembali beberapa saat lagi.')

@section('actions')
    <a href="{{ route('home') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-[#e2e2e2] hover:bg-[#f3f3f3] text-[#1a1c1c] text-sm font-semibold transition-all">
        <span class="material-symbols-outlined text-lg">arrow_back</span>
        Kembali
    </a>
@endsection
