@extends('errors.layout')

@section('code', '403')
@section('icon', 'lock')
@section('title', 'Akses Dibatasi')
@section('message', 'Anda tidak memiliki hak akses untuk membuka halaman ini. Pastikan Anda telah masuk dengan akun yang sesuai.')

@section('actions')
    @auth
        <a href="{{ route('dashboard.index') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-[#e2e2e2] hover:bg-[#f3f3f3] text-[#1a1c1c] text-sm font-semibold transition-all">
            <span class="material-symbols-outlined text-lg">dashboard</span>
            Buka Dashboard
        </a>
    @else
        <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-[#e2e2e2] hover:bg-[#f3f3f3] text-[#1a1c1c] text-sm font-semibold transition-all">
            <span class="material-symbols-outlined text-lg">login</span>
            Masuk Akun
        </a>
    @endauth
@endsection
