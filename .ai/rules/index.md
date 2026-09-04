# App SPJ BOS — AI Development Rules

Dokumen ini adalah aturan kerja untuk AI/coding agent pada repository App SPJ BOS.

Baca bersama `AGENTS.md` dan dokumentasi project sebelum mengubah source.

## 1. Current development priority

Prioritas aktif saat ini adalah:

> Audit dan implementasi SiPLah MVP secara hemat perubahan dan hemat testing cycle.

Phase 4 GUI standardization sedang **dipause sementara** pada checkpoint aman:

```text
7d02661 — refactor(gui): canonicalize spj package overview
```

Phase 4.3E belum dimulai dan tidak boleh diteruskan otomatis kecuali user meminta kembali ke GUI standardization.

## 2. SiPLah domain rule

SiPLah **bukan kategori SPJ**.

Jangan menambah:

```text
spj_category = SIPLAH
```

Kategori SPJ tetap:

```text
BARANG
KONSUMSI
PEMELIHARAAN
SPPD
HONOR_PEGAWAI
JASA_LAINNYA
```

SiPLah adalah karakteristik/metode/sumber proses pembelian atau pembayaran.

Prefer existing canonical concept:

```text
payment_method = siplah
```

Kategori dokumen tetap mengikuti sifat belanja.

## 3. Audit before migration

Sebelum membuat field/migration baru untuk SiPLah:

1. cari dukungan existing;
2. cek model dan migration;
3. cek ownership source vs operator;
4. cek transaction detail form;
5. cek package SPJ;
6. cek template/document generator;
7. cek tests existing.

Jangan menambah field yang menduplikasi makna field existing.

Contoh field yang wajib dicek terlebih dahulu:

```text
payment_method
payment_reference
vendor_name
vendor_owner
vendor_npwp
order_number
order_date
bap_number
bap_date
bast_number
bast_date
receipt_recipient_name
recipient_name
no_bukti
```

## 4. Source data ownership

ARKAS/BKU adalah sumber data readonly.

AI tidak boleh membuat perubahan yang menyebabkan sinkronisasi:

- menimpa data manual/operator;
- menghapus package SPJ manual;
- menghapus `receipt_recipient_name`;
- menghapus `payment_description` operator;
- mengganti data final tanpa reconciliation/lifecycle yang sah.

`manual_description` tidak digunakan dan tidak boleh dihidupkan kembali.

## 5. SPJ lifecycle invariants

Jangan mengubah invariants berikut tanpa instruksi eksplisit user:

- preview/download tidak boleh membuat nomor secara diam-diam;
- numbering dilakukan setelah data siap;
- nomor ditentukan oleh domain jenis dokumen dan urutan tanggal/peristiwa;
- nomor tidak mengikuti urutan input transaksi;
- dokumen NUMBERED/FINAL terkunci;
- koreksi setelah numbering harus melalui cancel/revision/unlock lifecycle yang sah;
- quarter numbering untuk READY adalah workflow utama;
- sumber dana terkunci pada fiscal year aktif.

## 6. Existing SPJ architecture

Pertahankan boundary use case:

```text
SpjWorkspaceUseCase
SpjPackageUseCase
SpjNumberingUseCase
SpjDocumentUseCase
SpjReportUseCase
```

Jangan mengembalikan domain logic besar ke `SpjController`.

Untuk perubahan frontend, jangan mengubah backend lifecycle hanya demi mempermudah UI.

## 7. GUI rules

Ikuti `docs/GUI_STANDARDIZATION.md`.

Gunakan primitive existing bila sesuai:

```text
x-ui.field
x-ui.input
x-ui.select
x-ui.textarea
x-ui.button
x-ui.form-section
x-ui.status-badge
x-ui.badge
x-ui.toolbar
x-page-header
```

Jangan membangun design system baru.

Gunakan semantic tokens `--ui-*` dan theme tokens existing.

Success/warning/danger harus tetap memiliki makna semantik.

Phase 4 compatibility CSS boleh dipertahankan jika area legacy masih memerlukannya. Jangan menghapus selector hanya untuk mengurangi line count.

## 8. Testing strategy

Gunakan focused verification untuk perubahan terarah.

Default minimum frontend/backend checkpoint:

```text
npm run theme:qa
npm run build
php artisan view:cache --no-interaction
git diff --check
php artisan test --compact <focused-test>
```

Jangan menjalankan full suite pada setiap perubahan kecil kecuali perubahan memang luas atau user meminta.

Jangan mengklaim full suite hijau tanpa menjalankannya.

Known caveat:

`CriticalDocumentWorkflowTest` pernah gagal karena mismatch pesan `Duplikasi invoice` vs `Keunikan invoice`. Perlakukan sebagai unrelated known issue sampai diperbaiki/dites ulang.

## 9. Browser/runtime claims

Jangan mengklaim browser/runtime PASS tanpa bukti aktual.

Jika kondisi tidak tersedia pada dataset, laporkan `RVR`, bukan PASS.

Computed contrast yang tidak bisa diukur tetap `RVR`.

Mobile visual QA masih TODO sesuai:

```text
docs/MOBILE_VISUAL_QA_TODO.md
```

Jangan menyebut aplikasi mobile-verified/mobile-complete sampai TODO tersebut ditutup.

## 10. Protected local working files

Jangan restore, reset, stash, checkout, overwrite, atau commit file berikut tanpa instruksi eksplisit user:

```text
resources/views/dashboard.blade.php
resources/views/students/index.blade.php
```

File berikut harus tetap untracked:

```text
spj-bosp-web.code-workspace
```

## 11. Change scope discipline

Untuk file Blade besar seperti:

```text
resources/views/spj/index.blade.php
```

hindari full-file rewrite jika patch lokal yang sempit lebih aman.

Jangan melakukan formatting seluruh file bila scope hanya satu panel/control.

Selalu audit diff sebelum commit.

## 12. SiPLah MVP scope

Target awal:

- kenali transaksi SiPLah;
- gunakan field existing sebanyak mungkin;
- tampilkan data SiPLah relevan pada transaction/SPJ workspace;
- pertahankan kategori SPJ normal;
- bawa data yang diperlukan ke dokumen;
- safe sync tetap aman;
- focused tests lulus.

Out of scope awal:

- API SiPLah eksternal;
- scraping marketplace;
- SSO SiPLah;
- kategori SPJ baru;
- redesign numbering;
- redesign document lifecycle;
- refactor UI besar bersamaan dengan implementasi SiPLah.

## 13. Required docs before SiPLah work

Baca:

```text
AGENTS.md
.ai/rules/index.md
docs/DEVELOPMENT_HANDOFF_2026-09-05.md
docs/SIPLAH_MVP_PLAN.md
docs/CURRENT_PROGRESS.md
docs/SPJ_DESIGN_DECISIONS.md
docs/GUI_STANDARDIZATION.md
```

## 14. Next action

Jika user meminta lanjut fitur SiPLah:

> Audit dulu dukungan SiPLah existing pada branch aktif. Jangan langsung membuat migration atau field baru. Buat peta existing support + gap minimum, lalu implementasikan hanya setelah scope jelas.
