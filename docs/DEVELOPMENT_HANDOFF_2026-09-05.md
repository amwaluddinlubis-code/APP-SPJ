# SPJ BOSP Web — Development Handoff 2026-09-05

Dokumen ini adalah snapshot kerja terbaru branch `gui-standardization` dan menjadi acuan sebelum pengembangan berikutnya dimulai.

## 1. Status branch

Branch aktif:

```text
gui-standardization
```

Checkpoint terakhir yang sudah divalidasi:

```text
7d02661 — refactor(gui): canonicalize spj package overview
```

Phase 4 UI **dipause sementara** setelah Phase 4.3D dinyatakan PASS.

Phase 4.3E — internal package tabs shell + Rincian panel **belum dikerjakan** dan tidak dianggap checkpoint aktif.

## 2. Status GUI standardization

Yang sudah selesai/diterima pada Phase 4:

- Phase 4.1 — Document Templates header actions;
- Phase 4.2 — Transactions physical canonicalization;
- Phase 4.3A — SPJ Page Header;
- Phase 4.3B — SPJ Persiapan filter, status badge, dan actions;
- Phase 4.3C — SPJ package list/card badges dan actions;
- Phase 4.3D — SPJ package overview, top navigation, validation, dan template actions.

Yang belum dilanjutkan:

- Phase 4.3E — internal package tabs shell + Rincian;
- Isian Manual canonicalization;
- Penomoran UI canonicalization;
- Laporan/Monitoring physical cleanup;
- final compatibility CSS cleanup.

Pekerjaan tersebut bukan prioritas utama saat ini.

## 3. Hasil verifikasi terakhir

Phase 4.3D terakhir dilaporkan:

```text
theme:qa: PASS
build: PASS
view:cache: PASS
diff --check: PASS
SyncedDataSpjEntitiesTest: PASS — 2 tests, 12 assertions
```

Visual desktop:

```text
Dark: PASS
Slate: PASS
Yellow: PASS
Indigo: PASS
Violet: PASS
```

Area yang tersedia pada dataset telah lolos. Kondisi yang tidak tersedia pada dataset tetap ditandai `RVR` dan bukan otomatis dianggap gagal.

Computed contrast tetap `RVR`.

Mobile visual QA tetap TODO sesuai `docs/MOBILE_VISUAL_QA_TODO.md` dan tidak boleh disebut mobile-verified/mobile-complete sebelum TODO tersebut ditutup.

## 4. Test strategy saat ini

Gunakan focused verification untuk perubahan kecil/terarah:

```text
npm run theme:qa
npm run build
php artisan view:cache --no-interaction
git diff --check
php artisan test --compact <test paling relevan>
```

Jangan menjalankan full test suite pada setiap checkpoint kecil.

Full suite belum boleh diklaim hijau total. Ada kegagalan unrelated yang pernah teridentifikasi pada `CriticalDocumentWorkflowTest` terkait perbedaan ekspektasi pesan `Duplikasi invoice` versus implementasi `Keunikan invoice`.

## 5. Perubahan prioritas pengembangan

Mulai setelah handoff ini, prioritas utama berpindah dari polishing Phase 4 UI ke **fitur pembelian SiPLah MVP**.

Alasan:

- fitur bisnis SiPLah memiliki nilai lebih tinggi untuk operator;
- GUI standardization dasar sudah cukup stabil untuk ditunda;
- pengembangan harus hemat siklus perubahan dan focused test;
- hindari refactor visual besar sebelum fitur SiPLah MVP stabil.

## 6. Keputusan domain SiPLah

SiPLah **bukan kategori SPJ baru**.

Kategori SPJ tetap:

```text
BARANG
KONSUMSI
PEMELIHARAAN
SPPD
HONOR_PEGAWAI
JASA_LAINNYA
```

SiPLah adalah karakteristik/metode/sumber proses pembelian atau pembayaran pada transaksi.

Field/konsep existing yang harus diaudit terlebih dahulu:

```text
payment_method = siplah
vendor_name / identitas rekanan
order_number / order_date
invoice / nomor bukti pembelian jika sudah tersedia
payment_reference jika sudah tersedia
receipt_recipient_name
transaction items
dokumen paket berdasarkan kategori SPJ
```

Jangan membuat kategori `SIPLAH` pada `spj_category`.

## 7. Strategi SiPLah MVP

Sebelum menambah migration/field baru:

1. audit model, migration, service, form, dan view yang sudah memiliki dukungan SiPLah;
2. petakan field existing yang dapat dipakai ulang;
3. identifikasi field minimum yang benar-benar belum tersedia;
4. implementasi MVP tanpa integrasi API eksternal SiPLah;
5. pertahankan sumber ARKAS/BKU sebagai readonly source;
6. jangan menimpa data operator saat sinkronisasi;
7. gunakan focused tests saja;
8. baru kembali ke Phase 4 UI setelah fitur SiPLah stabil.

## 8. Batas aman bisnis yang tidak boleh berubah

- ARKAS/BKU adalah sumber data dan tidak ditimpa oleh input operator.
- `payment_description` adalah uraian operator SPJ.
- `manual_description` tidak digunakan.
- `receipt_recipient_name` adalah data operator dan sinkronisasi tidak boleh menimpanya.
- preview/download tidak boleh membuat nomor secara diam-diam.
- numbering mengikuti jenis dokumen + urutan tanggal/peristiwa, bukan urutan input.
- dokumen bernomor/final terkunci dan koreksi harus melalui lifecycle yang sah.
- sumber dana mengikuti tahun anggaran aktif.

## 9. Working tree lokal yang harus dilindungi

Jangan restore/stash/reset/checkout/overwrite file berikut tanpa instruksi eksplisit user:

```text
resources/views/dashboard.blade.php
resources/views/students/index.blade.php
```

File berikut harus tetap untracked dan tidak dikomit:

```text
spj-bosp-web.code-workspace
```

## 10. Dokumen terkait

Baca bersama:

```text
AGENTS.md
.ai/rules/index.md
docs/CURRENT_PROGRESS.md
docs/DEVELOPMENT_ROADMAP.md
docs/SPJ_DESIGN_DECISIONS.md
docs/GUI_STANDARDIZATION.md
docs/MOBILE_VISUAL_QA_TODO.md
docs/SIPLAH_MVP_PLAN.md
```

## 11. Next action

Next action yang disepakati:

> Audit dukungan SiPLah yang sudah ada pada codebase, lalu tentukan gap minimum untuk SiPLah MVP sebelum melakukan perubahan source.
