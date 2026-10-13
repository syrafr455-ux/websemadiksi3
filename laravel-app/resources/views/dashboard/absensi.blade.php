<x-layouts.dashboard title="Presensi & Log Kehadiran" pageTitle="Presensi Kegiatan" pageSubtitle="Catatan absensi mandiri dan riwayat verifikasi kehadiran">
    <div class="space-y-8">
        {{-- Submission Form Card --}}
        <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-8 shadow-sm">
            <h3 class="text-base font-bold text-on-surface mb-1">Kirim Bukti Presensi Mandiri</h3>
            <p class="text-xs text-outline mb-6">Pilih kegiatan dan unggah bukti kehadiran untuk diverifikasi oleh pengurus.</p>

            <form action="{{ route('dashboard.absensi.store') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                @csrf

                <div>
                    <label for="activity_id" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                        Pilih Kegiatan <span class="text-rose-500">*</span>
                    </label>
                    <select name="activity_id" id="activity_id" required class="w-full px-4 py-2.5 bg-surface-container rounded-xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                        <option value="">-- Pilih Agenda --</option>
                        @foreach($activities as $act)
                            <option value="{{ $act->id }}">{{ $act->title }} ({{ $act->date_text }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="location_name" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                        Lokasi / Ruangan
                    </label>
                    <input type="text" name="location_name" id="location_name" value="Auditorium Kampus B UNUSA" class="w-full px-4 py-2.5 bg-surface-container rounded-xl border border-surface-variant/40 text-xs font-medium focus:border-primary">
                </div>

                <div class="md:col-span-2">
                    <label for="proof_image" class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-2">
                        Swafoto / Foto Bukti Kehadiran <span class="text-rose-500">*</span>
                    </label>
                    <input type="file" name="proof_image" id="proof_image" accept="image/*" required class="block w-full text-xs text-outline file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary file:text-white hover:file:bg-primary-container cursor-pointer">
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="px-6 py-3 rounded-2xl bg-primary hover:bg-primary-container text-white font-bold text-xs shadow-md shadow-primary/20 flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">how_to_reg</span>
                        Simpan Presensi
                    </button>
                </div>
            </form>
        </div>

        {{-- Attendance History Table --}}
        <div class="bg-surface rounded-3xl border border-surface-variant/40 p-6 sm:p-8 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-on-surface">Riwayat Kehadiran Anda</h3>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-surface-variant/40 text-outline uppercase font-bold">
                            <th class="py-3 px-4">Kegiatan</th>
                            <th class="py-3 px-4">Waktu Presensi</th>
                            <th class="py-3 px-4">Lokasi</th>
                            <th class="py-3 px-4">Bukti</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-variant/20">
                        @forelse($attendances as $att)
                            <tr class="hover:bg-surface-container/50">
                                <td class="py-3 px-4 font-bold text-on-surface">{{ $att->activity_title }}</td>
                                <td class="py-3 px-4 text-outline">{{ $att->submitted_at ? $att->submitted_at->format('d M Y, H:i') : '-' }}</td>
                                <td class="py-3 px-4 text-outline">{{ $att->location_name ?? '-' }}</td>
                                <td class="py-3 px-4">
                                    <a href="{{ $att->proof_image_url }}" target="_blank" class="font-bold text-primary hover:underline">
                                        Lihat Foto
                                    </a>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $att->status === 'Hadir' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $att->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-outline">Belum ada catatan presensi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
