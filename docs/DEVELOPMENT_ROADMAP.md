# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: **2026-09-06**

Roadmap ini menggambarkan prioritas setelah fondasi transaksi, safe sync ARKAS, package SPJ, numbering, template, tenant database, dan design system sudah terbentuk.

---

## 1. Sasaran utama

Target tetap satu alur operator yang konsisten:

```text
Transaksi ARKAS/BKU
→ Lengkapi data SPJ
→ Validasi
→ READY
→ Penomoran
→ Preview / Unduh
→ FINAL / Arsip
```

Prinsip:

- source tidak menimpa manual overlay;
- preview/download tidak menerbitkan nomor;
- numbered/final document terkunci;
- perubahan penting diaudit;
- UI menunjukkan next action;
- theme/UX menggunakan primitive yang sudah ada, bukan design system baru.

---

## 2. Fondasi yang sudah tersedia

- multi-database main + tenant;
- provision/backup/restore/reset tenant;
- safe ARKAS/BKU sync dan source status;
- transaksi + detail kategori;
- use case SPJ terpisah dari controller;
- package/checklist/numbering/template/generator foundation;
- SiPLah basic fields/policy/placeholders;
- global theme system + dark appearance;
- `x-ui.*`, `ui-*`, table/form/page primitives;
- SPJ Paket dengan sub-tab Rincian / Isian Manual / Penomoran;
- Dokumen & Template compact di dalam tab Rincian;
- theme-aware Paket, Isian Manual, dark form controls, numbering quarter cards.

---

## 3. P0 — konsistensi aturan dan workflow kritis

### 3.1 Seragamkan validasi tanggal pengadaan

Target canonical:

```text
Tanggal Pesanan <= Tanggal Transaksi
Tanggal Pesanan <= Tanggal BAP
Tanggal BAP <= Tanggal BAST
```

Saat ini `SpjPackageUseCase` mengikuti aturan tersebut, tetapi `TransactionController` masih lebih ketat untuk BAP/BAST. P0 adalah menghilangkan perbedaan entry point dan menambah focused test.

### 3.2 Stabilkan Surat Pesanan internal

Rule yang sudah diputuskan harus dipertahankan:

- content/substansi blocking pada persiapan;
- nomor tidak blocking sebelum numbering;
- nomor wajib pada NUMBERED/FINAL;
- SiPLah marketplace order berbeda dari internal order number.

Tambahkan regression test agar circular blocker tidak muncul kembali.

### 3.3 Generator PDF/Word/Excel

Target:

- preview/download stabil;
- template error mudah dipahami;
- identitas sekolah/vendor/penerima/pajak/nomor konsisten;
- preview bebas side effect;
- Word/Excel/PDF memakai source field yang benar.

### 3.4 Lifecycle/locking/revision

Target:

```text
DRAFT → READY → NUMBERED → FINAL
```

Dengan cancellation/reissue yang audited. Jangan mengandalkan UI untuk locking; backend tetap authoritative.

### 3.5 Numbering quarter hardening

- candidate valid/READY saja;
- domain per document type;
- nomor aktif tidak ditimpa;
- canceled slots/history aman;
- operasi atomik/audited;
- preview numbering bila diperlukan.

---

## 4. P1 — data integrity dan authorization

### 4.1 Reconciliation ARKAS snapshot/diff

- before/after snapshot;
- field-level diff manusiawi;
- keputusan eksplisit;
- final document tidak berubah otomatis.

### 4.2 Role/authorization

- ADMIN / OPERATOR / VIEWER;
- mutation sensitif dilindungi backend;
- cancel/reopen/final/reset/restore/configuration diauthorize eksplisit;
- test per role.

### 4.3 End-to-end per kategori

Wajib mencakup:

```text
BARANG
KONSUMSI
PEMELIHARAAN
SPPD
HONOR_PEGAWAI
JASA_LAINNYA
```

Untuk `KONSUMSI`, verifikasi juga bahwa auto-fill peserta mengambil Dapodik-only dan manual participant tetap berfungsi.

### 4.4 Tenant operations

Backup/restore/reset tenant diuji end-to-end dan database global tetap aman.

---

## 5. P2 — UX operator dan cleanup GUI

### 5.1 Package workspace

Struktur yang sudah dipilih dipertahankan:

```text
Rincian
├── Rincian Transaksi
└── Dokumen & Template
Isian Manual
Penomoran
```

Berikutnya:

- pindahkan DOM placement compatibility ke markup/component langsung ketika refactor aman;
- pertahankan compact document list;
- rename label peserta konsumsi agar eksplisit menyebut Dapodik;
- hapus hard-coded legacy color ketika view disentuh.

### 5.2 Single CTA per status

Contoh:

- belum lengkap → Lengkapi data;
- READY → Penomoran;
- NUMBERED → Preview/Unduh;
- perlu koreksi → Batalkan nomor/Buka paket sesuai authorization.

### 5.3 Field-level validation UX

Pesan dekat field, bahasa operator, link “Perbaiki” bila masuk akal.

### 5.4 Dirty-state / save-next / persistent filter

Ditambahkan setelah workflow inti stabil.

### 5.5 Mobile visual regression

Tutup `MOBILE_VISUAL_QA_TODO.md`. Package layout terbaru termasuk scope wajib QA.

---

## 6. P2/P3 — SiPLah

Status SiPLah sekarang **partial/in progress**, bukan sekadar planned.

Sudah ada:

- `payment_method=siplah`;
- `siplah_order_number`;
- vendor/invoice/reference fields;
- placeholder SiPLah;
- requirement policy SiPLah vs Non-SiPLah.

Berikutnya:

- verify ownership source vs operator;
- verify end-to-end package/document output;
- focused tests safe sync + template output;
- jangan membuat kategori `SIPLAH`.

Integrasi API eksternal SiPLah tetap di luar scope sampai MVP lokal stabil.

---

## 7. P3 — laporan BOS

Setelah workflow inti stabil:

- K7A, K7, K8, SPTJM;
- K7B/K7C;
- Buku Pembantu Kas/Bank/Pajak;
- laporan bulanan;
- rekap belanja modal/barang-jasa;
- daftar honor;
- batch export per transaksi/triwulan.

---

## 8. Definition of Done release candidate

Release candidate hanya layak jika:

- satu transaksi dapat diproses source → final tanpa edit database manual;
- semua kategori utama berhasil end-to-end;
- purchase-date rule konsisten antar entry point;
- Surat Pesanan tidak mengalami circular blocker;
- participant konsumsi Dapodik/manual berperilaku benar;
- preview/download bebas side effect;
- numbering tidak ganda;
- manual overlay aman terhadap sync;
- final document tidak dapat diedit normal;
- VIEWER tidak dapat mutation;
- critical tests lulus;
- backup/restore/reset tenant teruji;
- mobile minimum QA ditutup;
- frontend build berhasil untuk theme/dark mode.

---

## 9. Urutan pengerjaan yang direkomendasikan

```text
1. Samakan validasi tanggal pengadaan
2. Regression test Surat Pesanan / numbering
3. Stabilkan generator & preview
4. Lifecycle/locking/revision
5. Numbering quarter hardening
6. Reconciliation snapshot/diff
7. Authorization hardening
8. End-to-end semua kategori
9. SiPLah end-to-end verification
10. Mobile QA + GUI cleanup
11. Laporan BOS + release hardening
```

Baca bersama:

```text
docs/CURRENT_PROGRESS.md
docs/SPJ_DESIGN_DECISIONS.md
docs/USER_SCENARIOS.md
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
```
