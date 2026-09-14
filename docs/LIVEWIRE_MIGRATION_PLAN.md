# Rencana Migrasi Livewire (TALL) — Status & Urutan

Terakhir diverifikasi: **2026-09-14** (dari source, route, dan git tree aktif).

Dokumen ini adalah jawaban jujur atas "modul mana sudah / belum Livewire". Agar tidak
menyesatkan, setiap status memakai klasifikasi berikut:

```text
DONE COMMITTED   = sudah di-commit; klaim didukung test yang dijalankan.
WIP UNCOMMITTED  = terlihat di working tree tapi BELUM di-commit; bisa berubah
                   sewaktu-waktu, jangan dijadikan dasar keputusan.
NOT STARTED      = belum ada komponen Livewire untuk area tersebut.
OUT OF SCOPE     = sengaja tidak dimigrasi (ada alasan eksplisit).
```

Aturan baca: status test = source/CI PASS, **bukan** browser PASS.
Visual/browser tetap RVR mengikuti `docs/GUI_RUNTIME_QA.md`.

## 1. Pola canonical migrasi (wajib diikuti modul berikutnya)

1. Query tetap milik use case/service canonical; komponen hanya orkestrasi tipis
   (contoh: `SpjWorkspaceUseCase::preparationData()`, `TaxFilterService::taxData()`).
2. State filter memakai `#[Url]` agar bookmark/share URL tidak berubah perilaku.
3. Pagination memakai `WithPagination` + view `vendor/pagination/tailwind.blade.php`
   (bertoken-tema, label Indonesia); tabel Livewire selalu `data-pagination="server"`.
4. Tidak ada pager ganda: inisialisasi generik melewati subtree `[wire:id]`.
5. Setiap modul membawa regression test Livewire (`set`/`call` membuktikan update
   tanpa reload) + tidak merusak kontrak lifecycle/validation/numbering.

## 2. Status per modul

| Modul / halaman | Cakupan Livewire | Status | Bukti / catatan |
|---|---|---|---|
| Transaksi (`/transaksi`) | `TransactionsTable` (cari, filter, perPage) | DONE COMMITTED | Test `TransactionsTableLivewireTest` hijau. |
| RKAS budget | `RkasBudgetFilter`, `RkasBudgetTable` | DONE COMMITTED | Sudah dipakai di view rkas-budget. |
| SPJ tab Persiapan/Paket/Laporan/Monitoring (`/spj`) | `SpjPreparationFilter`, `SpjPackageList`, `SpjReportFilter`, `SpjMonitoringList` + navigasi tab SPA | DONE COMMITTED | Commit `b9eb0b6` + `e34709b`; test `SpjTabFiltersLivewireTest`, `SpjReportLayoutTest`, `SpjMainTabsRenderingTest` hijau. Detail paket (workspace mutasi) SENGAJA tetap server-rendered — lihat OUT OF SCOPE. |
| Pajak (`/pajak`) | `TaxFilter` (mode/periode toggle, cari, Baris, ringkasan header reaktif) | DONE COMMITTED | Commit `b9eb0b6`; test `TaxFilterLivewireTest` hijau. |
| Pengaturan: user, sekolah, tahun, dokumen, pegawai (`UserManagement`, `SchoolMaster`, `SchoolSelector`, `YearSelector`, `DocumentStorageSettings`, `EmployeeDirectory`) | State/pencarian/filter form | DONE COMMITTED (`0789ed8`) + WIP lain | Ter-commit 2026-09-14. **Catatan**: untuk `DocumentStorageSettings` dan `RkasTable` tidak ditemukan referensi di halaman mana pun pada tree 2026-09-14 — perlu konfirmasi pemilik sebelum dianggap selesai. |
| Database manager + Data sinkronisasi (`DatabaseStatusSummary`, `DatabaseManagerTabs`, `DatabaseTableExplorer`, `SyncedDataNavigation`) | Status/tab/explorer/navigasi | WIP UNCOMMITTED | Terlihat di working tree 2026-09-14, belum di-commit. Jangan jadikan dasar sampai di-commit + hijau. |
| Pagination global (`views/vendor/pagination/tailwind.blade.php`, `lang/id.*`) | Semua pemakai `links()` | DONE COMMITTED, SEDANG DIREVISI | Versi bertoken-tema ter-commit (`b9eb0b6`); pada 2026-09-14 terlihat revisi desain segmented di working tree (uncommitted). Final = versi yang di-commit terakhir + test hijau. |
| Rekonsiliasi (`/rekonsiliasi`) | — | NOT STARTED | Kandidat #1: search + filter perhatian + Baris, baris read-only. |
| Template dokumen (`/pengaturan/template-dokumen`) | — | NOT STARTED | Kandidat #2: filter status/kategori; upload/tambah tetap POST biasa. |
| Laporan audit (`/laporan-audit`) | — | NOT STARTED | Nilai kecil: tab sudah client-side, tersisa pagination saja. |
| ARKAS importer (`/pengaturan/arkas/importer`) | — | NOT STARTED, DITUNDA | Workflow stateful (mapping → preview → sinkronisasi); risiko merusak sinkronisasi lebih besar dari manfaat. Jangan tanpa desain khusus. |
| Siswa (`/siswa`) | — | OUT OF SCOPE | File protected; perlu instruksi eksplisit user. |
| Dashboard | — | OUT OF SCOPE | Isi tautan + grafik, bukan filter; tidak cocok. |
| Workspace detail paket SPJ (`?tab=paket&package_id=`) | — | OUT OF SCOPE | Mutation-heavy (Isian Manual, penomoran, upload, download POST, Alpine kompleks); tetap server-rendered penuh. |
| Dapodik | — | OUT OF SCOPE | Tidak ditemukan filter/pagination interaktif pada tree 2026-09-14. |

## 3. Kode mati hasil migrasi (jangan hapus tanpa konfirmasi user)

- `resources/views/spj/partials/preparation.blade.php` — digantikan `SpjPreparationFilter`, sudah tidak di-include.
- `resources/views/components/page-filter.blade.php` — digantikan filter Pajak, sudah tidak dipakai halaman mana pun.
- Markup lama employees di dalam `<div class="hidden">` — wilayah rekan, jangan sentuh.

## 4. Urutan rekomendasi berikutnya

1. **Rekonsiliasi** — nilai tertinggi, risiko rendah, pola identik SPJ/Pajak.
2. **Template dokumen** — admin-only, frekuensi rendah, upload tetap POST.
3. **Laporan audit pagination** — opsional, murah.
4. **Kliring**: hapus kode mati §3 setelah konfirmasi + finalisasi revisi pagination + verifikasi komponen tanpa referensi (§2).

## 5. Aturan main paralel (anti-tabrakan)

- Klaim area sebelum mulai; satu area = satu pemilik.
- Jangan sentuh file uncommitted milik pihak lain (cek `git status` + `git diff` dulu).
- Commit terpisah per area; untuk file docs campuran, stage hanya hunk sendiri.
- Definisi selesai per modul: test Livewire hijau + `view:cache` + `npm run build` + update status di dokumen ini + browser RVR tetap terbuka.
