# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-11**

Dokumen ini adalah sumber status release utama untuk branch `gui-standardization`.

Definisi status:

- **FUNCTIONAL PASS**: dibuktikan oleh source + deterministic CI/regression;
- **REAL-DATA VERIFIED**: dibuktikan pada salinan database sekolah nyata tanpa mengarang data yang tidak tersedia;
- **RVR**: masih memerlukan real-value/runtime/operator verification untuk aspek yang tidak bisa dibuktikan hanya dari CI;
- **DEFERRED**: sengaja tidak dikerjakan pada fokus pengembangan saat ini, bukan berarti PASS.

---

## Checkpoint terbaru

### Release gate branch aktif

```text
code gate : a2509aad9104706da2709fcd07cce0b973282cb1
subject   : test(spj): isolate numbered description flash state
CI run    : 34595391755
CI job    : 103249843120
result    : PASS — 248 tests / 1880 assertions
```

Evidence gate:

```text
Frontend build   : PASS
Blade compile    : PASS
SPJ Critical     : PASS — 248 tests / 1880 assertions
Repository Pint : ADVISORY — 3 style issues, non-blocking
```

Pint advisory pada gate ini:

- `app/Services/ArkasStagingService.php`;
- `app/Services/SpjDocumentNumberService.php`;
- `tests/Feature/SyncProgressUiTest.php`.

Dua file pertama/terakhir tidak boleh dibaca sebagai functional regression hanya karena style advisory. `SpjDocumentNumberService.php` memiliki satu style issue baru yang dapat dibersihkan pada maintenance pass berikutnya tanpa mengubah aturan bisnis.

Commit setelah code gate yang hanya mengubah dokumentasi tidak menciptakan functional gate baru. Sampai ada source/test commit berikutnya, `a2509aad...` adalah checkpoint functional canonical.

---

## Status release saat ini

Status keseluruhan:

```text
FUNCTIONAL CORE : PASS
REAL-DATA       : VERIFICATION ACTIVE
OFFICIAL OUTPUT : RVR ACTIVE
BROWSER/RUNTIME : RVR ACTIVE
FINAL RELEASE   : NOT YET
```

Aplikasi belum boleh disebut final release-ready hanya karena deterministic CI hijau. Official-template output, browser/operator QA, dan real-data verification tetap merupakan gate terpisah.

---

## P0-01 — Six-category SPJ end-to-end

Status:

```text
FUNCTIONAL SIX-CATEGORY E2E : PASS
REAL-DATA VERIFICATION      : ACTIVE
INSTALLED-RUNTIME           : DEFERRED / RVR
```

Kategori canonical:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Deterministic workflow sudah menjaga:

```text
DRAFT
-> READY
-> NUMBERED
-> preview XLSX/PDF
-> FINAL
```

beserta validation, tenant context, lifecycle lock, dan audit yang relevan.

### Real-data baseline terakhir

Baseline audit yang sudah dicatat sebelumnya:

```text
tables                    : 43
transactions              : 170
transaction_items         : 407
spj_packages              : 66
spj_documents             : 0
document_number_sequences : 0
document_number_formats   : 0
operational_audit_logs    : 309
fiscal_years              : 6
fund_sources              : 2
```

Untuk 2026 pada baseline tersebut:

```text
BARANG          : 41
HONOR_PEGAWAI   : 12
JASA_LAINNYA    : 9
KONSUMSI        : 2
PEMELIHARAAN    : 2
SPPD            : 0
```

SPPD nyata tersedia pada data 2025, bukan 2026. Jangan fabrikasi SPPD 2026 untuk memaksa coverage.

Original baseline harus tetap immutable; mutation real-data hanya dilakukan pada isolated copy.

---

## P0-02 — Document generator / template

Status:

```text
FUNCTIONAL GENERATOR        : PASS
TEMPLATE UPLOAD HARDENING   : PASS
OFFICIAL-TEMPLATE VISUAL QA : RVR
```

