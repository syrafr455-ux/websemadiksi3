<x-layouts.app title="Daftar Akun Mahasiswa">
    <div class="py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
        {{-- Background decorative blobs --}}
        <div class="organic-blob w-96 h-96 bg-primary top-10 right-10"></div>
        <div class="organic-blob w-96 h-96 bg-secondary bottom-10 left-10"></div>

        <div class="max-w-2xl mx-auto bg-surface/90 backdrop-blur-xl border border-surface-variant/40 rounded-3xl p-8 sm:p-12 shadow-2xl relative z-10" x-data="{ showPass: false, showConfirmPass: false }">
            <div class="text-center mb-8">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-primary to-primary-container mx-auto flex items-center justify-center text-white shadow-lg shadow-primary/20 mb-4">
                    <span class="material-symbols-outlined text-3xl">how_to_reg</span>
                </div>
                <h2 class="text-2xl font-extrabold tracking-tight text-on-surface">Registrasi Mahasiswa KIP-K</h2>
                <p class="text-sm text-outline mt-1.5">Lengkapi formulir di bawah ini dengan data identitas akademik valid UNUSA.</p>
            </div>

            <form action="{{ route('register.post') }}" method="POST" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    {{-- Nama Lengkap --}}
                    <div class="sm:col-span-2">
                        <label for="name" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                            Nama Lengkap <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            value="{{ old('name') }}" 
                            required 
                            placeholder="Contoh: Ahmad Fauzan" 
                            class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all @error('name') border-rose-500 @enderror"
                        >
                        @error('name')
                            <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label for="email" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                            Email Mahasiswa / Kampus <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="{{ old('email') }}" 
                            required 
                            placeholder="nama@student.unusa.ac.id" 
                            class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all @error('email') border-rose-500 @enderror"
                        >
                        @error('email')
                            <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- NIM --}}
                    <div>
                        <label for="nim" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                            NIM (Nomor Induk Mahasiswa) <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="nim" 
                            name="nim" 
                            value="{{ old('nim') }}" 
                            required 
                            placeholder="Contoh: 2240021001" 
                            class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all @error('nim') border-rose-500 @enderror"
                        >
                        @error('nim')
                            <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Nomor HP / WhatsApp --}}
                    <div>
                        <label for="phone" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                            No. WhatsApp Aktif
                        </label>
                        <input 
                            type="text" 
                            id="phone" 
                            name="phone" 
                            value="{{ old('phone') }}" 
                            placeholder="081234567890" 
                            class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all @error('phone') border-rose-500 @enderror"
                        >
                        @error('phone')
                            <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Tahun Angkatan --}}
                    <div>
                        <label for="angkatan" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                            Tahun Angkatan <span class="text-rose-500">*</span>
                        </label>
                        <select 
                            id="angkatan" 
                            name="angkatan" 
                            required 
                            class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all"
                        >
                            <option value="2026" {{ old('angkatan') == '2026' ? 'selected' : '' }}>2026</option>
                            <option value="2025" {{ old('angkatan') == '2025' ? 'selected' : '' }}>2025</option>
                            <option value="2024" {{ old('angkatan', '2024') == '2024' ? 'selected' : '' }}>2024</option>
                            <option value="2023" {{ old('angkatan') == '2023' ? 'selected' : '' }}>2023</option>
                            <option value="2022" {{ old('angkatan') == '2022' ? 'selected' : '' }}>2022</option>
                            <option value="2021" {{ old('angkatan') == '2021' ? 'selected' : '' }}>2021</option>
                        </select>
                    </div>

                    {{-- Program Studi --}}
                    <div>
                        <label for="prodi" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                            Program Studi <span class="text-rose-500">*</span>
                        </label>
                        <select 
                            id="prodi" 
                            name="prodi" 
                            required 
                            class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all"
                        >
                            <option value="S1 Sistem Informasi">S1 Sistem Informasi</option>
                            <option value="S1 Teknik Informatika">S1 Teknik Informatika</option>
                            <option value="S1 Manajemen">S1 Manajemen</option>
                            <option value="S1 Akuntansi">S1 Akuntansi</option>
                            <option value="S1 Pendidikan Dokter">S1 Pendidikan Dokter</option>
                            <option value="S1 Keperawatan">S1 Keperawatan</option>
                            <option value="S1 Kebidanan">S1 Kebidanan</option>
                            <option value="S1 Gizi">S1 Gizi</option>
                            <option value="S1 Kesehatan Masyarakat">S1 Kesehatan Masyarakat</option>
                            <option value="S1 PGSD">S1 PGSD</option>
                            <option value="S1 PG PAUD">S1 PG PAUD</option>
                            <option value="S1 Pendidikan Bahasa Inggris">S1 Pendidikan Bahasa Inggris</option>
                            <option value="D4 Analis Kesehatan">D4 Analis Kesehatan</option>
                            <option value="D4 Keselamatan & Kesehatan Kerja">D4 Keselamatan & Kesehatan Kerja</option>
                            <option value="D3 Keperawatan">D3 Keperawatan</option>
                            <option value="D3 Kebidanan">D3 Kebidanan</option>
                        </select>
                    </div>

                    {{-- Status KIP-K --}}
                    <div>
                        <label for="kip_status" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                            Kategori Penerima <span class="text-rose-500">*</span>
                        </label>
                        <select 
                            id="kip_status" 
                            name="kip_status" 
                            required 
                            class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all"
                        >
                            <option value="KIP UNUSA">KIP Kuliah UNUSA</option>
                            <option value="Umum">Mahasiswa Umum / Calon Pendaftar</option>
                        </select>
                    </div>

                    {{-- Universitas --}}
                    <div class="sm:col-span-2">
                        <label for="university" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                            Perguruan Tinggi <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="university" 
                            name="university" 
                            value="{{ old('university', 'Universitas Nahdlatul Ulama Surabaya') }}" 
                            required 
                            class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all"
                        >
                    </div>

                    {{-- Kata Sandi --}}
                    <div>
                        <label for="password" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                            Kata Sandi Baru <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                :type="showPass ? 'text' : 'password'" 
                                id="password" 
                                name="password" 
                                required 
                                placeholder="Minimal 8 karakter" 
                                class="w-full pl-4 pr-11 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all @error('password') border-rose-500 @enderror"
                            >
                            <button 
                                type="button" 
                                @click="showPass = !showPass" 
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface"
                            >
                                <span class="material-symbols-outlined text-xl" x-text="showPass ? 'visibility_off' : 'visibility'">visibility</span>
                            </button>
                        </div>
                        @error('password')
                            <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Konfirmasi Kata Sandi --}}
                    <div>
                        <label for="password_confirmation" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                            Konfirmasi Kata Sandi <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                :type="showConfirmPass ? 'text' : 'password'" 
                                id="password_confirmation" 
                                name="password_confirmation" 
                                required 
                                placeholder="Ulangi kata sandi" 
                                class="w-full pl-4 pr-11 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium transition-all"
                            >
                            <button 
                                type="button" 
                                @click="showConfirmPass = !showConfirmPass" 
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface"
                            >
                                <span class="material-symbols-outlined text-xl" x-text="showConfirmPass ? 'visibility_off' : 'visibility'">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pt-4">
                    <button 
                        type="submit" 
                        class="w-full py-4 px-6 rounded-2xl bg-primary hover:bg-primary-container text-white font-bold text-base shadow-lg shadow-primary/25 transition-all hover:scale-[1.01] active:scale-[0.99] flex items-center justify-center gap-2"
                    >
                        <span class="material-symbols-outlined text-xl">check_circle</span>
                        Daftar Akun Mahasiswa
                    </button>
                </div>
            </form>

            <div class="mt-8 pt-6 border-t border-surface-variant/40 text-center text-xs text-outline">
                Sudah memiliki akun terdaftar? 
                <a href="{{ route('login') }}" class="font-bold text-primary hover:underline">Masuk di sini</a>
            </div>
        </div>
    </div>
</x-layouts.app>
