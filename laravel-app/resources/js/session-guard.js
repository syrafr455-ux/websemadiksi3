/**
 * SEMADIKSI Session Guard & Inactivity Tracker
 *
 * 1. Mencegah tampilan halaman terautentikasi lewat tombol Back setelah logout (pageshow bfcache reload).
 * 2. Melacak aktivitas pengguna (mousemove, keydown, click, scroll, touchstart, input) secara throttled.
 * 3. Mengirimkan heartbeat ping ringan (POST /sesi/ping) jika pengguna aktif untuk memperpanjang sesi server.
 * 4. Menampilkan peringatan "Sesi akan berakhir dalam 1 menit" pada menit ke-14 tanpa aktivitas (tanpa data pribadi).
 * 5. Melakukan logout otomatis pada menit ke-15 tanpa aktivitas dan mengalihkan ke halaman login dengan pesan Indonesia.
 * 6. Menghentikan hitung mundur selama pengunggahan berkas aktif.
 */

const IDLE_TIMEOUT_MS = 15 * 60 * 1000; // 15 menit
const WARNING_TIME_MS = 14 * 60 * 1000; // 14 menit
const PING_INTERVAL_MS = 2.5 * 60 * 1000; // 2.5 menit
const CHECK_INTERVAL_MS = 5 * 1000; // Cek setiap 5 detik
const ACTIVITY_THROTTLE_MS = 1000; // Throttle pencatatan aktivitas 1 detik

