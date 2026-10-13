<x-layouts.app title="Presensi Kegiatan Cepat">
    <div class="py-16 px-4 sm:px-6 lg:px-8 max-w-2xl mx-auto">
        <div class="text-center mb-10 space-y-3">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-bold">
                <span class="material-symbols-outlined text-sm">camera_enhance</span>
                Presensi & Log Kehadiran Digital
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-on-surface">Presensi Mandiri Kegiatan</h1>
            <p class="text-sm text-outline max-w-lg mx-auto">
                Isi data kehadiran dan unggah swafoto (selfie) atau bukti kehadiran di lokasi acara untuk dicatat secara langsung di server PostgreSQL SEMADIKSI.
            </p>
        </div>

        <div class="bg-surface border border-surface-variant/40 rounded-3xl p-6 sm:p-10 shadow-xl">
            <form action="{{ route('absensi.public.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                {{-- Pilih Kegiatan --}}
                <div>
                    <label for="activity_id" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                        Pilih Kegiatan / Agenda <span class="text-rose-500">*</span>
                    </label>
                    <select 
                        name="activity_id" 
                        id="activity_id" 
                        required 
                        class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary text-sm font-medium"
                    >
                        <option value="">-- Pilih Kegiatan Aktif --</option>
                        @foreach($activities as $act)
                            <option value="{{ $act->id }}">{{ $act->title }} ({{ $act->date_text }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- Nama Lengkap --}}
                <div>
                    <label for="student_name" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                        Nama Lengkap Mahasiswa <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="student_name" 
                        name="student_name" 
                        value="{{ Auth::user()?->name ?? old('student_name') }}" 
                        required 
                        placeholder="Contoh: Ahmad Fauzan" 
                        class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary text-sm font-medium"
                    >
                </div>

                {{-- NIM --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="student_nim" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                            NIM <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="student_nim" 
                            name="student_nim" 
                            value="{{ Auth::user()?->nim ?? old('student_nim') }}" 
                            required 
                            placeholder="Contoh: 2240021001" 
                            class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary text-sm font-medium"
                        >
                    </div>
                    <div>
                        <label for="student_phone" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                            No. WhatsApp
                        </label>
                        <input 
                            type="text" 
                            id="student_phone" 
                            name="student_phone" 
                            value="{{ Auth::user()?->phone ?? old('student_phone') }}" 
                            placeholder="081234567890" 
                            class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary text-sm font-medium"
                        >
                    </div>
                </div>

                {{-- Perguruan Tinggi --}}
                <div>
                    <label for="university" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                        Perguruan Tinggi <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="university" 
                        name="university" 
                        value="{{ Auth::user()?->university ?? 'Universitas Nahdlatul Ulama Surabaya' }}" 
                        required 
                        class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary text-sm font-medium"
                    >
                </div>

                {{-- Upload Bukti Foto Selfie --}}
                <div>
                    <label for="proof_image" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                        Foto Selfie / Bukti Kehadiran di Tempat <span class="text-rose-500">*</span>
                    </label>
                    <div class="p-6 bg-surface-container rounded-2xl border-2 border-dashed border-surface-variant/60 text-center space-y-2">
                        <span class="material-symbols-outlined text-4xl text-primary">add_a_photo</span>
                        <p class="text-xs text-outline">Ambil foto atau pilih file gambar (JPG, PNG, WebP) maksimal 5MB.</p>
                        <input 
                            type="file" 
                            id="proof_image" 
                            name="proof_image" 
                            accept="image/*" 
                            capture="user"
                            required 
                            class="block w-full text-xs text-outline file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary file:text-white hover:file:bg-primary-container cursor-pointer"
                        >
                    </div>
                </div>

                {{-- Lokasi & Catatan --}}
                <div>
                    <label for="location_name" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                        Nama Lokasi / Ruangan
                    </label>
                    <input 
                        type="text" 
                        id="location_name" 
                        name="location_name" 
                        value="Auditorium Kampus B UNUSA Lantai 9" 
                        class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 focus:border-primary text-sm font-medium"
                    >
                </div>

                <button 
                    type="submit" 
                    class="w-full py-4 px-6 rounded-2xl bg-primary hover:bg-primary-container text-white font-bold text-sm shadow-xl shadow-primary/20 flex items-center justify-center gap-2 transition-all hover:scale-[1.01] active:scale-[0.99]"
                >
                    <span class="material-symbols-outlined text-xl">how_to_reg</span>
                    Kirim Bukti Presensi
                </button>
            </form>
        </div>
    </div>
</x-layouts.app>
