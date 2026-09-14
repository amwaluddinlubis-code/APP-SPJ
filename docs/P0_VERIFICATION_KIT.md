# P0 Verification Kit

Terakhir diperbarui: **2026-09-14**

Dokumen ini mendefinisikan alat verifikasi release-safety yang dipakai berulang. Status release authoritative berada di `CURRENT_PROGRESS.md`.

## 1. Functional gate aktif

Evidence gate hidup di bagian ini. Dokumen lain wajib me-link ke sini dan tidak boleh mempromosikan commit docs-only sebagai code gate baru.

Latest completed green source gate:

```text
commit        : 887d0219142d634e6a85b6672d3bffb02b5b1584
CI run        : 34839580942 (#480)
workflow      : SPJ Critical Verification
result        : SUCCESS
```

Blocking workflow #480:

```text
npm run build          -> PASS
php artisan view:cache -> PASS
SPJ Critical PHPUnit   -> PASS : 287 tests / 2236 assertions
Full Unit PHPUnit      -> PASS : 60 tests / 203 assertions
Full Feature PHPUnit   -> PASS : 411 tests / 2972 assertions
```

Repository Pint tetap **advisory** (`continue-on-error: true`). Pada run #480, `php vendor/bin/pint --test` masih melaporkan 5 style issue repository-wide yang sudah ada sebelumnya:

```text
app/Console/Commands/TestIsolatedSpjCancellation.php
app/Console/Commands/TestIsolatedSpjNumbering.php
app/Console/Commands/TestIsolatedSpjQuarterRollback.php
app/Console/Commands/TestIsolatedSpjTailRollback.php
tests/Feature/SpjIsolatedQuarterRollbackCommandTest.php
```

Karena step tersebut advisory, workflow #480 tetap SUCCESS. Jangan menyatakan repository-wide Pint clean sampai lima issue itu benar-benar diperbaiki.

### Integration repair yang ditutup sebelum gate #480

CI #478 sebelumnya berhenti pada 2 regression SPJ Critical. Keduanya ditutup tanpa mengubah business rule aplikasi:

1. `SpjNumberingRollbackTest::test_item_description_can_change_when_numbered_but_not_when_final`
   - stale direct-controller invocation diganti dengan HTTP route canonical `transactions.spj-descriptions.update`;
   - NUMBERED tetap mengizinkan koreksi `item_description`;
   - FINAL tetap menolak perubahan.
2. `SpjWorkspaceMigrationTest::test_numbered_keeps_manual_paths_locked_but_allows_item_description_and_final_locks_everything`
   - test diselaraskan dengan contract canonical bahwa payload field substansi pada NUMBERED diabaikan/tidak disimpan;
   - `vendor_name` tetap tidak berubah;
   - category switch tetap ditolak;
   - koreksi uraian tetap diperbolehkan;
   - FINAL tetap locked.

Perbaikan tersebut masuk pada:

```text
b61cdc621539cb6fc62dd17efc22da16a9c2a14c
test: close SPJ critical integration regressions
```

CI #479 kemudian membuktikan SPJ Critical dan Unit sudah hijau, lalu membuka satu stale full-feature source-contract assertion di `TransactionNumberedItemDescriptionUiTest`. Test itu masih mencari implementasi inline lama untuk `payment_description`, sementara controller canonical sudah mendelegasikan ke `SpjDescriptionService`. Assertion diselaraskan dengan service delegation pada:

```text
887d0219142d634e6a85b6672d3bffb02b5b1584
test: align description UI contract with service delegation
```

Tidak ada lifecycle, numbering, safe-sync, tenant ownership, atau authorization rule yang diubah untuk membuat gate hijau.

### Coverage penting yang dipertahankan gate #480

Gate #480 mencakup dan mempertahankan functional regression untuk:

- six-category SPJ lifecycle;
- NUMBERED/FINAL description correction contract;
- numbering registry, cancel, reserved sequence, tail/quarter rollback;
- preview/download tanpa numbering side effect;
- safe sync dan reconciliation;
- authorization + tenant boundary;
- Livewire mutation authorization Phase 2;
- database maintenance/reset hardening;
- Generic ARKAS Importer;
- template upload/download/placeholder/master lifecycle;
- true single-sheet individual XLSX download;
- canonical worksheet HTML preview;
- generated document validator;
- SiPlah policy;
- employee identity;
- quarter audit;
- workspace/ownership migration.

