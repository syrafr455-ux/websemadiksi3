@extends('layouts.admin')

@section('content')
    {{-- Nomor pendaftaran hanya ditampilkan tersamar di pratinjau. --}}
    @include('admin.impor._pratinjau', [
        'judul' => 'Pratinjau Impor Daftar Penerima KIP-K',
        'kolom' => ['nomor_tersamar' => 'No. Pendaftaran (tersamar)', 'nama_lengkap' => 'Nama Lengkap', 'nim' => 'NIM', 'program_studi_teks' => 'Program Studi', 'angkatan' => 'Angkatan', 'status_penetapan' => 'Status Penetapan'],
        'routeSimpan' => 'admin.penerima-kipk.impor',
        'routeBatal' => 'admin.penerima-kipk.batal',
    ])
@endsection
