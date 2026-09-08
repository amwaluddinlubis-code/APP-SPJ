const SYNC_PROFILES = {
    arkas: {
        title: 'Sinkronisasi ARKAS',
        subtitle: 'RKAS, BKU, transaksi, dan referensi sedang diperbarui.',
        match: (url) => url.pathname.endsWith('/sinkronisasi/arkas'),
        stages: [
            { percent: 8, label: 'Menyiapkan koneksi ARKAS' },
            { percent: 25, label: 'Membaca profil dan referensi dasar' },
            { percent: 50, label: 'Membaca RKAS dan BKU' },
            { percent: 78, label: 'Menyimpan transaksi dan rekonsiliasi' },
            { percent: 92, label: 'Menyusun referensi turunan' },
        ],
    },
    dapodik: {
        title: 'Sinkronisasi Dapodik',
        subtitle: 'GTK dan Peserta Didik sedang diambil dan dipadankan.',
        match: (url) => url.pathname.endsWith('/pengaturan/dapodik/sinkron'),
        stages: [
            { percent: 10, label: 'Menghubungkan Web Service Dapodik' },
            { percent: 30, label: 'Mengambil data GTK' },
            { percent: 50, label: 'Mengambil data Peserta Didik' },
            { percent: 72, label: 'Memadankan dan menyimpan GTK' },
            { percent: 92, label: 'Memadankan dan menyimpan siswa' },
        ],
    },
};

const profileForForm = (form) => {
    try {
        const url = new URL(form.action, window.location.href);
        return Object.values(SYNC_PROFILES).find((profile) => profile.match(url)) || null;
    } catch {
        return null;
    }
};

const createOverlay = () => {
    const overlay = document.createElement('div');
    overlay.id = 'sync-progress-overlay';
    overlay.className = 'fixed inset-0 z-[120] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', 'sync-progress-title');
    overlay.innerHTML = `
        <section class="w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
            <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-300">Proses sinkronisasi</p>
                        <h2 id="sync-progress-title" class="mt-1 text-xl font-extrabold text-slate-900 dark:text-white"></h2>
                        <p data-sync-subtitle class="mt-1 text-sm text-slate-500 dark:text-slate-400"></p>
                    </div>
                    <span data-sync-percent class="rounded-full bg-indigo-50 px-3 py-1 text-sm font-extrabold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200">0%</span>
                </div>
            </header>

            <div class="space-y-5 px-5 py-5">
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3 text-xs font-semibold text-slate-500 dark:text-slate-400">
                        <span data-sync-current-stage>Menyiapkan proses...</span>
                        <span data-sync-elapsed>00:00</span>
                    </div>
                    <div class="h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" aria-hidden="true">
                        <div data-sync-bar class="h-full rounded-full bg-indigo-600 transition-[width] duration-500 ease-out" style="width: 0%"></div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Persentase menunjukkan tahapan proses. Penyelesaian 100% hanya ditampilkan setelah server benar-benar selesai.</p>
                </div>

                <ol data-sync-stages class="grid gap-2 sm:grid-cols-2"></ol>

                <div data-sync-waiting class="hidden rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200">
                    Tahap utama sudah dikirim. Server masih menyelesaikan penyimpanan dan validasi akhir. Jangan tutup halaman ini.
                </div>

                <div data-sync-error class="hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200"></div>
            </div>

            <footer class="flex items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-950/40 dark:text-slate-400">
                <span data-sync-footer>Mohon tunggu. Data manual SPJ tidak ditimpa otomatis oleh sinkronisasi.</span>
                <button data-sync-close type="button" class="hidden rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">Tutup</button>
            </footer>
        </section>
    `;
    document.body.appendChild(overlay);

    return overlay;
};

const overlay = () => document.getElementById('sync-progress-overlay') || createOverlay();

const formatElapsed = (milliseconds) => {
    const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));
    const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
    const seconds = String(totalSeconds % 60).padStart(2, '0');
    return `${minutes}:${seconds}`;
};

const renderStages = (root, profile, activeIndex) => {
    const list = root.querySelector('[data-sync-stages]');
    if (!list) return;

    list.innerHTML = profile.stages.map((stage, index) => {
        const completed = index < activeIndex;
        const active = index === activeIndex;
        const marker = completed ? '✓' : (active ? '●' : String(index + 1));
        const classes = completed
            ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200'
            : active
                ? 'border-indigo-300 bg-indigo-50 text-indigo-900 dark:border-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-200'
                : 'border-slate-200 bg-white text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400';

        return `<li class="flex items-center gap-3 rounded-xl border px-3 py-2.5 ${classes}">
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full border border-current text-xs font-extrabold">${marker}</span>
            <span class="min-w-0"><strong class="block text-sm">${stage.label}</strong><span class="text-xs opacity-70">Target tahap ${stage.percent}%</span></span>
        </li>`;
    }).join('');
};