export function initSessionGuard() {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    // Deteksi status autentikasi halaman
    const getAuthMeta = () => document.querySelector('meta[name="user-authenticated"]');
    const isAuthPage = () => {
        const meta = getAuthMeta();
        if (meta) return true;
        const p = window.location.pathname;
        return (
            p.startsWith('/dashboard') ||
            (p.startsWith('/admin') && p !== '/admin/masuk') ||
            p.startsWith('/two-factor')
        );
    };

    const isStaffRole = () => {
        const meta = getAuthMeta();
        if (meta && meta.getAttribute('data-role') === 'staff') return true;
        return window.location.pathname.startsWith('/admin');
    };

    // 1. Pageshow handler: jika halaman diambil dari cache riwayat browser (bfcache) pada halaman login-required, lakukan reload
    window.addEventListener('pageshow', (event) => {
        if (event.persisted && isAuthPage()) {
            window.location.reload();
        }
    });

    // Jika bukan halaman terautentikasi, tidak perlu menjalankan pelacak inaktivitas
    if (!isAuthPage()) {
        return;
    }

    let lastActivity = Date.now();
    let lastPing = Date.now();
    let lastThrottledRecord = 0;
    let isWarningVisible = false;
    let isLoggingOut = false;
    let activeUploadsCount = 0;
    let warningModal = null;

    const getCsrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Modal Peringatan Menit ke-14
    function createWarningModal() {
        if (warningModal) return warningModal;

        const container = document.createElement('div');
        container.id = 'session-timeout-warning-modal';
        container.className =
            'fixed inset-0 z-[9999] bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity duration-200';
        container.style.display = 'none';
        container.setAttribute('role', 'dialog');
        container.setAttribute('aria-modal', 'true');
        container.setAttribute('aria-labelledby', 'session-modal-title');

        container.innerHTML = `
            <div class="bg-slate-900 border border-amber-500/40 rounded-2xl p-6 sm:p-8 max-w-md w-full shadow-2xl text-slate-100 flex flex-col items-center text-center">
                <div class="w-14 h-14 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-400 flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-3xl">timer</span>
                </div>
                <h3 id="session-modal-title" class="text-lg font-bold text-white mb-2">
                    Sesi akan berakhir dalam 1 menit
                </h3>
                <p class="text-xs sm:text-sm text-slate-300 mb-6 leading-relaxed">
                    Anda tidak melakukan aktivitas selama 14 menit. Demi keamanan akun, sesi akan ditutup secara otomatis dalam waktu 1 menit jika tidak ada aktivitas.
                </p>
                <button
                    id="btn-session-stay-logged-in"
                    type="button"
                    class="w-full py-3 px-4 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-sm transition-all shadow-lg shadow-amber-600/20 active:scale-[0.99] flex items-center justify-center gap-2 cursor-pointer"
                >
                    <span class="material-symbols-outlined text-lg">check_circle</span>
                    Tetap masuk
                </button>
            </div>
        `;

        document.body.appendChild(container);

        container.querySelector('#btn-session-stay-logged-in')?.addEventListener('click', () => {
            recordActivity();
            hideWarningModal();
            pingServer();
        });

        warningModal = container;
        return warningModal;
    }

    function showWarningModal() {
        if (isWarningVisible) return;
        const modal = createWarningModal();
        modal.style.display = 'flex';
        isWarningVisible = true;
    }

    function hideWarningModal() {
        if (!isWarningVisible) return;
        if (warningModal) {
            warningModal.style.display = 'none';
        }
        isWarningVisible = false;
    }

    // Catat aktivitas pengguna (throttled agar performa tetap ringan)
    function recordActivity() {
        const now = Date.now();
        if (now - lastThrottledRecord > ACTIVITY_THROTTLE_MS) {
            lastThrottledRecord = now;
            lastActivity = now;
            if (isWarningVisible) {
                hideWarningModal();
                pingServer();
            }
        }
    }

    // Heartbeat ping ke server (POST /sesi/ping)
    async function pingServer() {
        const token = getCsrfToken();
        if (!token) return;

        try {
            const res = await fetch('/sesi/ping', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            lastPing = Date.now();

            if (res.status === 401 || res.status === 419) {
                handleAutoLogout();
            }
        } catch {
            // Abaikan kegagalan jaringan sementara
        }
    }

    // Logout otomatis pada menit ke-15
    function handleAutoLogout() {
        if (isLoggingOut) return;
        isLoggingOut = true;

        const target = isStaffRole() ? '/admin/masuk' : '/masuk';
        // Arahkan ke endpoint login yang menampilkan pesan sesi berakhir
        window.location.href = target;
    }

    // Pasang listener aktivitas interaktif (pengetikan, klik, gerak, sentuhan, gulir)
    const events = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'input'];
    events.forEach((ev) => {
        window.addEventListener(ev, recordActivity, { passive: true });
    });

    // Deteksi pengunggahan berkas aktif untuk menghentikan hitung mundur
    window.addEventListener('submit', (e) => {
        const form = e.target;
        if (form && form.querySelector && form.querySelector('input[type="file"]')) {
            const fileInputs = form.querySelectorAll('input[type="file"]');
            let hasSelectedFiles = false;
            fileInputs.forEach((inp) => {
                if (inp.files && inp.files.length > 0) hasSelectedFiles = true;
            });

            if (hasSelectedFiles) {
                activeUploadsCount++;
                lastActivity = Date.now();
                setTimeout(() => {
                    activeUploadsCount = Math.max(0, activeUploadsCount - 1);
                }, 30000);
            }
        }
    });

    // Pemeriksaan berkala (setiap 5 detik)
    setInterval(() => {
        if (isLoggingOut) return;

        if (activeUploadsCount > 0) {
            lastActivity = Date.now();
            return;
        }

        const now = Date.now();
        const idleDuration = now - lastActivity;

        // 1. Menit ke-15: logout otomatis
        if (idleDuration >= IDLE_TIMEOUT_MS) {
            handleAutoLogout();
            return;
        }

        // 2. Menit ke-14: tampilkan dialog peringatan 1 menit
        if (idleDuration >= WARNING_TIME_MS) {
            showWarningModal();
            return;
        }

        // 3. Jika pengguna aktif dan interval ping tercapai, perbarui sesi server
        if (idleDuration < PING_INTERVAL_MS && (now - lastPing) >= PING_INTERVAL_MS) {
            pingServer();
        }
    }, CHECK_INTERVAL_MS);
}

// Inisialisasi otomatis saat script dimuat
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSessionGuard);
    } else {
        initSessionGuard();
    }
}
