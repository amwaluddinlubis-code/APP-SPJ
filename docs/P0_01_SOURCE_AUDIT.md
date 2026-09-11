# P0-01 — Source Audit & Real-Database Verification Guide

Terakhir diperbarui: **2026-09-11**

Status: **FUNCTIONAL SIX-CATEGORY E2E PASS / REAL-DATA VERIFICATION ACTIVE**

> Dokumen ini bukan sumber status utama. Status release selalu mengikuti `CURRENT_PROGRESS.md`. Bagian lama yang menyatakan six-category deterministic E2E belum PASS sudah superseded.

## 1. Posisi P0-01 sekarang

Deterministic six-category E2E sudah PASS untuk kategori canonical:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Workflow yang sudah dibuktikan secara regression:

```text
source transaction + item
→ create/open DRAFT
→ simpan data kategori
→ READY validation
→ NUMBERED
→ preview/download
→ FINAL
```

Preview/download tidak mengalokasikan nomor baru, FINAL terkunci dari edit normal, dan source item/tax tidak boleh dimutasi oleh workspace Paket.

Checkpoint deterministic yang berlaku dirangkum di `CURRENT_PROGRESS.md`.

## 2. Real-data baseline aktif

Baseline sekolah nyata terbaru yang sudah dianalisis:

```text
transactions                  170
transaction_items             407
spj_packages                   66
spj_documents                   0
document_number_sequences       0
document_number_formats         0
operational_audit_logs        309
fiscal_years                    6
fund_sources                    2
```

Health database:

```text
SQLite integrity_check  ok
foreign_key_check       0 violation
jumlah tabel            43
```

Seluruh **66 Paket SPJ tahun 2026 berstatus READY** pada snapshot tersebut.

Coverage kategori 2026:

```text
BARANG             41
HONOR_PEGAWAI      12
JASA_LAINNYA        9
KONSUMSI             2
PEMELIHARAAN         2
SPPD                  0
```

SPPD nyata tersedia pada 2025 sebanyak 14 transaksi. Tidak ada satu fiscal year nyata yang memuat keenam kategori sekaligus. Jangan membuat SPPD 2026 fiktif hanya untuk memenuhi coverage.

## 3. Auditor read-only canonical

Command:

```powershell
php artisan spj:audit-quarter <NPSN> --quarter=<Q>
```

Opsional:

```powershell
php artisan spj:audit-quarter <NPSN> --quarter=<Q> --year=<TAHUN>
php artisan spj:audit-quarter <NPSN> --quarter=<Q> --fund-source=<SUMBER_DANA>
php artisan spj:audit-quarter <NPSN> --quarter=<Q> --json
```

Guardrail:

- tidak membuat database tenant yang hilang;
- tidak memanggil provisioning/migration maintenance;
- membuka SQLite existing secara query-only;
- `PRAGMA query_only = ON`;
- hanya SELECT/PRAGMA pemeriksaan;
- tidak memperbaiki data otomatis;
- hash byte database dan metadata tenant tidak boleh berubah akibat audit.

Regression utama:

```text
tests/Feature/SpjQuarterAuditCommandTest.php
tests/Feature/SpjQuarterAuditPolicyTest.php
```

## 4. Fokus audit 66 READY package

Audit real-data sekarang harus menjawab:

- apakah source masih ACTIVE;
- apakah `requires_reconciliation` masih true;
- apakah seluruh `item_description` tersedia;
- apakah gross/tax/net konsisten;
- apakah detail kategori sesuai transaksi;
- apakah requirement document applicable terpenuhi;
- apakah JASA_LAINNYA aggregate gross/tax/net konsisten;
- apakah PEMELIHARAAN linkage bahan/upah valid;
- apakah SiPLah hanya memakai requirement yang applicable;
- apakah ada orphan/duplicate/invalid lifecycle state;
- blocker pertama apa yang mencegah canonical numbering order.

Kandidat audit bukan izin untuk mutation otomatis.

## 5. Aturan mutation real-data

Sebelum mutation:

1. audit original secara read-only;
2. simpan hasil audit;
3. identifikasi blocker legitimate;
4. mutation hanya pada isolated copy;
5. source transaction dan `transaction_items` tetap immutable;
6. jangan menebak penerima/vendor/SPPD/template;
7. numbering mengikuti canonical order dan berhenti pada blocker yang lebih awal.

Operator overlay hanya boleh dikoreksi bila ada evidence dari dokumen/source/operator input yang sah.

## 6. Coverage dan evidence

P0-01 sekarang mempunyai dua jenis evidence yang berbeda:

### FUNCTIONAL PASS

Dibuktikan oleh deterministic regression/CI untuk enam kategori dan lifecycle.

### REAL-DATA VERIFICATION ACTIVE

Dibuktikan bertahap pada salinan database sekolah nyata. Belum boleh disebut real-data six-category PASS karena dataset nyata tidak menyediakan enam kategori dalam satu fiscal year dan numbering/output real-data belum diselesaikan untuk seluruh kandidat.

## 7. Definition of Done real-data verification

Real-data verification dapat ditutup bila:

- seluruh package target sudah diaudit read-only;
- blocker legitimate terdokumentasi;
- isolated-copy workflow berhasil untuk kandidat yang datanya memang tersedia;
- numbering tidak melompati urutan canonical;
- source data tidak dimutasi untuk memaksa PASS;
- generated document memakai data yang benar;
- official-template/runtime verification yang applicable tercatat;
- kategori yang tidak tersedia pada dataset nyata dicatat sebagai coverage limitation, bukan difabrikasi.

## 8. Dokumen terkait

Gunakan urutan berikut:

```text
CURRENT_PROGRESS.md      -> status dan evidence terbaru
DEVELOPMENT_ROADMAP.md   -> pekerjaan aktif berikutnya
P0_VERIFICATION_KIT.md   -> command dan release-safety verification
SPJ_DESIGN_DECISIONS.md  -> kontrak domain permanen
```
