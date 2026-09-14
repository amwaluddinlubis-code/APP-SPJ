// Bilah progres atas: tampil saat beban halaman awal, perpindahan halaman,
// dan pembaruan Livewire. Tanpa dependensi tambahan.
(() => {
    const BAR_ID = 'app-top-progress';
    const TRICKLE_MAX = 90;
    const FINISH_DELAY = 300;

    let bar = null;
    let fill = null;
    let trickleTimer = null;
    let finishTimer = null;

    const progressElements = () => {
        if (!bar) {
            bar = document.getElementById(BAR_ID);
            fill = bar?.querySelector(':scope > span') ?? null;
        }

        return bar && fill ? { bar, fill } : null;
    };

    const stopTrickle = () => {
        if (trickleTimer) {
            window.clearInterval(trickleTimer);
            trickleTimer = null;
        }
    };

    const currentWidth = (fill) => Number.parseFloat(fill.style.width) || 0;

    const start = () => {
        const elements = progressElements();
        if (!elements) return;

        if (finishTimer) {
            window.clearTimeout(finishTimer);
            finishTimer = null;
        }

        elements.bar.classList.add('is-active');
        if (currentWidth(elements.fill) >= 100) {
            elements.fill.style.width = '0%';
        }

        // Merayap menuju 90% agar jeda pendek tetap terlihat hidup.
        if (!trickleTimer) {
            trickleTimer = window.setInterval(() => {
                const next = Math.min(TRICKLE_MAX, currentWidth(elements.fill) + 4 + Math.random() * 10);
                elements.fill.style.width = `${next}%`;
            }, 250);
        }
    };

    const done = () => {
        const elements = progressElements();
        if (!elements) return;

        stopTrickle();
        elements.fill.style.width = '100%';

        if (finishTimer) {
            window.clearTimeout(finishTimer);
        }
        finishTimer = window.setTimeout(() => {
            elements.bar.classList.remove('is-active');
            elements.fill.style.width = '0%';
            finishTimer = null;
        }, FINISH_DELAY);
    };

    // Beban halaman awal: modul deferred berjalan sebelum event load.
    if (document.readyState !== 'complete') {
        start();
        window.addEventListener('load', done, { once: true });
    }

    // Perpindahan halaman penuh (klik tautan, location.assign, submit form).
    window.addEventListener('beforeunload', start);
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) done();
    });

    // Pembaruan Livewire tanpa reload.
    document.addEventListener('livewire:init', () => {
        if (!window.Livewire?.hook) return;

        window.Livewire.hook('request', ({ fail }) => {
            start();
            fail(() => done());
        });
        window.Livewire.hook('morph.updated', () => done());
    });
    document.addEventListener('livewire:navigated', () => done());
})();