Functional gate tidak sama dengan browser/document visual verification. Microsoft Excel/LibreOffice fidelity, print area, page breaks, header/footer, drawing, defined-name/formula kompleks, browser interactions, dan installed runtime tetap RVR/DEFERRED sesuai `CURRENT_PROGRESS.md`.

Workflow `.github/workflows/spj-critical.yml` mengabaikan `docs/**` dan root `*.md`; dokumentasi-only commit setelah #480 tidak menggantikan code gate `887d0219142d634e6a85b6672d3bffb02b5b1584`.

---

## 2. Command verifikasi canonical

Untuk verifikasi lokal/developer umum:

```powershell
php artisan spj:verify
```

Urutan default:

```text
Repository Pint --test
-> SPJ Critical PHPUnit
-> npm run build
-> php artisan view:cache
-> optional real-tenant audit
```

GitHub Actions menambahkan full Unit dan full Feature suite sebagai blocking coverage.

Opsi iterasi developer tersedia, tetapi `--skip-*` bukan evidence release final:

```powershell
php artisan spj:verify --skip-style
php artisan spj:verify --skip-build
php artisan spj:verify --skip-tests
```

---

## 3. SPJ Critical suite

```powershell
php artisan test --testsuite="SPJ Critical" --compact
```

Suite ini menjaga release-safety lintas fitur, termasuk six-category lifecycle, numbering, preview/download side-effect, safe sync, authorization/tenant boundary, Livewire mutation authorization, maintenance, ARKAS importer, template upload/download, placeholder inspector, master template lifecycle, SiPlah, employee identity, quarter audit, ownership/workspace migration, reconciliation, dan generated-document validation.

Nama/jumlah test dapat berubah. Angka pada §1 hanya authoritative untuk gate #480 dan tidak boleh diasumsikan tetap sama pada commit berikutnya.

---

## 4. Canonical numbering registry verification

Source of truth metadata numbering:

```text
app/Services/SpjNumberingDocumentRegistry.php
```

Metadata canonical yang dimiliki registry:

```text
code
label
numbered
applicable_categories
channel
event_date_rule
number_target
scope_rule
```

Consumer yang wajib membaca registry/policy adapter dan tidak boleh mempunyai daftar domain numbering sendiri:

```text
DocumentNumberFormatController / halaman Format Penomoran
SpjNumberingUseCase / halaman Penomoran Triwulan
SpjNumberingPolicyService
SpjNumberingGateService
SpjNumberingOrderService
SpjDocumentNumberService
SpjDocumentLifecycleService
SpjDocumentLifecycleUseCase
SpjSingleNumberingUseCase
```

Current canonical numbered codes:

```text
SPJ
PESANAN
BAP
BAST
SPK
RAB
SURAT_TUGAS_PERJALANAN_DINAS
```

Daftar di atas adalah snapshot dokumentasi, bukan source executable. `SpjDocumentTypeRegistry` tetap menangani template/placeholder/output dan bukan source sequence numbering.

---

## 5. Real-tenant audit — read-only

Canonical command:

```powershell
php artisan spj:audit-quarter <NPSN> --quarter=<Q> --year=<TAHUN> --fund-source=<SUMBER_DANA>
```

Kontrak:

- tidak provision tenant;
- tidak migrate/repair sebagai side effect;
- `PRAGMA query_only=ON`;
- tidak mengubah transaction/item/package;
- tidak mengubah metadata `SchoolDatabase`;
- hash baseline harus tetap sama.

Evidence real-data aktif yang sudah terdokumentasi:

```text
2026 / TW2 / Fund Source 1 : 66 transaksi ber-item / 66 Paket READY -> PASS
2026 / TW2 / Fund Source 2 : 0 transaksi -> partition check PASS pada scope yang diuji
TW1/TW3/TW4 2026           : tidak mempunyai transaksi pada baseline aktif
```

SPPD 2026 tidak tersedia pada real data; jangan dibuat fiktif untuk coverage.

---

## 6. Read-only numbering preflight

```powershell
php artisan spj:preflight-numbering <NPSN> \
  --year=<TAHUN> \
  --quarter=<Q> \
  --fund-source=<SUMBER_DANA>
```

