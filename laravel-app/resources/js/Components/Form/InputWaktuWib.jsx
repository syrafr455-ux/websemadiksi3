import React, { useRef } from 'react';

/**
 * Normalisasi nilai string tanggal-waktu WIB ke format "YYYY-MM-DDTHH:mm" (tanpa detik).
 */
export function normalisasiWaktuWib(val) {
    if (!val) return '';
    const match = String(val).match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/);
    return match ? match[1] : '';
}

/**
 * Komponen Input Waktu WIB bersama untuk form admin SEMADIKSI.
 *
 * Fitur:
 * 1. step="60" sehingga detik tidak tampil (--:-- bukan --:--:--).
 * 2. Nilai dinormalisasi ke YYYY-MM-DDTHH:mm tanpa detik.
 * 3. Klik atau fokus pada badan kolom memanggil showPicker() di dalam try/catch.
 * 4. Ikon kalender kontras dan terlihat jelas di tema gelap (color-scheme: dark).
 * 5. Tombol "Kosongkan" untuk kolom opsional yang terisi.
 * 6. Teks bantu "Klik kolom atau ikon kalender untuk memilih".
 * 7. Aksesibilitas: aria-label, aria-invalid, aria-describedby tersambung ke teks bantu & galat.
 * 8. Min dan max didukung dari props dan dinormalisasi.
 * 9. Format 12 atau 24 jam mengikuti pengaturan browser & OS pengguna (standar HTML5).
 */
export default function InputWaktuWib({
    id,
    name,
    label,
    value,
    onChange,
    onKosongkan,
    error,
    disabled = false,
    required = false,
    opsional = false,
    min,
    max,
    bantuan,
    className = '',
    inputClassName = '',
    'aria-label': ariaLabelProp,
    ...props
}) {
    const inputRef = useRef(null);
    const inputId = id || name || (label ? `waktu-${label.toLowerCase().replace(/[^a-z0-9]/g, '-')}` : undefined);
    const helpId = inputId ? `${inputId}-help` : undefined;
    const errorId = inputId ? `${inputId}-error` : undefined;

    const nilaiNormal = normalisasiWaktuWib(value);
    const minNormal = normalisasiWaktuWib(min);
    const maxNormal = normalisasiWaktuWib(max);

    const bisaDikosongkan = (opsional || !required) && Boolean(nilaiNormal) && !disabled;

    const handleFocusAtauKlik = (e) => {
        if (disabled) return;
        try {
            if (typeof e.target.showPicker === 'function') {
                e.target.showPicker();
            }
        } catch (_) {
            // Browser mungkin melempar NotAllowedError atau tidak mendukung showPicker; abaikan dengan aman
        }
    };

    const handleKosongkan = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (disabled) return;
        if (typeof onKosongkan === 'function') {
            onKosongkan();
        } else if (typeof onChange === 'function') {
            onChange({
                target: {
                    name,
                    id: inputId,
                    value: '',
                },
            });
        }
    };

    const labelTeks = label || ariaLabelProp || 'Waktu (WIB)';

    return (
        <div className={`space-y-1 ${className}`}>
            {label && (
                <div className="flex items-center justify-between gap-2">
                    <label htmlFor={inputId} className="block text-xs font-bold text-slate-300">
                        {label} {required && <span className="text-rose-400">*</span>}
                    </label>
                    {bisaDikosongkan && (
                        <button
                            type="button"
                            onClick={handleKosongkan}
                            className="text-[10px] font-medium text-slate-400 hover:text-amber-400 underline transition-colors cursor-pointer"
                            aria-label={`Kosongkan ${labelTeks}`}
                        >
                            Kosongkan
                        </button>
                    )}
                </div>
            )}

            <div className="relative">
                <input
                    ref={inputRef}
                    type="datetime-local"
                    step="60"
                    id={inputId}
                    name={name}
                    value={nilaiNormal}
                    min={minNormal || undefined}
                    max={maxNormal || undefined}
                    disabled={disabled}
                    required={required}
                    onChange={onChange}
                    onClick={handleFocusAtauKlik}
                    onFocus={handleFocusAtauKlik}
                    aria-label={ariaLabelProp || labelTeks}
                    aria-invalid={Boolean(error)}
                    aria-describedby={[helpId, error ? errorId : null].filter(Boolean).join(' ') || undefined}
                    style={{ colorScheme: 'dark' }}
                    className={`w-full px-3 py-2 rounded-xl bg-slate-950 border text-white text-xs cursor-pointer min-h-[38px] transition-colors
                        focus:outline-none focus:ring-1 focus:ring-emerald-500
                        disabled:opacity-50 disabled:cursor-not-allowed
                        [&::-webkit-calendar-picker-indicator]:cursor-pointer
                        [&::-webkit-calendar-picker-indicator]:opacity-90
                        [&::-webkit-calendar-picker-indicator]:hover:opacity-100
                        [&::-webkit-calendar-picker-indicator]:p-1
                        [&::-webkit-calendar-picker-indicator]:rounded-md
                        [&::-webkit-calendar-picker-indicator]:hover:bg-slate-800
                        [&::-webkit-calendar-picker-indicator]:transition-all
                        [&::-webkit-calendar-picker-indicator]:scale-110
                        ${error ? 'border-rose-500/80 focus:border-rose-500' : 'border-slate-800 focus:border-emerald-500'}
                        ${inputClassName}`}
                    {...props}
                />
            </div>

            <div className="space-y-0.5">
                <p id={helpId} className="text-[10px] text-slate-400">
                    Klik kolom atau ikon kalender untuk memilih{bantuan ? ` • ${bantuan}` : ''}
                </p>
                {error && (
                    <p id={errorId} className="text-[10px] text-rose-400" role="alert">
                        {error}
                    </p>
                )}
            </div>
        </div>
    );
}
