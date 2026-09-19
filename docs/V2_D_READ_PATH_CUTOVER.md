# V2-D — Read-Path Cutover

Status: **D1 SHADOW PARITY FUNCTIONAL PASS / D2 CANONICAL ADAPTER RUNTIME PASS / D3 PACKAGE/DOCUMENT PARITY RUNTIME PASS / DOWNSTREAM REPORT-TAX-PERIOD PARITY RUNTIME PASS / PRODUCTION CUTOVER BLOCKED**.

Baseline V2-C3: commit `8876478`, 187 canonical V2 transactions, 291 legacy
provenance mappings, 431 source links, 187 transaction overlays, 245 item
overlays, 67 package links, zero unresolved overlay conflicts. V2-C3 remains the
migration gate; V2-D must not weaken it.

## Objective

V2-D moves application reads from the transitional legacy/fresh model to the
canonical V2 model without changing ARKAS, operator-owned overlays, Paket SPJ,
document numbering, snapshots, or lifecycle state.

Cutover is intentionally staged. A production read path must not switch merely
because V2 tables exist. Shadow parity must prove that the current fresh read
model and canonical V2 resolve the same transaction identity, source membership,
and operator overlays first.

## Current read-path inventory

The application is still mixed:

- `/transaksi` list uses `SpjFreshTransaction` and raw-mirror payloads.
- `TransactionDetailWorkspace` resolves a legacy `Transaction`, overlays fresh
  ARKAS values when available, and synthesizes a legacy-shaped model when only a
  fresh transaction exists.
- `TransactionController` uses legacy lookup with a fresh fallback.
- `SpjWorkspaceUseCase` preparation, package navigation, and overview metrics
  still query legacy `Transaction` / `SpjPackage`.
- Paket/document generation, reports, settlement, numbering, fiscal-period
  closure, tax views, and supporting relations still depend on legacy model
  contracts and must be cut over only after their required fields/relations are
  mapped explicitly.

Therefore V2-D does **not** start by replacing `Transaction::query()` globally.

## D1 — Shadow read parity

`SpjV2ReadParityService` compares the current active fresh projection against V2
using the canonical boundary. Fresh rows are restricted to fiscal-year/fund-source
contexts represented by active V2 rows; legacy-only contexts remain outside the
V2-D gate. Operator overlays are read from the current legacy read model through
the V2 provenance map and compared with the canonical V2 overlays, including
many-to-one provenance.

`fiscal_year_id + fund_source_id + source_id + source membership hash`.

It checks:

1. active transaction identity exists on both sides;
2. source membership (`ID_KAS_UMUM`) is identical;
3. transaction overlay fields are identical;
4. item-description overlays are attached to the same source identity;
5. unresolved V2 transaction/item reconciliation conflicts are zero.

The service is read-only. It does not update either model.

`spj:v2-read-parity` is restricted by `V2BIsolatedDatabaseGuard` and requires an
explicit isolated rehearsal database plus source identity evidence. It returns a
non-zero exit code when parity fails.

Focused regression `V2DReadParityTest` performs a fresh V2-C migration on an
isolated clone, then requires 187 fresh and 187 V2 canonical transactions with
zero identity/membership/overlay mismatches. A synthetic V2 overlay drift must
make parity fail closed.

Runtime evidence on 2026-09-19: `php artisan test --compact
tests/Feature/V2DReadParityTest.php` passed with 2 tests, 20 assertions, and 2
deprecations. The rehearsal found the ARKAS evidence source from the documented
project-relative fixture `../../backupdata/datasmp.db` without requiring manual
environment setup. `vendor/bin/pint --dirty --format agent` and `git diff --check`
also passed. The synthetic overlay-drift regression remained fail-closed. This is
functional local rehearsal evidence only; no production read path has switched.

## D2 — Canonical read adapter

D1 runtime parity sudah memberikan evidence lokal yang cukup untuk membuka implementasi D2.
`SpjV2CanonicalReadService` sekarang menyediakan jalur baca read-only untuk konteks
`Fiscal Year + Fund Source + source_id` tanpa memindahkan controller/Livewire production.

Kontrak adapter:

