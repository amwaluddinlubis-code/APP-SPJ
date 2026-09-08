# Migrasi Detail Transaksi ↔ Paket SPJ

Terakhir diperbarui: **2026-09-08**

> **STATUS: PASS / ARCHIVED**
>
> Ownership migration selesai. Dokumen ini dipertahankan sebagai referensi keputusan migrasi dan guardrail agar write-path lama tidak dihidupkan kembali.

Keputusan bisnis permanen berada di `docs/SPJ_DESIGN_DECISIONS.md`; gap release aktif ada di `docs/CURRENT_PROGRESS.md`.

## 1. Tujuan akhir yang sudah dicapai

```text
Detail Transaksi = fakta transaksi/source + koreksi item_description
Paket SPJ        = seluruh data dokumen pertanggungjawaban
```

Operator tidak mengisi field SPJ yang sama pada dua workspace.

## 2. Ownership final

### Detail Transaksi

- source ARKAS/BKU;
- status source/reconciliation;
- informasi Paket SPJ;
- rincian item source;
- `item_description` sebagai satu-satunya field item editable.

```text
description       readonly
item_description  editable
quantity          readonly
unit              readonly
unit_price        readonly
amount            readonly
```

`item_description` wajib tersimpan sebelum Paket dapat dibuat/dibuka.

UI Detail Transaksi sekarang ringkas:

```text
Header
→ Informasi Referensi ARKAS/BKU + Total Pajak
→ Rincian Barang/Jasa
→ Status Paket SPJ
```

Rincian PPN/PPh/SSPD tidak lagi menjadi panel besar di Detail Transaksi.

### Pajak

Pajak tetap source transaction:

```text
PPN
PPh 21
PPh 22
PPh 23
PPh 4(2)
SSPD
Total Pajak
Nilai Netto
```

Paket SPJ hanya membaca nilai tersebut. Rincian lengkap tersedia pada tab readonly **Rincian Pajak**.

### Paket SPJ

Mutation dokumen hanya di Paket:

- `spj_category`;
- `payment_description`;
- `payment_method`;
- `payment_reference`;
- `receipt_recipient_name` / Penerima Utama;
- vendor/rekanan;
- SiPLah/invoice;
- data kategori;
- numbering;
- preview/generate/download/finalisasi.

## 3. Write-path final

Create/open draft:

```text
transactions.prepare-spj
→ SpjPreparationController
→ CreateSpjDraftUseCase
```

Update Paket:

```text
spj.update
→ UpdateSpjPackageDetailsUseCase
```

Update `item_description`:

```text
transactions.spj-descriptions.update
→ TransactionController::updateSpjDescriptions()
```

Write-path berikut sudah dipensiunkan dan tidak boleh dihidupkan kembali:

```text
spj.prepare
SpjPackageUseCase
transactions.manual-description.update
TransactionController::updateManualDescription()
SpjDocumentController legacy
SpjReportController legacy
```

## 4. Category switching

Keenam kategori canonical:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Semua partial memiliki `data-spj-section` dan dapat di-switch tanpa full page reload. Persist kategori dilakukan via AJAX; backend tetap authoritative.

SiPLah bukan kategori. Untuk BARANG, UI memakai radio mutually-exclusive `SiPLah / Non SiPLah` yang disinkronkan dengan `payment_method`.

## 5. Pajak readonly

`tax-reference.blade.php` adalah readonly display dan tidak mengirim field tax ke Paket.

Backend Paket tidak menulis:

```text
gross_amount
ppn
pph21
pph22
pph23
pph4
sspd
tax_total
net_amount
```

Forged request tidak boleh mengubah source tax.

## 6. PEMELIHARAAN linkage

Selector pasangan bahan/upah sekarang ditampilkan di workspace Paket SPJ untuk UX, tetapi relationship state tetap transaction/context-owned melalui endpoint maintenance-link khusus.

Selector bukan field package form.

## 7. Refinement UI setelah ownership migration

Setelah migration boundary ditutup, workspace Paket dirapikan tanpa mengubah ownership:

- toolbar `Semua Paket / Paket Sebelumnya / Paket Setelahnya`;
- summary `Periode / Penerima / Bruto / Pajak / Nilai Dibayarkan`;
- tab `Rincian / Isian Manual / Rincian Pajak / Penomoran`;
- nomor otomatis menjadi strip informasi, bukan input readonly;
- Data Umum Dokumen compact: textarea 5 baris kiri, field umum kanan;
- tabel non-BARANG compact dengan satu pagination dan Penerima Utama;
- format uang accounting `1.000` tanpa `Rp`/desimal;
- BARANG SiPLah/Non SiPLah satu radio group;
- PEMELIHARAAN selector pasangan berada di baris kategori.

Refinement ini adalah presentation/workspace work, bukan perubahan ownership domain.

## 8. Verification

Focused regression suite SPJ setelah refactor workspace besar dilaporkan user **ALL PASS** pada 2026-09-08.

Cakupan test ownership meliputi:

- gateway create/open draft;
- `item_description` ownership;
- tax immutability;
- category switch AJAX;
- category partial rendering;
- NUMBERED/FINAL locking;
- maintenance linkage endpoints;
- request legacy tidak dapat menulis lewat path lama.

Perubahan visual/JS yang masuk setelah checkpoint test tersebut tetap perlu browser QA/rebuild. Full release E2E sampai preview/download/FINAL seluruh kategori tetap dicatat terpisah di `CURRENT_PROGRESS.md`.

## 9. Guardrail permanen

- jangan memindahkan edit `item_description` ke Paket SPJ;
- jangan membuka edit quantity/unit/harga/amount di Paket;
- jangan membuat pajak editable di Paket;
- jangan menggabungkan SiPLah menjadi kategori baru;
- jangan menghidupkan kembali route/use-case legacy;
- jangan menggunakan JS sebagai satu-satunya enforcement business rule;
- jangan mengubah lifecycle/numbering hanya demi refactor UI;
- jangan menganggap focused test ownership sama dengan full release E2E generator/lifecycle.

## 10. Status akhir

Ownership migration: **PASS**.

Pekerjaan berikutnya bukan lagi “menyelesaikan migrasi”, melainkan release hardening yang ada di:

```text
docs/CURRENT_PROGRESS.md
docs/DEVELOPMENT_ROADMAP.md
```
