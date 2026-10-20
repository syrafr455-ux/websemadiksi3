<x-layouts.app title="Daftar Akun SEMADIKSI">
    @php
        $kelasInput = 'w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all';
        $kelasLabel = 'block text-xs font-bold text-on-surface uppercase tracking-wider mb-2';
        $jalurAwal = old('jalur', session('unusa_status') ? 'unusa' : '');
        $statusUnusa = session('unusa_status');
    @endphp

    <div class="py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
        <div class="organic-blob w-96 h-96 bg-primary top-10 right-10"></div>
        <div class="organic-blob w-96 h-96 bg-secondary bottom-10 left-10"></div>

        <div class="max-w-2xl mx-auto bg-surface/90 backdrop-blur-xl border border-surface-variant/40 rounded-3xl p-8 sm:p-12 shadow-2xl relative z-10" x-data="{ jalur: @js($jalurAwal) }">
            <div class="text-center mb-8">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-primary to-primary-container mx-auto flex items-center justify-center text-white shadow-lg shadow-primary/20 mb-4">
                    <span class="material-symbols-outlined text-3xl">how_to_reg</span>
                </div>
                <h2 class="text-2xl font-extrabold tracking-tight text-on-surface">Daftar Akun SEMADIKSI</h2>
                <p class="text-sm text-outline mt-1.5">Pilih jalur pendaftaran yang sesuai dengan Anda.</p>
            </div>

            {{-- Langkah 1: pilih jalur --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8" role="radiogroup" aria-label="Jalur pendaftaran">
                <button type="button" role="radio" :aria-checked="jalur === 'unusa'" @click="jalur = 'unusa'" data-jalur="unusa"
                    :class="jalur === 'unusa' ? 'border-primary bg-primary/10 ring-2 ring-primary/30' : 'border-surface-variant/60 hover:border-primary/50'"
                    class="text-left p-5 rounded-2xl border transition-all">
                    <span class="material-symbols-outlined text-primary text-2xl">school</span>
                    <div class="font-extrabold text-on-surface mt-2">Mahasiswa UNUSA</div>
                    <div class="text-xs text-outline mt-1">Data akademik terisi otomatis dari NIM. Status penerima KIP-K ditentukan sistem.</div>
                </button>
                <button type="button" role="radio" :aria-checked="jalur === 'umum'" @click="jalur = 'umum'" data-jalur="umum"
                    :class="jalur === 'umum' ? 'border-primary bg-primary/10 ring-2 ring-primary/30' : 'border-surface-variant/60 hover:border-primary/50'"
                    class="text-left p-5 rounded-2xl border transition-all">
                    <span class="material-symbols-outlined text-primary text-2xl">public</span>
                    <div class="font-extrabold text-on-surface mt-2">Peserta Umum</div>
                    <div class="text-xs text-outline mt-1">Mahasiswa kampus lain atau masyarakat umum yang ingin mengikuti kegiatan.</div>
                </button>
            </div>

            {{-- Jalur Mahasiswa UNUSA: NIM -> link aktivasi ke email kampus --}}
            <div x-show="jalur === 'unusa'" @if($jalurAwal !== 'unusa') style="display: none" @endif data-bagian="unusa">
                @if($statusUnusa)
                    <div class="mb-6 p-4 rounded-2xl text-sm font-medium {{ $statusUnusa === 'terkirim' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-amber-50 text-amber-900 border border-amber-200' }}" role="status" data-status-unusa="{{ $statusUnusa }}">
                        {{ session('unusa_pesan') }}
                        @if($statusUnusa === 'sudah_terdaftar')
                            <a href="{{ route('login') }}" class="block mt-2 font-bold text-primary hover:underline">Masuk ke akun Anda</a>
                            <a href="{{ route('password.request') }}" class="block mt-1 font-bold text-primary hover:underline">Lupa kata sandi?</a>
                        @endif
                    </div>
                @endif

                <form action="{{ route('daftar.unusa.cek') }}" method="POST" class="space-y-5">
                    @csrf
                    <input type="hidden" name="jalur" value="unusa">
                    <div>
                        <label for="unusa_nim" class="{{ $kelasLabel }}">NIM UNUSA <span class="text-rose-500">*</span></label>
                        <input type="text" id="unusa_nim" name="nim" value="{{ $jalurAwal === 'unusa' ? old('nim') : '' }}" required autocomplete="off"
                            placeholder="Contoh: 2241010001" class="{{ $kelasInput }} @if($jalurAwal === 'unusa') @error('nim') border-rose-500 @enderror @endif">
                        @if($jalurAwal === 'unusa') @error('nim') <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p> @enderror @endif
                        <p class="text-xs text-outline mt-2">
                            Demi keamanan, data Anda baru ditampilkan setelah Anda membuka link aktivasi yang kami kirim ke <strong>email kampus</strong> Anda.
                        </p>
                    </div>
                    <button type="submit" class="w-full py-4 px-6 rounded-2xl bg-primary hover:bg-primary-container text-white font-bold text-base shadow-lg shadow-primary/25 transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-xl">mail</span>
                        Kirim Link Aktivasi
                    </button>
                </form>
            </div>

            {{-- Jalur Peserta Umum: field & validasi sama dengan form admin (App\Http\Requests\Pengguna\UserUmumRequest) --}}
            <form action="{{ route('register.post') }}" method="POST" class="space-y-6" x-show="jalur === 'umum'" @if($jalurAwal !== 'umum') style="display: none" @endif data-bagian="umum">
                @csrf
                <input type="hidden" name="jalur" value="umum">
                @php($umum = $jalurAwal === 'umum')

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
