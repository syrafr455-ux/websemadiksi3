@extends('layouts.admin')

@section('content')
    @include('admin.impor._pratinjau', [
        'judul' => 'Pratinjau Impor Data Mahasiswa UNUSA',
        'kolom' => ['nim' => 'NIM', 'nama_lengkap' => 'Nama Lengkap', 'email_kampus' => 'Email Kampus', 'program_studi' => 'Program Studi', 'angkatan' => 'Angkatan', 'status_aktif' => 'Status'],
        'routeSimpan' => 'admin.mahasiswa-unusa.impor',
        'routeBatal' => 'admin.mahasiswa-unusa.batal',
    ])
@endsection
