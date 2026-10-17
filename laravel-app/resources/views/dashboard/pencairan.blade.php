<x-layouts.dashboard title="Pengajuan Pencairan KIP-K" pageTitle="Pengajuan Pencairan Beasiswa" pageSubtitle="Tahap 1: Verifikasi berkas administrasi dan keaktifan studi">
    <div class="max-w-3xl mx-auto space-y-8">
        {{-- Info Alert --}}
        <div class="p-5 rounded-2xl bg-primary/10 border border-primary/20 text-on-surface flex items-start gap-4">
            <span class="material-symbols-outlined text-primary text-2xl shrink-0 mt-0.5">info</span>
            <div class="text-xs leading-relaxed space-y-1">
                <p class="font-bold text-sm text-primary">Persyaratan Pencairan Dana Bantuan KIP-Kuliah</p>
                <p class="text-outline">Pastikan Anda telah menyiapkan dokumen rekomendasi program studi dan surat pernyataan aktif kuliah. Berkas dapat diunggah dalam format tautan Google Drive publik atau PDF resmi.</p>
            </div>
        </div>

        {{-- Dynamic Form Card --}}
        <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-10 shadow-sm">
            <div class="mb-8 pb-6 border-b border-surface-variant/30">
                <h3 class="text-xl font-bold text-on-surface">{{ $form->title ?? 'Formulir Pengajuan Pencairan KIP-Kuliah' }}</h3>
                <p class="text-xs text-outline mt-1">{{ $form->subtitle ?? 'Lengkapi seluruh data di bawah ini dengan cermat.' }}</p>
            </div>

            <form action="{{ route('dashboard.pencairan.store') }}" method="POST" class="space-y-8">
                @csrf

                @if($form && $form->sections->count() > 0)
                    @foreach($form->sections as $section)
                        <div class="space-y-5 pt-4 first:pt-0">
                            <div class="p-4 rounded-2xl bg-surface-container border border-surface-variant/30">
                                <h4 class="text-sm font-bold text-primary">{{ $section->title }}</h4>
                                @if($section->description)
                                    <p class="text-xs text-outline mt-0.5">{{ $section->description }}</p>
                                @endif
                            </div>

                            @foreach($section->questions as $q)
                                <div class="space-y-2">
                                    <label class="block text-xs font-bold text-on-surface uppercase tracking-wider">
                                        {{ $q->title }} @if($q->required) <span class="text-rose-500">*</span> @endif
                                    </label>

                                    @if($q->type === 'Drop-down' && !empty($q->options))
                                        <select name="{{ $q->question_code ?? 'q_'.$q->id }}" @if($q->required) required @endif class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                                            @foreach($q->options as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($q->type === 'Paragraf')
                                        <textarea name="{{ $q->question_code ?? 'q_'.$q->id }}" rows="3" placeholder="{{ $q->placeholder ?? 'Tuliskan jawaban Anda...' }}" @if($q->required) required @endif class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium focus:border-primary"></textarea>
                                    @else
                                        <input type="text" name="{{ $q->question_code ?? 'q_'.$q->id }}" value="{{ Auth::user()->{$q->question_code} ?? '' }}" placeholder="{{ $q->placeholder ?? 'Jawaban Anda' }}" @if($q->required) required @endif class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                                    @endif

                                    @if($q->help_text)
                                        <p class="text-[11px] text-outline italic">{{ $q->help_text }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                @else
                    {{-- Default standard fields fallback --}}
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" value="{{ Auth::user()->name }}" required class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">NIM</label>
                            <input type="text" name="nim" value="{{ Auth::user()->nim }}" required class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">Tautan Berkas Rekomendasi Prodi (Google Drive / PDF)</label>
                            <input type="url" name="surat_rekomendasi" placeholder="https://drive.google.com/..." required class="w-full px-4 py-3 bg-surface-container rounded-2xl border border-surface-variant/40 text-xs font-medium">
                        </div>
                    </div>
                @endif

                <button type="submit" class="w-full py-4 px-6 rounded-2xl bg-primary hover:bg-primary-container text-white font-bold text-sm shadow-xl shadow-primary/20 flex items-center justify-center gap-2 transition-all hover:scale-[1.01]">
                    <span class="material-symbols-outlined text-xl">send</span>
                    Kirim Formulir Pengajuan Pencairan
                </button>
            </form>
        </div>

        {{-- Submission History --}}
        @if($submissions->count() > 0)
            <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-8 shadow-sm space-y-4">
                <h3 class="text-base font-bold text-on-surface">Riwayat Pengajuan Anda</h3>
                <div class="space-y-3">
                    @foreach($submissions as $sub)
                        <div class="p-4 rounded-2xl bg-surface-container flex items-center justify-between text-xs">
                            <div>
                                <span class="font-bold text-on-surface block">Pengajuan Tanggal {{ \App\Support\Wib::dari($sub->created_at)->translatedFormat('d M Y, H:i') }} WIB</span>
                                <span class="text-outline text-[11px]">ID: #SUB-{{ $sub->id }}</span>
                            </div>
                            <span class="px-2.5 py-1 rounded-full font-bold {{ $sub->status === 'Disetujui' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $sub->status }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-layouts.dashboard>