Kontrak yang sudah dijaga:

- preview/download tidak menerbitkan nomor;
- template invalid tidak mengganti template aktif;
- unresolved placeholder tidak boleh diam-diam lolos ke final output;
- functional artifact generation tidak sama dengan visual verification dokumen resmi.

Detail placeholder canonical berada di `DOCUMENT_TEMPLATE_PLACEHOLDERS.md`.

---

## P0-03 — Numbering, lifecycle, correction & rollback

Status:

```text
FUNCTIONAL NUMBERING        : PASS
INDIVIDUAL CANCEL           : PASS
TAIL ROLLBACK               : PASS
QUARTER ROLLBACK            : PASS
FUND-SOURCE SEQUENCE SCOPE  : PASS
POST-NUMBERING EDIT RULE    : PASS
REAL-DATA OPERATOR QA       : ACTIVE
```

Kontrak terbaru:

### Individual cancel

```text
nomor -> CANCELLED permanen
sequence tidak mundur
nomor tidak dipakai ulang
```

Reissue setelah individual cancel mendapat nomor/sequence baru.

### Tail rollback

Rollback dari sequence `N`:

```text
N ... tail aktif dilepas
Paket terdampak -> DRAFT
sequence dibangun ulang dari numbering yang masih sah
released number boleh dipakai kembali
```

Rollback tidak boleh melintasi nomor SPJ yang telah `CANCELLED` secara individual, karena nomor tersebut adalah history permanen.

### Cancel numbering triwulan

Dependency canonical:

```text
TW4 -> TW3 -> TW2 -> TW1
```

Triwulan lebih lama tidak dapat di-reset selama triwulan setelahnya masih mempunyai numbering aktif dalam context yang sama.

Boundary dependency dan sequence:

```text
School + Fiscal Year + Fund Source
```

Numbering sumber dana lain tidak boleh memblokir rollback atau mengubah sequence context aktif.

Full quarter reset ditolak bila target quarter memiliki nomor SPJ cancelled individual permanen.

### Sequence per fund source

`document_number_sequences` sekarang di-scope oleh:

```text
fiscal_year_id
+ fund_source_id
+ format_name
+ period_key
```

Migration tenant baru:

```text
database/migrations/school/2026_09_11_180000_scope_document_number_sequences_by_fund_source.php
```

### Edit setelah numbering

`item_description`:

```text
NUMBERED -> boleh dikoreksi
nomor/sequence -> tetap
FINAL -> terkunci
```

Data manual Paket seperti category/payment/vendor/penerima/procurement/detail kategori tetap terkunci pada NUMBERED/FINAL dan harus melalui rollback/lifecycle resmi terlebih dahulu.

Regression canonical:

```text
tests/Feature/SpjNumberingRollbackTest.php
tests/Feature/DocumentNumberingWorkflowTest.php
tests/Feature/SpjOwnershipMigrationTest.php
tests/Feature/SpjWorkspaceMigrationTest.php
```

Panduan domain lengkap:

```text
docs/NUMBERING_CORRECTION_AND_ROLLBACK.md
```

---

## P0-04 — Authorization

Status:

```text
FUNCTIONAL PASS
```

Contract:

- VIEWER read-only;
- OPERATOR workflow operasional sesuai permission;
- ADMIN lifecycle/maintenance/sensitive action;
- role authorization tidak menggantikan tenant/context isolation.

Rollback numbering dan cancel numbering triwulan adalah jalur administrator.

---

## P0-05 — Safe synchronization / reconciliation

Status:

```text
FUNCTIONAL PASS
REAL-DATA RECONCILIATION VERIFICATION : ACTIVE
```

Kontrak utama:

```text
ARKAS/BKU = readonly source
overlay operator = dipertahankan
source missing = jangan hapus pekerjaan operator
source returning = reuse identity yang sama
NUMBERED/FINAL = tidak dimutasi diam-diam
```

Detail canonical berada di `SYNCHRONIZATION.md`.

