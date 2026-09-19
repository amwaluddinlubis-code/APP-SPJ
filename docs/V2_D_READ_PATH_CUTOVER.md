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
- selain selector/source eligibility, consumer melakukan **live consumer parity**
  terhadap summary yang benar-benar dihasilkan jalur production legacy pada
  `ActiveSpjContext`; projected provenance parity saja tidak cukup;
- bila count/cancelled/gross/tax/net/komponen pajak berbeda sedikit pun, consumer
  fail-closed ke legacy;
- `SpjReportUseCase::reportData()` dan tab laporan mengaktifkan gate summary ini,
  sedangkan `export()` tetap legacy;
- regression `V2DReportSummaryCutoverTest` mengunci tiga kondisi: fixture nyata
  dengan stale legacy-context harus fallback; clone yang context-nya disejajarkan
  secara eksplisit boleh membaca V2; synthetic raw-source drift setelah itu wajib
  kembali ke legacy. Protected transaction/package/document tetap immutable dan
  VIEWER tetap dapat membaca summary ketika live parity exact.

Runtime evidence pada 2026-09-19: focused gate `V2DReadPathSelectorTest`,
`V2DCanonicalReadAdapterTest`, `V2DWorkflowParityTest`, dan
`V2DReportSummaryCutoverTest` PASS dengan **16 test / 354 assertions / 16
deprecations**. `vendor/bin/pint --dirty --format agent` PASS dan
`git diff --check` bersih. D4 step 2 source + fail-safe consumer gate ditutup
sebagai runtime PASS. Effective production cutover tetap blocked karena fixture
nyata membuktikan live legacy `ActiveSpjContext` dapat berbeda dari canonical V2;
pada kondisi itu consumer secara benar tetap memakai legacy.

### Live-context mismatch root cause

Source audit after the step-2 runtime PASS confirms the remaining blocker is an
intentional V2-C transition boundary, not the new report selector.

V2-C resolves canonical context from:

```text
year(transaction_date) + fund_source_id
→ matching fiscal_years.id
→ spj_transactions.fiscal_year_id
```

while the current production legacy scope still resolves from:

```text
transactions.fiscal_year_id + transactions.fund_source_id
→ Transaction::forSpjContext()
```

`SpjV2LegacyMigrationService::classifyCanonicalContext()` explicitly accepts a
row as `ACTIVE_CANONICAL` when transaction date + fund source resolve a valid
context even if legacy `fiscal_year_id` is stale, and records that reason.
`migrateOne()` writes the effective fiscal-year id only to the V2 transaction and
provenance bridge; it does **not** rewrite the legacy transaction. Therefore a
numbered package can be validly linked to an ACTIVE_CANONICAL V2 transaction while
the legacy production query for the same active context returns zero rows.

Do not repair this by mutating legacy `transactions.fiscal_year_id` or protected
Paket/document lifecycle data merely to enable cutover. The next D4 task is a
read-only compatibility/context strategy that can prove equivalence from the
provenance bridge before any broader consumer is switched.

### D4 step 3 — effective-context compatibility audit/resolver

Source implementation now adds `SpjV2EffectiveContextCompatibilityService`.
This service is read-only and does not modify legacy transaction context, Paket,
documents, numbering, snapshots, or V2 rows.

It has two responsibilities:

1. `audit()` inventories every legacy provenance row and all existing Paket,
   classifying Paket as `ALIGNED`, `STALE_LEGACY_FISCAL_YEAR`, or `UNSAFE`;
2. `resolve()` resolves one explicit
   `fiscal_year_id + fund_source_id + source_id` canonical context back to its
   deterministic legacy provenance and Paket set.

Resolver rules:

- canonical target must be `ACTIVE_CANONICAL`;
- fund source must remain identical across legacy and effective context;
- `LEGACY_DUPLICATE` provenance may participate only when it targets the same
  ACTIVE_CANONICAL transaction;
- missing provenance, cross-context provenance, wrong Paket bridge, or more than
  one Paket targeting the same canonical transaction blocks the resolver;
- stale legacy `fiscal_year_id` is reported explicitly but never rewritten;
- missing V2 schema returns `UNAVAILABLE`; no source/context identity is guessed.

Regression `V2DEffectiveContextCompatibilityTest` uses the real isolated Tenant A
fixture and is intended to prove:

- all 67 Paket / 66 NUMBERED are inventoried;
- stale legacy fiscal-year rows are surfaced;
- duplicate provenance remains deterministic when bridge-safe;
- package sets cannot leak across effective contexts;
- a synthetic wrong Paket bridge fails closed;
- audit/resolver execution does not mutate legacy transaction/Paket/document
  state;
- missing V2 schema fails unavailable.

The test writes its exact audit inventory to
`storage/app/v2-c-rehearsal/reports/test-v2d-effective-context-compatibility.json`
for local inspection.