Kontrak preflight:

- tenant database dipaksa query-only;
- tidak activate/provision/migrate;
- tidak membuat `QuarterNumberingRun`;
- tidak membuat format/sequence/nomor;
- SHA-256 baseline diverifikasi tidak berubah;
- canonical package order dipreview;
- validator numbering dijalankan tanpa mutation.

Evidence yang sudah terdokumentasi:

```text
PREFLIGHT RESULT : PASS
baseline hash    : UNCHANGED
number issued    : NONE
```

---

## 7. Isolated mutation QA

Mutation real-data hanya boleh dilakukan pada copy terisolasi, bukan baseline asli.

```powershell
php artisan spj:test-numbering-copy <NPSN> --database=<COPY> --year=<Y> --quarter=<Q> --fund-source=<FS>
php artisan spj:test-cancel-copy <NPSN> --database=<COPY> --year=<Y> --quarter=<Q> --fund-source=<FS>
php artisan spj:test-tail-rollback-copy <NPSN> --database=<FRESH_COPY> --year=<Y> --quarter=<Q> --fund-source=<FS>
php artisan spj:test-quarter-rollback-copy <NPSN> --database=<FRESH_COPY> --year=<Y> --quarter=<Q> --fund-source=<FS>
```

Evidence yang sudah PASS:

```text
FIRST NUMBER
BPU01 -> sequence 1 / NUMBERED
baseline UNCHANGED

INDIVIDUAL CANCEL + RESERVE
BPU01 sequence 1 -> CANCELLED permanen
BPU02 -> sequence 2
baseline UNCHANGED

TAIL ROLLBACK
1,2,3 -> rollback from 2 -> checkpoint 1 -> sequence 2 dapat diterbitkan lagi
baseline UNCHANGED
```

`spj:test-quarter-rollback-copy` mempunyai functional regression/command, tetapi isolated real-data runtime quarter rollback belum menjadi evidence pada checkpoint ini. Jangan fabrikasi data untuk memaksakan coverage.

---

## 8. Numbering token `{TW}`

```text
{TW} -> I / II / III / IV
```

Prefix `TW.` tidak ditambahkan otomatis. Operator dapat menambahkan literal prefix dalam pattern, misalnya `{SEQ}/SPJ/{SCHOOL}/TW.{TW}/{YEAR}`. Nomor lama yang sudah diterbitkan tidak dimutasi otomatis.

---

## 9. Audit snapshot dan diff

```powershell
php artisan spj:audit-quarter <NPSN> --quarter=<Q> --output=storage/app/audits/before.json
php artisan spj:audit-quarter <NPSN> --quarter=<Q> --output=storage/app/audits/after.json
php artisan spj:audit-diff storage/app/audits/before.json storage/app/audits/after.json --fail-on-regression
```

Snapshot dengan school/year/quarter/fund-source berbeda tidak boleh diperlakukan sebagai delta yang sama.

---

## 10. Browser dan document QA

CI/PHPUnit tidak membuktikan:

- browser interaction aktual;
- desktop/laptop layout aktual;
- mobile/tablet usability aktual;
- HTML preview pixel-perfect terhadap renderer Microsoft Excel;
- Word/Excel/PDF visual fidelity;
- Office repair/fidelity pada workbook nyata;
- print area/page break/header/footer;
- hasil cetak fisik;
- installed Windows runtime.

Area tersebut tetap RVR/DEFERRED sesuai `CURRENT_PROGRESS.md`.

---

## 11. Verification workflow per perubahan

```text
1. Reproduce masalah nyata
2. Perbaiki source/domain/UI pada scope yang tepat
3. Tambahkan focused regression hanya bila bug perlu dikunci
4. Jalankan focused test yang relevan
5. Untuk source release-safety, tunggu CI blocking hijau
6. Jika menyentuh real-data/source mapping, audit before/after secara read-only
7. Jika menyentuh UI/generator, verifikasi melalui operator/browser/document flow
8. Update CURRENT_PROGRESS dan ROADMAP dengan evidence aktual
```

Jangan membuat test/smoke test baru hanya untuk memperbesar coverage setelah contract cukup dibuktikan. Untuk perubahan numbering metadata, mulai dari `SpjNumberingDocumentRegistry`.