- hanya membaca `ACTIVE_CANONICAL` pada context eksplisit;
- fakta ARKAS berasal dari `spj_transaction_sources -> arkas_source_identity_registry
  -> current_raw_mirror_row_id`;
- kategori, uraian pembayaran, payment method/reference, penerima override, dan
  item description hanya berasal dari tabel overlay V2;
- identifier dapat berupa membership hash canonical **atau** `legacy_source_key`
  dari provenance bridge, sehingga 22 mapping `DETERMINISTIC` tetap dapat dibuka
  memakai URL/source key legacy;
- gross/tax/net dihitung dari source rows canonical dan tax receipt rows (10/30),
  bukan dari field operator;
- output menyertakan legacy provenance dan source-item membership untuk audit.

Regression `V2DCanonicalReadAdapterTest` memverifikasi context partition,
187 canonical transactions, aggregate financial parity, legacy deterministic
identifier resolution, source-link/item cardinality, dan ownership overlay.
Runtime evidence pada 2026-09-19: `php artisan test --compact
tests/Feature/V2DCanonicalReadAdapterTest.php` PASS dengan 3 test, 225 assertions,
dan 3 deprecations. `vendor/bin/pint --dirty --format agent` PASS dan
`git diff --check` bersih. Dengan evidence ini, gate runtime D2 ditutup. Production
UI tetap belum dialihkan ke V2 karena D3 dan downstream workflow parity belum lulus.

## D3 — Paket/document bridge

Before any production cutover, map every relation consumed by Paket/document
flows. Existing `spj_packages.spj_transaction_id` is additive provenance from
V2-C and does not by itself authorize switching package lifecycle writes.
NUMBERED/FINAL state, document numbers, snapshots, and generated artifacts remain
protected.

Source D3 sekarang menyediakan `SpjV2PackageDocumentParityService` dan regression
`V2DPackageDocumentParityTest`. Gate membandingkan jalur authoritative legacy
`spj_packages.transaction_id -> legacy_transaction_v2_map -> spj_transactions`
dengan link additive `spj_packages.spj_transaction_id`, menolak package tanpa
provenance, link null, link ke V2 transaction berbeda walaupun FK valid, link ke
canonical context non-aktif, serta dokumen orphan. Protected Paket/document
manifest di-hash dari field lifecycle/numbering/snapshot agar parity traversal
fail-closed bila bridge tidak lagi menunjuk Paket dan dokumen yang sama.

Regression juga mencakup synthetic wrong-but-valid V2 link yang wajib FAIL dan
synthetic FINAL Paket/document untuk membuktikan parity service read-only terhadap
lifecycle protected. Runtime evidence pada 2026-09-19: `php artisan test --compact
tests/Feature/V2DPackageDocumentParityTest.php` PASS dengan 3 test, 42 assertions,
dan 3 deprecations. `vendor/bin/pint --dirty --format agent` PASS dan
`git diff --check` bersih. D3 ditutup sebagai runtime PASS. Production
`SpjPackage::transaction()` tetap memakai `transaction_id` legacy.

## Downstream workflow parity — report, tax, period

Setelah D3 PASS, gate berikutnya membuktikan consumer downstream yang masih memakai
legacy `transactions`: `SpjReportUseCase`, `TaxFilterService`, dan
`FiscalPeriodWorkflowService`.

`SpjV2CanonicalReadService` kini juga mengekspos breakdown pajak
`ppn/pph21/pph22/pph23/pph4/sspd` langsung dari row PBT raw `kas_umum`, dan
menelusuri kode kegiatan dari relasi raw
`kas_umum.id_rapbs_periode -> rapbs_periode -> rapbs -> ref_kode`.
Tidak ada fallback ke legacy transaction projection untuk fakta tersebut.

`SpjV2WorkflowParityService` dan `V2DWorkflowParityTest` membandingkan:

- identity/source fields dan gross/tax/net per canonical transaction;
- breakdown pajak per transaction dan summary pajak;
- report successful/cancelled/pending serta financial summary;
- filter semua/bulan/triwulan/semester;
- quarter closure-readiness inputs: reconciliation/source-missing, package absence,
  dan FINAL state;
- realization grouping per kode kegiatan dan rekening;
- synthetic tax-component drift dan transaction-date/quarter drift yang wajib
  fail closed.