Runtime evidence on 2026-09-19: focused gate
`V2DPackageDocumentParityTest`, `V2DReadPathSelectorTest`,
`V2DReportSummaryCutoverTest`, and
`V2DEffectiveContextCompatibilityTest` PASS with **16 tests / 174 assertions /
16 deprecations**. `vendor/bin/pint --dirty --format agent` PASS and
`git diff --check` clean.

Exact isolated-fixture inventory:

- audit status: `COMPATIBLE_STALE_CONTEXT`;
- Paket total: 67;
- Paket NUMBERED: 66;
- Paket FINAL: 0;
- Paket aligned legacy/effective fiscal year: 0;
- Paket stale legacy fiscal year: 67;
- Paket unsafe: 0;
- Paket with `LEGACY_DUPLICATE` provenance: 1;
- provenance aligned legacy context: 121;
- provenance stale legacy fiscal year: 170;
- provenance fund-source mismatch: 0;
- provenance `LEGACY_DUPLICATE`: 104.

D4 step 3 is therefore **RUNTIME PASS**. The result proves the transition is
deterministic and fund-source safe, but also proves that every existing Paket in
this fixture is attached to a legacy transaction whose `fiscal_year_id` is stale
relative to the effective canonical context. The next cutover step must therefore
use the provenance/effective-context resolver for read compatibility rather than
relying on `Transaction::forSpjContext()` for Paket/list membership.

### D4 step 4 — package read membership compatibility

Source implementation now adds `SpjV2PackageReadMembershipService`.
It composes the existing read-path selector with the effective-context
compatibility resolver and returns package membership only when all of these are
true:

- `SPJ_V2_READ_PATH=v2` is explicitly requested;
- exactly one canonical source resolves for the active fiscal-year/fund-source;
- effective-context compatibility returns `RESOLVED`;
- package/provenance relations are deterministic and fund-source safe.

Its output is intentionally identity-only:

```text
package_ids
legacy_transaction_ids
canonical_transaction_ids
source_id
compatibility_mode
```

A `null` result means the caller must stay on the legacy membership path. The
service never rewrites legacy fiscal-year context or Paket/document lifecycle.

Regression `V2DPackageReadMembershipTest` is intended to prove:

- the union of effective-context memberships equals the exact 67 Paket fixture;
- exactly 66 returned Paket are NUMBERED;
- every context membership equals the direct ACTIVE_CANONICAL package bridge;
- returned legacy transactions never cross fund source;
- config rollback `v2 -> legacy` is immediate;
- a wrong-but-valid Paket V2 bridge makes membership fail closed;
- resolver execution does not mutate transaction/Paket/document state.

Runtime evidence on 2026-09-19: focused gate
`V2DReadPathSelectorTest`, `V2DPackageDocumentParityTest`,
`V2DReportSummaryCutoverTest`, `V2DEffectiveContextCompatibilityTest`, and
`V2DPackageReadMembershipTest` PASS with **19 tests / 216 assertions / 19
deprecations**. `vendor/bin/pint --dirty --format agent` PASS and
`git diff --check` clean. D4 step 4 is therefore **RUNTIME PASS**.

The production Paket list and report package table are **not switched yet**.
Both expose follow-up actions such as open Paket, preview, and download, while
those action/detail guards still validate the legacy transaction context. Showing
effective-context Paket before those action boundaries are compatible would
create rows that are visible but cannot be opened safely.

### D4 step 5 — read-only document action context compatibility

Source implementation now adds `SpjV2PackageReadContextService` and wires it
only into `SpjDocumentUseCase` read-only document actions:

- package PDF download;
- package Excel download;
- package preview;
- package preview PDF;
- individual template download;
- individual template PDF download;
- individual template preview;
- individual template preview PDF.

The compatibility rule is conservative:

1. a legacy-aligned Paket passes unchanged;
2. otherwise `SPJ_V2_READ_PATH=v2` must resolve exact package membership through
   Step 4;
3. package id, legacy transaction id, canonical transaction id, and fund source
   must all match the active effective context;
4. only then is the legacy transaction's `fiscal_year_id` normalized to the
   active effective fiscal year **in memory only**;
5. the fiscal-year relation is cleared so subsequent template/profile lookup
   reloads the effective year;
6. no legacy/V2/Paket/document database row is updated.

The in-memory normalization is required because both
`SpjPackageTemplateSelector` and `SpjTemplateService` historically derive
template/profile/year placeholders from `package->transaction->fiscal_year_id`.
Passing the context guard without this normalization could render a document with
the wrong fiscal-year template or school profile.

Regression `V2DPackageReadContextTest` is staged to prove:

- a stale NUMBERED Paket is allowed only under resolved V2 compatibility;
- database `transactions.fiscal_year_id` remains unchanged;
- effective-year templates are selected while stale-year templates are excluded;
- `SPJ_V2_READ_PATH=legacy` still blocks the stale package;
- a wrong-but-valid V2 package bridge remains fail-closed;
- transaction/Paket/document protected state remains unchanged.