---

## P0-06 — Tenant isolation

Status:

```text
FUNCTIONAL PASS
```

Boundary canonical:

```text
School + Fiscal Year + Fund Source
```

Boundary ini sekarang juga diterapkan pada sequence numbering/rollback sehingga sumber dana lain tidak ikut ter-reset.

---

## P0-07 — School database maintenance

Status:

```text
FUNCTIONAL MAINTENANCE : PASS
INSTALLED-RUNTIME      : DEFERRED / RVR
```

Reset/provision/backup/restore tetap harus menjaga primary database dan tenant lain.

---

## P0-08 — Generic ARKAS Importer

Status:

```text
FUNCTIONAL HARDENING : PASS
OPERATOR DATA TEST   : ACTIVE / NEXT VERIFICATION
```

Importer/sync tidak boleh menulis data fiktif untuk memaksa downstream SPJ PASS.

---

## Unified Employee Identity

Status:

```text
FUNCTIONAL IDENTITY CORE : PASS
REAL-SCHOOL VERIFICATION : ACTIVE
```

Identity matching harus konservatif. Normalized name ambigu tidak boleh menyebabkan silent merge.

Auto-fill KONSUMSI tetap menggunakan provenance Dapodik yang sah; participant manual tetap diperbolehkan.

---

## Quarter Audit

Status:

```text
FUNCTIONAL PASS
READY FOR REAL-DATA AUDIT
```

Quarter audit harus tetap read-only ketika digunakan untuk menentukan baseline.

---

## SiPLah

Status:

```text
FUNCTIONAL CORE                 : PASS
GENERATED-DOCUMENT E2E          : RVR
OFFICIAL-TEMPLATE OUTPUT        : RVR
```

SiPLah tetap channel/payment context, bukan `spj_category`.

---

## P1 aktif

Prioritas setelah numbering rollback functional PASS:

1. jalankan read-only audit pada baseline real-data 66 Paket READY;
2. perbaiki hanya blocker legitimate yang ditemukan audit;
3. lakukan operator/real-data QA untuk rollback numbering dengan isolated copy, terutama alignment nomor SPJ terhadap source order ARKAS;
4. verifikasi JASA_LAINNYA multi-recipient pada generated document nyata;
5. verifikasi PEMELIHARAAN bahan + upah end-to-end dokumen;
6. verifikasi SiPLah generated-document + official template;
7. browser QA desktop/laptop;
8. operational audit E2E;
9. employee identity real-school + participant roster.

Mobile tetap non-blocker untuk target release desktop/laptop saat ini.

---

## P2 / maintenance debt

- field validation UX;
- Pint advisory repository, termasuk style di `SpjDocumentNumberService.php`;
- GUI/compatibility cleanup;
- icon/action consistency;
- performance;
- Bridge `bin/obj` hygiene;
- report foundation;
- mobile polish setelah target desktop/laptop stabil.

---

## Open verification / release blockers

Belum boleh diberi status final sampai evidence tersedia untuk:

- official template visual/output RVR;
- browser/operator runtime QA;
- real-data numbering/rollback operator verification pada isolated copy;
- real-data category-specific document QA yang belum selesai;
- installed-runtime checks yang masih DEFERRED.

Tidak ada blocker functional deterministic baru dari implementasi numbering rollback; functional gate terbaru hijau.

---

## Aturan evidence

1. Jangan mengubah source data hanya agar test/audit real-data PASS.
2. Jangan memakai deterministic fixture sebagai bukti bahwa real-data verified.
3. Jangan memakai screenshot/UI appearance sebagai pengganti backend regression.
4. Jangan menyatakan CI baru untuk commit docs-only.
5. Setiap source/test change berikutnya harus menghasilkan gate baru sebelum menggantikan checkpoint `a2509aad...`.
6. Jika business rule berubah, sinkronkan `SPJ_DESIGN_DECISIONS.md`, feature guide terkait, test, dan dokumen ini.
