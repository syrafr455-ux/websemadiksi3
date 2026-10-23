<x-layouts.app title="Daftar Akun SEMADIKSI">
    @php
        $kelasInput = 'w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all';
        $kelasLabel = 'block text-xs font-bold text-on-surface uppercase tracking-wider mb-2';
    @endphp

    <div class="py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto bg-surface border border-surface-container rounded-2xl p-8 sm:p-10 shadow-sm">
            <div class="text-center mb-8">
                <div class="w-12 h-12 rounded-xl bg-primary/10 mx-auto flex items-center justify-center text-primary mb-3">
                    <span class="material-symbols-outlined text-2xl">how_to_reg</span>
                </div>
                <h2 class="text-2xl font-bold tracking-tight text-on-surface">Pendaftaran Peserta Umum</h2>
                <p class="text-sm text-outline mt-1.5">Untuk mahasiswa dari luar UNUSA atau masyarakat umum.</p>
            </div>

            {{-- Jalur Peserta Umum: field & validasi sama dengan form admin (App\Http\Requests\Pengguna\UserUmumRequest) --}}
            <form action="{{ route('register.post') }}" method="POST" class="space-y-6">
                @csrf
                <input type="hidden" name="jalur" value="umum">
                @php($umum = true)

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="sm:col-span-2">
                        <label for="umum_name" class="{{ $kelasLabel }}">Nama Lengkap <span class="text-rose-500">*</span></label>
                        <input type="text" id="umum_name" name="name" value="{{ $umum ? old('name') : '' }}" required autocomplete="name"
                            placeholder="Contoh: Siti Aminah" class="{{ $kelasInput }} @if($umum) @error('name') border-rose-500 @enderror @endif">
                        <p class="text-xs text-outline mt-1">Ditulis persis seperti yang akan tercetak di sertifikat.</p>
                        @if($umum) @error('name') <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p> @enderror @endif
                    </div>

                    <div class="sm:col-span-2">
                        <label for="umum_asal_kampus" class="{{ $kelasLabel }}">Asal Kampus <span class="text-rose-500">*</span></label>
                        <input type="text" id="umum_asal_kampus" name="asal_kampus" value="{{ old('asal_kampus') }}" required
                            placeholder="Contoh: Universitas Airlangga" class="{{ $kelasInput }} @error('asal_kampus') border-rose-500 @enderror">
                        @error('asal_kampus') <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="umum_nim" class="{{ $kelasLabel }}">NIM <span class="text-rose-500">*</span></label>
                        <input type="text" id="umum_nim" name="nim" value="{{ $umum ? old('nim') : '' }}" required
                            placeholder="NIM di kampus asal" class="{{ $kelasInput }} @if($umum) @error('nim') border-rose-500 @enderror @endif">
                        @if($umum) @error('nim') <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p> @enderror @endif
                    </div>

                    <div>
                        <label for="umum_program_studi_teks" class="{{ $kelasLabel }}">Program Studi <span class="text-rose-500">*</span></label>
                        <input type="text" id="umum_program_studi_teks" name="program_studi_teks" value="{{ old('program_studi_teks') }}" required
                            placeholder="Contoh: S1 Teknik Informatika" class="{{ $kelasInput }} @error('program_studi_teks') border-rose-500 @enderror">
                        @error('program_studi_teks') <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="umum_email" class="{{ $kelasLabel }}">Email <span class="text-rose-500">*</span></label>
                        <input type="email" id="umum_email" name="email" value="{{ $umum ? old('email') : '' }}" required autocomplete="email"
                            placeholder="nama@email.com" class="{{ $kelasInput }} @if($umum) @error('email') border-rose-500 @enderror @endif">
                        @if($umum) @error('email') <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p> @enderror @endif
                    </div>

                    <div>
                        <label for="umum_phone" class="{{ $kelasLabel }}">Nomor Handphone <span class="text-rose-500">*</span></label>
                        <input type="tel" id="umum_phone" name="phone" value="{{ $umum ? old('phone') : '' }}" required autocomplete="tel"
                            placeholder="081234567890" class="{{ $kelasInput }} @if($umum) @error('phone') border-rose-500 @enderror @endif">
                        @if($umum) @error('phone') <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p> @enderror @endif
                    </div>

                    <div>
                        <label for="umum_password" class="{{ $kelasLabel }}">Kata Sandi Baru <span class="text-rose-500">*</span></label>
                        <input type="password" id="umum_password" name="password" required autocomplete="new-password"
                            placeholder="Minimal 8 karakter, huruf & angka" class="{{ $kelasInput }} @if($umum) @error('password') border-rose-500 @enderror @endif">
                        @if($umum) @error('password') <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p> @enderror @endif
                    </div>

                    <div>
                        <label for="umum_password_confirmation" class="{{ $kelasLabel }}">Konfirmasi Kata Sandi <span class="text-rose-500">*</span></label>
                        <input type="password" id="umum_password_confirmation" name="password_confirmation" required autocomplete="new-password"
                            placeholder="Ulangi kata sandi" class="{{ $kelasInput }}">
                    </div>
                </div>

                <p class="text-xs text-outline">Setelah mendaftar, kami mengirim link verifikasi ke email Anda. Akun aktif setelah email diverifikasi.</p>

                <button type="submit" class="w-full py-4 px-6 rounded-2xl bg-primary hover:bg-primary-container text-white font-bold text-base shadow-lg shadow-primary/25 transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-xl">check_circle</span>
                    Daftar sebagai Peserta Umum
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-surface-variant/40 text-center text-xs text-outline">
                Sudah memiliki akun?
                <a href="{{ route('login') }}" class="font-bold text-primary hover:underline">Masuk di sini</a>
            </div>
        </div>
    </div>
</x-layouts.app>