`SpjWorkspaceUseCase::tabPaket()` is intentionally **not** changed yet. Opening
the Paket workspace exposes mutation controls, including the NUMBERED correction
carve-out, so effective-context read compatibility must not silently widen write
eligibility. Runtime evidence on 2026-09-19: focused gate
`V2DReadPathSelectorTest`, `V2DPackageDocumentParityTest`,
`V2DEffectiveContextCompatibilityTest`, `V2DPackageReadMembershipTest`,
`V2DPackageReadContextTest`, `SpjDocumentGeneratorHardeningTest`, and
`SpjPreviewExcelParityTest` PASS with **27 tests / 353 assertions / 27
deprecations**. `vendor/bin/pint --dirty --format agent` PASS and
`git diff --check` clean. D4 step 5 is therefore **RUNTIME PASS**.

### D4 step 6 — Paket workspace read-only compatibility

Source implementation now permits a stale-context Paket to open from
`/spj?tab=paket&package_id=...` only when Step 5 effective-context preparation
returns `v2_compat`.

The compatibility branch does **not** reuse the normal mutation-heavy Paket
workspace. It returns the dedicated `spj.package-readonly` view containing:

- transaction summary;
- read-only item detail;
- validation messages as text only;
- Preview Paket/template;
- Download PDF/Excel/template operations already covered by Step 5.

The read-only surface deliberately does not render routes/actions for:

- `spj.update`;
- `spj.ready`;
- Paket/document numbering;
- FINAL;
- cancel/replace;
- quarter numbering;
- participant/manual editors or any other Paket mutation.

Legacy-aligned Paket continue through the original workspace unchanged.
`SPJ_V2_READ_PATH=legacy`, unresolved membership, or an unsafe/wrong V2 bridge
still redirect away from the stale-context Paket. The V2 compatibility branch
also skips `CreateSpjDraftUseCase`, previous/next legacy navigation, participant
roster hydration, and other mutation-adjacent helpers.

Regression `V2DPackageWorkspaceReadOnlyTest` is staged to prove:

- stale NUMBERED Paket opens only as `spj.package-readonly` under a resolved V2
  effective context;
- effective fiscal year exists only in-memory while persisted legacy fiscal year
  remains unchanged;
- all selected templates belong to the effective fiscal year;
- the dedicated view contains approved Preview/Download actions but no lifecycle
  mutation route;
- legacy config keeps the stale Paket unavailable;
- a wrong-but-valid Paket bridge remains fail-closed;
- protected transaction/Paket/document state remains unchanged.

Runtime evidence on 2026-09-19: focused gate
`V2DPackageReadMembershipTest`, `V2DPackageReadContextTest`,
`V2DPackageWorkspaceReadOnlyTest`, `SpjPackageNavigationContextTest`,
`SpjMainTabsRenderingTest`, `SpjDocumentGeneratorHardeningTest`, and
`SpjPreviewExcelParityTest` PASS with **25 tests / 351 assertions / 25
deprecations**. `vendor/bin/pint --dirty --format agent` PASS and
`git diff --check` clean. D4 step 6 is therefore **RUNTIME PASS**.

### D4 step 7 — Paket list membership cutover

Source implementation now switches the read-only Paket list membership and Paket
summary counters through `SpjV2PackageReadMembershipService` when
`SPJ_V2_READ_PATH=v2` and the effective context is fully resolved.

The cutover remains deliberately narrow:

- `SpjWorkspaceUseCase::packageListData()` uses exact effective-context
  `package_ids` when available;
- `overviewMetrics()` uses the same package membership query for
  `totalPackages` and `numberedPackages`, preventing a zero legacy summary
  above a non-empty V2 Paket list;
- `readyTransactions`, Persiapan, legacy previous/next navigation, transaction
  mutation, numbering, lifecycle, and settlement queries remain legacy;
- each listed row is tagged in-memory as `legacy` or `v2_compat`;
- V2-compatible rows are visibly labelled **Baca saja** / **Buka baca**;
- opening a `v2_compat` row continues into the dedicated Step 6 read-only
  workspace, not the mutation-heavy workspace;
- unresolved/unsafe membership still falls back immediately to the legacy Paket
  query;
- rollback remains config-only by setting `SPJ_V2_READ_PATH=legacy`.

Regression `V2DPackageListCutoverTest` is staged to prove:

- paginator membership equals the direct ACTIVE_CANONICAL Paket bridge for the
  active effective context;
- Paket total/numbered metrics use the same membership as the list;
- every stale row is tagged `v2_compat`;
- legacy config removes stale-context Paket from the list immediately;
- switching `legacy -> v2` restores the same membership without data repair;
- wrong-but-valid Paket bridge falls back to legacy and does not expose the stale
  Paket;
- list rendering adds read-only labels without adding mutation routes;
- list/metric reads do not mutate protected transaction/Paket/document state.

Runtime evidence for D4 step 7 is currently **RVR**.

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