Runtime evidence pada 2026-09-19: focused suite `V2CLegacyMigrationTest`,
`V2DReadParityTest`, `V2DCanonicalReadAdapterTest`,
`V2DPackageDocumentParityTest`, dan `V2DWorkflowParityTest` PASS dengan **16 test,
378 assertions, 16 deprecations**, tanpa failure. Gate ini menutup ulang V2-C
migration/idempotency sekaligus D1-D3 dan downstream report/tax/period parity pada
isolated rehearsal. Perbedaan representasi package pada fiscal-period readiness
tetap dicatat sebagai diagnostic bila total unfinished/closure decision identik;
synthetic tax-component drift dan date/quarter drift tetap fail-closed.

## D4 — Controlled cutover

D4 tidak boleh mengalihkan consumer mutation-heavy langsung ke canonical overlay
selama mutation operator masih hanya menulis legacy tables. Tanpa write-through
atau transitional merge yang eksplisit, `payment_description`, `item_description`,
category, receipt-recipient, dan overlay operator lain dapat menjadi stale setelah
migration snapshot. Karena itu cutover pertama harus read-only atau memakai
compatibility adapter yang mempertahankan live operator overlay dari jalur legacy
sampai write-path V2 mempunyai evidence tersendiri. `source_id` juga tidak boleh
di-hard-code; resolver cutover harus membuktikan source canonical unik pada active
`Fiscal Year + Fund Source` context atau tetap di legacy path.

D4 step 1 source implementation:

- `SPJ_V2_READ_PATH` defaults to `legacy`;
- `SpjV2CanonicalSourceResolver` resolves source identity only when exactly one
  ACTIVE_CANONICAL `source_id` exists in the requested fiscal-year/fund-source context;
- `SpjReadPathSelector` permits `v2` only after that unique resolution;
- invalid config, missing V2 schema, no canonical source, atau multiple source ids
  selalu jatuh kembali ke `legacy`;
- rollback selector bersifat configuration-only; tidak memutasi V2, legacy, Paket,
  document, numbering, atau source ARKAS;
- belum ada controller/Livewire production consumer yang memakai selector ini.

Runtime evidence pada 2026-09-19: `V2DReadPathSelectorTest` PASS dengan **7 test,
22 assertions, 7 deprecations**. `vendor/bin/pint --dirty --format agent` PASS dan
`git diff --check` bersih. Selector gate D4 step 1 ditutup sebagai runtime PASS;
production consumer tetap belum dialihkan.

D4 step 2 source implementation:

- consumer pertama adalah financial summary pada tab Laporan SPJ;
- hanya field read-only `count/cancelled_count/gross/tax/net/ppn/pph21/pph22/pph23/pph4/sspd`
  yang eligible membaca canonical V2;
- daftar Paket, pending paginator, activity/account labels, export, monitoring,
  numbering, lifecycle, dan seluruh mutation tetap memakai jalur legacy;
- `SpjV2ReportFinancialSummaryService` memakai selector D4 step 1 dan jatuh
  kembali ke legacy bila package bridge/document relation belum tersedia;
- `SpjReportUseCase::reportData()` dan tab laporan mengaktifkan gate summary ini,
  sedangkan `export()` tetap legacy;
- regression `V2DReportSummaryCutoverTest` membuktikan V2/legacy parity awal,
  synthetic raw-source drift hanya terlihat pada mode V2, rollback config mengembalikan
  summary legacy, protected transaction/package/document tetap immutable, dan
  VIEWER dapat membaca summary pada active context.

Runtime verification untuk step 2 masih **RVR** sampai focused test/Pint/diff dijalankan.

A production switch requires all of the following:

- V2-C3 semantic verify PASS;
- D1 shadow parity PASS on fresh real-data rehearsal;
- canonical read adapter regression PASS;
- Paket/document relation parity PASS;
- report/tax/period workflow parity PASS;
- authorization and active-context regression PASS;
- rollback strategy documented and tested;
- canonical source resolver tidak menebak `source_id`;
- mutation-heavy consumer mempunyai overlay write-through/compatibility strategy sebelum membaca V2 overlay;
- Pint, focused regression, `git diff --check`, and applicable CI evidence.

No production read path has been switched pada D1, D2, D3, maupun downstream parity.
