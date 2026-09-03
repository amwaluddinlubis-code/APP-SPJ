<script>
    (() => {
        if (window.__spjUiLanguageReady) return;
        window.__spjUiLanguageReady = true;

        const exact = new Map([
            ['Dashboard operasional', 'Ringkasan pekerjaan'],
            ['Dashboard Operasional', 'Ringkasan pekerjaan'],
            ['Dashboard v.2', 'Ringkasan lengkap'],
            ['Data Hasil Sinkron', 'Data Hasil Sinkronisasi'],
            ['Sinkron Semua ARKAS', 'Ambil Data ARKAS Terbaru'],
            ['Integrasi ARKAS', 'Pengaturan ARKAS'],
            ['Integrasi Dapodik', 'Pengaturan Dapodik'],
            ['Manajemen User', 'Kelola Pengguna'],
            ['Uji Sebagai User', 'Lihat Sebagai Pengguna'],
            ['Backup & Pemulihan', 'Cadangan & Pemulihan'],
            ['Database Aktif', 'Penyimpanan Data Aktif'],
            ['Pajak Sinkronisasi', 'Data Pajak'],
            ['WORKFLOW SPJ & PENOMORAN', 'ALUR KERJA SPJ & PENOMORAN'],
            ['Preview antrean penomoran', 'Periksa paket yang akan diberi nomor'],
            ['Preview sebelum penomoran', 'Periksa sebelum membuat nomor'],
            ['Terapkan Filter', 'Terapkan'],
            ['Reset Filter', 'Hapus Saringan'],
            ['Reset Periode', 'Hapus Saringan'],
            ['Filter antrean rekonsiliasi', 'Saring transaksi yang perlu diperiksa'],
            ['Simpan Profil Aktif', 'Simpan Perubahan'],
            ['Buat Sekolah dan Database', 'Tambah Sekolah'],
            ['Tambah Tahun Anggaran', 'Tambahkan Tahun Anggaran'],
            ['Buka Paket SPJ', 'Lihat Paket SPJ'],
            ['Ruang Kerja SPJ', 'Pekerjaan SPJ'],
            ['Data SPJ Operator', 'Data SPJ yang Diisi'],
            ['Data ARKAS / BKU', 'Data dari ARKAS / BKU'],
            ['Hanya dibaca', 'Tidak dapat diubah'],
            ['Read only', 'Tidak dapat diubah'],
            ['Read-only', 'Tidak dapat diubah'],
        ]);

        const phrases = [
            [/\bworkflow\b/gi, 'alur kerja'],
            [/\bpreview\b/gi, 'pratinjau'],
            [/\bfilter\b/gi, 'saringan'],
            [/\breset\b/gi, 'atur ulang'],
            [/\bmapping\b/gi, 'pemetaan'],
            [/\bbatch\b/gi, 'proses'],
            [/\buser\b/gi, 'pengguna'],
            [/\bsync\b/gi, 'sinkronisasi'],
            [/\bdownload\b/gi, 'unduh'],
            [/\bupload\b/gi, 'unggah'],
            [/\bread[ -]?only\b/gi, 'tidak dapat diubah'],
            [/\bplaceholder\b/gi, 'penanda data'],
            [/\bdraft\b/gi, 'belum lengkap'],
        ];

        const simplify = (value) => {
            if (!value || typeof value !== 'string') return value;
            const trimmed = value.trim();
            if (exact.has(trimmed)) {
                const replacement = exact.get(trimmed);
                return value.replace(trimmed, replacement);
            }

            let result = value;
            phrases.forEach(([pattern, replacement]) => {
                result = result.replace(pattern, replacement);
            });
            return result;
        };

        const excluded = 'script, style, code, pre, textarea, [contenteditable="true"], [data-preserve-ui-copy]';

        const simplifyNode = (root) => {
            if (!root) return;
            const scope = root.nodeType === Node.ELEMENT_NODE ? root : root.parentElement;
            if (scope?.matches?.(excluded) || scope?.closest?.(excluded)) return;

            const walker = document.createTreeWalker(
                root.nodeType === Node.TEXT_NODE ? root.parentElement : root,
                NodeFilter.SHOW_TEXT,
                {
                    acceptNode(node) {
                        const parent = node.parentElement;
                        if (!parent || parent.matches(excluded) || parent.closest(excluded)) return NodeFilter.FILTER_REJECT;
                        return NodeFilter.FILTER_ACCEPT;
                    },
                },
            );

            const nodes = [];
            if (root.nodeType === Node.TEXT_NODE) nodes.push(root);
            while (walker.nextNode()) nodes.push(walker.currentNode);

            nodes.forEach((node) => {
                const changed = simplify(node.nodeValue);
                if (changed !== node.nodeValue) node.nodeValue = changed;
            });

            const elementRoot = root.nodeType === Node.ELEMENT_NODE ? root : root.parentElement;
            if (!elementRoot) return;
            const elements = [elementRoot, ...elementRoot.querySelectorAll('[title], [aria-label], [placeholder]')];
            elements.forEach((element) => {
                if (element.matches(excluded) || element.closest(excluded)) return;
                ['title', 'aria-label', 'placeholder'].forEach((attribute) => {
                    if (!element.hasAttribute(attribute)) return;
                    const original = element.getAttribute(attribute);
                    const changed = simplify(original);
                    if (changed !== original) element.setAttribute(attribute, changed);
                });
            });
        };

        const run = () => simplifyNode(document.body);
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run, { once: true });
        } else {
            run();
        }

        let scheduled = false;
        const observer = new MutationObserver((mutations) => {
            if (scheduled) return;
            if (!mutations.some((mutation) => mutation.addedNodes.length || mutation.type === 'characterData')) return;
            scheduled = true;
            requestAnimationFrame(() => {
                scheduled = false;
                run();
            });
        });

        const startObserver = () => observer.observe(document.body, { childList: true, subtree: true, characterData: true });
        if (document.body) startObserver();
        else document.addEventListener('DOMContentLoaded', startObserver, { once: true });
    })();
</script>