const runProgress = async (form, profile) => {
    const root = overlay();
    const title = root.querySelector('#sync-progress-title');
    const subtitle = root.querySelector('[data-sync-subtitle]');
    const percentLabel = root.querySelector('[data-sync-percent]');
    const bar = root.querySelector('[data-sync-bar]');
    const currentStage = root.querySelector('[data-sync-current-stage]');
    const elapsed = root.querySelector('[data-sync-elapsed]');
    const waiting = root.querySelector('[data-sync-waiting]');
    const errorBox = root.querySelector('[data-sync-error]');
    const footer = root.querySelector('[data-sync-footer]');
    const close = root.querySelector('[data-sync-close]');

    root.classList.remove('hidden');
    root.classList.add('flex');
    waiting?.classList.add('hidden');
    errorBox?.classList.add('hidden');
    close?.classList.add('hidden');
    if (title) title.textContent = profile.title;
    if (subtitle) subtitle.textContent = profile.subtitle;
    if (footer) footer.textContent = 'Mohon tunggu. Data manual SPJ tidak ditimpa otomatis oleh sinkronisasi.';

    const startedAt = Date.now();
    let activeIndex = 0;
    let currentPercent = 2;
    let finished = false;

    const paint = () => {
        const stage = profile.stages[Math.min(activeIndex, profile.stages.length - 1)];
        if (percentLabel) percentLabel.textContent = `${Math.round(currentPercent)}%`;
        if (bar instanceof HTMLElement) bar.style.width = `${Math.min(100, currentPercent)}%`;
        if (currentStage) currentStage.textContent = stage?.label || 'Menunggu server menyelesaikan proses';
        if (elapsed) elapsed.textContent = formatElapsed(Date.now() - startedAt);
        renderStages(root, profile, activeIndex);
    };

    paint();

    const stageTimer = window.setInterval(() => {
        if (finished) return;
        const target = profile.stages[activeIndex]?.percent ?? 92;
        if (currentPercent < target) {
            currentPercent = Math.min(target, currentPercent + Math.max(1, (target - currentPercent) * 0.18));
        } else if (activeIndex < profile.stages.length - 1) {
            activeIndex += 1;
        } else {
            waiting?.classList.remove('hidden');
        }
        paint();
    }, 650);

    const elapsedTimer = window.setInterval(() => {
        if (elapsed) elapsed.textContent = formatElapsed(Date.now() - startedAt);
    }, 1000);

    try {
        const response = await fetch(form.action, {
            method: (form.method || 'POST').toUpperCase(),
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'text/html,application/xhtml+xml',
            },
            redirect: 'follow',
        });

        finished = true;
        currentPercent = 100;
        activeIndex = profile.stages.length;
        if (percentLabel) percentLabel.textContent = '100%';
        if (bar instanceof HTMLElement) bar.style.width = '100%';
        if (currentStage) currentStage.textContent = response.ok ? 'Sinkronisasi selesai' : 'Sinkronisasi selesai dengan respons error';
        if (waiting) waiting.classList.add('hidden');
        renderStages(root, profile, profile.stages.length);

        if (!response.ok) {
            throw new Error(`Server merespons HTTP ${response.status}.`);
        }

        if (footer) footer.textContent = 'Selesai. Membuka hasil sinkronisasi...';
        await new Promise((resolve) => window.setTimeout(resolve, 500));
        window.location.assign(response.url || window.location.href);
    } catch (error) {
        finished = true;
        if (percentLabel) percentLabel.textContent = 'GAGAL';
        if (currentStage) currentStage.textContent = 'Sinkronisasi gagal';
        if (errorBox) {
            errorBox.textContent = `Proses tidak dapat diselesaikan: ${error instanceof Error ? error.message : 'kesalahan tidak diketahui'}`;
            errorBox.classList.remove('hidden');
        }
        if (footer) footer.textContent = 'Tidak ada halaman yang ditutup otomatis. Periksa koneksi lalu coba kembali.';
        close?.classList.remove('hidden');
    } finally {
        window.clearInterval(stageTimer);
        window.clearInterval(elapsedTimer);
    }
};

const initializeSyncProgress = () => {
    if (document.documentElement.dataset.syncProgressInitialized === 'true') return;
    document.documentElement.dataset.syncProgressInitialized = 'true';

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        const profile = profileForForm(form);
        if (!profile) return;

        event.preventDefault();
        event.stopImmediatePropagation();

        const confirmation = form.dataset.confirm;
        if (confirmation && !window.confirm(confirmation)) return;
        if (form.dataset.syncProgressRunning === 'true') return;

        form.dataset.syncProgressRunning = 'true';
        form.querySelectorAll('button, input[type="submit"]').forEach((element) => {
            if (element instanceof HTMLButtonElement || element instanceof HTMLInputElement) element.disabled = true;
        });

        runProgress(form, profile).catch(() => {}).finally(() => {
            form.dataset.syncProgressRunning = 'false';
            form.querySelectorAll('button, input[type="submit"]').forEach((element) => {
                if (element instanceof HTMLButtonElement || element instanceof HTMLInputElement) element.disabled = false;
            });
        });
    }, true);

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const button = target.closest('[data-sync-close]');
        if (!button) return;
        const root = document.getElementById('sync-progress-overlay');
        root?.classList.add('hidden');
        root?.classList.remove('flex');
    });
};

initializeSyncProgress();
document.addEventListener('livewire:navigated', initializeSyncProgress);
