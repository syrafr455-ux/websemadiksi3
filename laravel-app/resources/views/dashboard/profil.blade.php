<x-layouts.dashboard title="Profil & Keamanan" pageTitle="Pengaturan Profil" pageSubtitle="Kelola informasi data diri akademik dan kata sandi Anda">
    <div class="max-w-4xl mx-auto space-y-8">
        {{-- Profile Card --}}
        <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-10 shadow-sm space-y-6">
            <h3 class="text-base font-bold text-on-surface">Data Identitas Akademik</h3>

            <form action="{{ route('dashboard.profil.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="flex items-center gap-6">
                    <div class="w-20 h-20 rounded-full bg-primary/10 border-2 border-primary/20 flex items-center justify-center overflow-hidden shrink-0">
                        @if($user->avatar_url)
                            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                        @else
                            <span class="material-symbols-outlined text-4xl text-primary">person</span>
                        @endif
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-1.5">Foto Profil</label>
                        <input type="file" name="avatar" accept="image/*" class="block w-full text-xs text-outline file:mr-4 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-surface-container file:text-on-surface hover:file:bg-primary hover:file:text-white cursor-pointer">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Nama Lengkap</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full px-4 py-2.5 bg-surface-container rounded-xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Email Terdaftar</label>
                        <input type="text" value="{{ $user->email }}" disabled class="w-full px-4 py-2.5 bg-surface-container-highest rounded-xl border border-surface-variant/40 text-xs font-medium text-outline cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">NIM</label>
                        <input type="text" value="{{ $user->nim }}" disabled class="w-full px-4 py-2.5 bg-surface-container-highest rounded-xl border border-surface-variant/40 text-xs font-medium text-outline cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">No. WhatsApp</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="w-full px-4 py-2.5 bg-surface-container rounded-xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Angkatan</label>
                        <input type="text" name="angkatan" value="{{ old('angkatan', $user->angkatan) }}" required class="w-full px-4 py-2.5 bg-surface-container rounded-xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Program Studi</label>
                        <input type="text" name="prodi" value="{{ old('prodi', $user->prodi) }}" required class="w-full px-4 py-2.5 bg-surface-container rounded-xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Perguruan Tinggi</label>
                        <input type="text" name="university" value="{{ old('university', $user->university) }}" required class="w-full px-4 py-2.5 bg-surface-container rounded-xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-3 rounded-2xl bg-primary hover:bg-primary-container text-white font-bold text-xs shadow-md shadow-primary/20 flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">save</span>
                        Perbarui Data Diri
                    </button>
                </div>
            </form>
        </div>

        {{-- Security / Change Password Card --}}
        <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-10 shadow-sm space-y-6">
            <h3 class="text-base font-bold text-on-surface">Ubah Kata Sandi Akun</h3>

            <form action="{{ route('dashboard.profil.password') }}" method="POST" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Kata Sandi Saat Ini</label>
                    <input type="password" name="current_password" required placeholder="Masukkan kata sandi lama" class="w-full px-4 py-2.5 bg-surface-container rounded-xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                    @error('current_password')
                        <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Kata Sandi Baru</label>
                        <input type="password" name="password" required placeholder="Minimal 8 karakter" class="w-full px-4 py-2.5 bg-surface-container rounded-xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                        @error('password')
                            <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Ulangi Kata Sandi Baru</label>
                        <input type="password" name="password_confirmation" required placeholder="Ulangi kata sandi baru" class="w-full px-4 py-2.5 bg-surface-container rounded-xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-3 rounded-2xl bg-primary hover:bg-primary-container text-white font-bold text-xs shadow-md shadow-primary/20 flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">lock_reset</span>
                        Simpan Kata Sandi Baru
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.dashboard>
