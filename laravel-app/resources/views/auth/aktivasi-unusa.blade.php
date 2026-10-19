<x-layouts.app title="Aktivasi Akun Mahasiswa UNUSA">
    @php
        $kelasInput = 'w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all';
        $kelasLabel = 'block text-xs font-bold text-on-surface uppercase tracking-wider mb-2';
    @endphp

    <div class="py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto bg-surface/90 border border-surface-variant/40 rounded-3xl p-8 sm:p-12 shadow-2xl space-y-8">
            <div class="text-center">
                <h2 class="text-2xl font-extrabold tracking-tight text-on-surface">Aktivasi Akun Mahasiswa UNUSA</h2>
                <p class="text-sm text-outline mt-1.5">Data akademik diambil otomatis dari sistem UNUSA dan tidak dapat diubah di sini.</p>
            </div>

            {{-- Data otomatis: ditampilkan sebagai teks (bukan input), server tidak menerima perubahan apa pun atas data ini. --}}
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-5 rounded-2xl bg-surface-container border border-surface-variant/40 text-sm" data-data-otomatis>
                @foreach([
                    'Nama Lengkap' => $mahasiswa['nama_lengkap'],
                    'NIM' => $mahasiswa['nim'],
                    'Program Studi' => $mahasiswa['program_studi'] ?? '-',
                    'Fakultas' => $mahasiswa['fakultas'] ?? '-',
                    'Angkatan' => $mahasiswa['angkatan'] ?? '-',
                    'Email Kampus' => $mahasiswa['email_kampus'],
                ] as $label => $nilai)
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-outline">{{ $label }}</dt>
                        <dd class="font-semibold text-on-surface mt-0.5 break-all">{{ $nilai }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="text-xs text-outline -mt-4">Data tidak sesuai? Hubungi admin SEMADIKSI / Biro Kemahasiswaan sebelum melanjutkan.</p>

            <form action="{{ $aksi }}" method="POST" class="space-y-5">
                @csrf
                <div>
                    <label for="aktivasi_phone" class="{{ $kelasLabel }}">Nomor WhatsApp <span class="text-rose-500">*</span></label>
                    <input type="tel" id="aktivasi_phone" name="phone" value="{{ old('phone') }}" required autocomplete="tel"
                        placeholder="081234567890" class="{{ $kelasInput }} @error('phone') border-rose-500 @enderror">
                    @error('phone') <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="aktivasi_password" class="{{ $kelasLabel }}">Kata Sandi <span class="text-rose-500">*</span></label>
                        <input type="password" id="aktivasi_password" name="password" required autocomplete="new-password"
                            placeholder="Minimal 8 karakter, huruf & angka" class="{{ $kelasInput }} @error('password') border-rose-500 @enderror">
                        @error('password') <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="aktivasi_password_confirmation" class="{{ $kelasLabel }}">Konfirmasi Kata Sandi <span class="text-rose-500">*</span></label>
                        <input type="password" id="aktivasi_password_confirmation" name="password_confirmation" required autocomplete="new-password" class="{{ $kelasInput }}">
                    </div>
                </div>
                <button type="submit" class="w-full py-4 px-6 rounded-2xl bg-primary hover:bg-primary-container text-white font-bold text-base shadow-lg shadow-primary/25 transition-all">
                    Aktifkan Akun
                </button>
            </form>
        </div>
    </div>
</x-layouts.app>
