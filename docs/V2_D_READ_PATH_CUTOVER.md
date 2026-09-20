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

Runtime evidence on 2026-09-19: focused gate
`V2DPackageReadMembershipTest`, `V2DPackageReadContextTest`,
`V2DPackageWorkspaceReadOnlyTest`, `V2DPackageListCutoverTest`,
`SpjPackageNavigationContextTest`, `SpjMainTabsRenderingTest`,
`SpjDocumentGeneratorHardeningTest`, and `SpjPreviewExcelParityTest` PASS
with **29 tests / 583 assertions / 29 deprecations**.
`vendor/bin/pint --dirty --format agent` PASS and `git diff --check` clean.
D4 step 7 is therefore **RUNTIME PASS**.

### D4 step 8 — atomic report Paket table + financial summary cutover

Source implementation now extends the controlled cutover to the read-only Paket
table on the Laporan tab. The switch is **atomic**: the table must never move to
effective-context membership while the financial summary remains on a stale
legacy context.

For `reportData(..., allowV2=true)`:

1. legacy report Paket query and legacy financial summary are built as the
   fallback;
2. Step 4 effective Paket membership is resolved for the active context;
3. a live consumer summary is recomputed from the exact effective Paket IDs,
   while still using the current live legacy transaction fields for operator-owned
   data;
4. `SpjV2ReportFinancialSummaryService` compares canonical V2 financial facts
   against that exact live consumer summary;
5. only when parity is exact do both the Paket table and financial summary switch
   to V2/effective-context;
6. if membership is unavailable, bridge safety fails, or canonical raw facts
   drift, both table and summary fall back to legacy together.

The Step-2 regression contract is intentionally promoted: stale legacy
`fiscal_year_id` alone is no longer a fallback reason once the provenance/effective
context bridge from Steps 3–7 has proved the Paket membership safe.

The cutover remains narrow:

- period filters `bulan/triwulan/semester` are applied to the same effective
  Paket transaction dates;
- report rows are tagged `v2_compat` and labelled **Baca saja**;
- Preview/Download/Buka Paket reuse the already-gated Step 5–6 read-only actions;
- pending transactions, activity/account realization groupings, monitoring,
  report export, Honor/Jasa exports, settlement, numbering, lifecycle, and all
  mutation paths remain legacy.

Regression work:

- `V2DReportSummaryCutoverTest` is updated so the real stale-fiscal-year fixture
  must now resolve through V2 compatibility rather than fall back solely because
  legacy context is stale;
- `V2DReportPackageListCutoverTest` proves exact Paket membership, summary/list
  atomicity, month/quarter/semester filtering, config rollback, raw-source drift
  fail-closed behavior, read-only row labelling, and protected-state immutability.

Initial runtime attempt on 2026-09-19 did **not** pass: the focused gate ended with **8 failures / 627 assertions / 34 deprecations** because `ExtendedSpjReportUseCase` still called the parent `SpjReportUseCase` constructor with two dependencies after Step 8 added `SpjV2PackageReadMembershipService` as the third dependency. This was a DI wiring regression, not a report-parity assertion failure. Commit `956ae6c2` updates the subclass constructor and forwards the membership service to the parent. A clean rerun is required before Step 8 can be promoted.

Clean rerun after the DI fix passed on 2026-09-19 with **42 tests / 711 assertions /
42 deprecations**. `vendor/bin/pint --dirty --format agent` PASS and
`git diff --check` clean. D4 step 8 is therefore **RUNTIME PASS**.

### D4 step 9 — effective-context Honor/Jasa report flows

The active Laporan toolbar exposes **Honor Pegawai** and **Jasa Lainnya** report
flows. Before this step, their select/compose/export queries still used
`Transaction::forSpjContext()`, so a stale legacy fiscal-year could make these
flows empty even when the main report table was already reading the same
effective context through Steps 4–8.

`ExtendedSpjReportUseCase` now centralizes the read boundary:

- `reportTransactionQuery()` / `applyReportTransactionContext()` use
  `SpjV2PackageReadMembershipService::legacy_transaction_ids` when
  `SPJ_V2_READ_PATH=v2` and the effective context is fully resolved;
- otherwise the query falls back immediately to legacy `forSpjContext()`;
- transaction/operator overlays, Honor rows, and Jasa recipients are still read
  from the authoritative legacy tables;
- no V2 write-through is introduced;
- selection, compose validation, Honor export, and Jasa export all use the same
  effective transaction boundary;
- transaction IDs outside the resolved membership remain rejected by compose
  validation;
- rollback remains config-only.

Regression `V2DExtendedReportContextCutoverTest` uses controlled operator-owned
Honor/Jasa overlays on an isolated clone, pinned to a real
`STALE_LEGACY_FISCAL_YEAR` effective context. It is staged to prove:

- legacy config does not see the stale synthetic Honor/Jasa transactions;
- V2 config resolves both through effective legacy transaction membership;
- compose Honor/Jasa accepts only the resolved transaction IDs;
- an outside/forged transaction ID remains rejected;
- `v2 -> legacy` rollback is immediate;
- protected transaction/Paket/document lifecycle state is not mutated by the
  read-context flow.

Runtime evidence on 2026-09-19: focused gate `V2DExtendedReportContextCutoverTest`, `V2DReportSummaryCutoverTest`, `V2DReportPackageListCutoverTest`, `V2DPackageReadMembershipTest`, and `SpjMainTabsRenderingTest` PASS with **20 tests / 241 assertions / 20 deprecations**. `vendor/bin/pint --dirty --format agent` PASS and `git diff --check` clean. D4 step 9 is therefore **RUNTIME PASS**.

### D4 step 10 — fail-closed Pajak effective-context cutover

The Pajak page is a read-only Livewire consumer, but before this step
`TaxFilterService` still scoped rows through legacy
`Transaction::activeContext()`. A stale legacy fiscal-year could therefore make
the Pajak page incomplete even though canonical V2 tax parity had already passed.

Step 10 adds `SpjV2TaxReadContextService`.

Eligibility is intentionally stricter than membership alone:

1. `SPJ_V2_READ_PATH=v2` must resolve one canonical source for the active
   fiscal-year/fund-source;
2. effective-context compatibility must be `RESOLVED`;
3. one deterministic legacy representative is selected for each canonical
   transaction, matching the existing workflow-parity representation for
   many-to-one `LEGACY_DUPLICATE` provenance;
4. representative `source_key`, proof number, date, description, recipient, and
   every tax component (`ppn/pph21/pph22/pph23/pph4/sspd/tax_total`) must still
   match canonical raw facts exactly;
5. any identity/source-key/raw-tax drift returns `null`, and the Pajak consumer
   falls back to the existing legacy context as one unit.

When eligible, `TaxFilterService` keeps returning legacy `Transaction` models
so existing Livewire rendering/search/pagination contracts remain unchanged, but
the query is scoped to the deterministic effective-context representative IDs.
Summary and filtered summary therefore use exactly the same membership as the
table.

Follow-up navigation is also guarded:

- V2-compatible tax rows are labelled **Baca saja**;
- their Detail Transaksi URL uses `source_key`, not the stale legacy numeric ID;
- `TransactionController` / `TransactionDetailWorkspace` can resolve that key
  through the active `SpjFreshTransaction` projection;
- fresh-only transaction detail is non-persistent (`exists=false`), so
  description/reconciliation mutation remains unavailable there;
- ordinary legacy tax rows keep the existing numeric/model navigation.

Regression `V2DTaxReadContextCutoverTest` is staged to prove:

- deterministic representative membership equals the tax table output;
- annual summary, month filtering, and search remain scoped to the same effective
  transaction set;
- every V2 row has a source key and `v2_compat` read marker;
- synthetic canonical tax-raw drift forces the whole consumer back to legacy;
- config rollback remains immediate;
- the Blade surface routes V2 rows by source key and adds no tax-page mutation;
- protected transaction/Paket/document state remains unchanged.

Existing `TaxFilterLivewireTest` remains the legacy/no-V2-schema compatibility
gate, while `TransactionDetailWorkspaceAuthorizationTest` is reused to protect
fresh source-key context isolation and mutation authorization.

Runtime evidence on 2026-09-19: focused gate
`V2DTaxReadContextCutoverTest`, `TaxFilterLivewireTest`,
`TransactionDetailWorkspaceAuthorizationTest`, `V2DWorkflowParityTest`,
`V2DReadPathSelectorTest`, and `V2DEffectiveContextCompatibilityTest` PASS
with **33 tests / 199 assertions / 33 deprecations**.
`vendor/bin/pint --dirty --format agent` PASS and `git diff --check` clean.
D4 step 10 is therefore **RUNTIME PASS**.

### Post-Step-10 audit — Monitoring remains legacy-authoritative

The next visible consumer was audited after Step 10. The Monitoring tab is **not**
a read-only surface even though its pending queue is rendered through Livewire.

The same tab exposes:

- Bulk Final SPJ;
- quarter numbering;
- quarter close/reopen;
- pending rows that link to Checklist or Persiapan.

Those actions remain intentionally authorized by legacy
`ActiveSpjContext`/legacy Paket state. Switching only the queue membership to
effective-context would therefore create a split-brain UI: an operator could see
a stale-context row through V2 compatibility while the adjacent mutation action
still operates only on legacy context.

The audit decision is therefore **DEFERRED / BLOCKED FOR READ CUTOVER**:

- do not switch `SpjMonitoringList` membership yet;
- do not normalize legacy fiscal-year data to make Monitoring mutations appear
  compatible;
- do not use read compatibility as mutation authorization;
- Checklist/Persiapan mutation-adjacent navigation remains legacy-authoritative;
- Bulk Final, numbering, and fiscal-period close/reopen remain fully legacy;
- revisit Monitoring only after an explicit effective-context write-through or
  mutation-authorization strategy has runtime evidence.

This is an intentional safety boundary, not an unresolved read-parity defect.

### D4 step 11A — transitional mutation authorization for Checklist/READY

Step 10 completed the currently safe read-only cutovers. The first write-path
transition is intentionally limited to the smallest reversible lifecycle mutation:
`SpjPackage.status: DRAFT -> READY`.

`SpjV2MutationContextService` is the mutation authorization boundary. It does
not make V2 the write owner and it does not rewrite legacy context.

Rules:

1. a legacy-aligned Paket is authorized exactly as before;
2. a stale legacy fiscal year is eligible only when `SPJ_V2_READ_PATH=v2`;
3. Step 4 package membership must resolve one canonical source and the exact
   package id, legacy transaction id, canonical transaction id, and provenance
   bridge must all agree;
4. fund source must equal the active context;
5. `SOURCE_MISSING` or unresolved source reconciliation blocks compatibility
   mutation;
6. source-owned facts used by READY validation — proof number, transaction date,
   source description, activity/account code, recipient, gross/tax/net, and tax
   components — must still match current canonical raw facts;
7. only after those checks is legacy `transaction.fiscal_year_id` normalized to
   the active effective year **in memory only** for downstream validation;
8. no Transaction/Paket/document row is persisted by the authorization service.

`SpjPackageLifecycleUseCase::markReadyResult()` now uses this boundary before
validation. The only intended persistence is the existing DRAFT -> READY status
change plus the existing `PAKET_READY` operational audit row. The audit is
recorded against the active effective fiscal year. Numbering, FINAL, settlement,
package detail edits, cancel/replace, bulk-final, and fiscal-period mutations are
unchanged.

Checklist behavior follows the same boundary:

- an exact effective-context Paket can open Checklist;
- V2-compatible transaction links use `source_key` so Detail Transaksi stays
  inside the active effective context;
- the compatibility Paket page exposes only a **Buka Checklist** entry for DRAFT;
- the **Tandai siap diproses** POST appears only when the existing checklist and
  document requirements are fully satisfied;
- Isian Manual, numbering, and every other lifecycle mutation remain unavailable
  from the compatibility Paket surface.

Regression `V2DMutationContextReadyTest` is staged to prove:

- stale DRAFT + resolved V2 context can transition to READY;
- persisted legacy `transactions.fiscal_year_id` is unchanged;
- transaction source rows and documents are unchanged;
- `PAKET_READY` audit uses the effective fiscal year;
- config `legacy` blocks stale-context READY before validation;
- a wrong package bridge blocks READY;
- unresolved reconciliation and synthetic raw-source drift block READY before validation;
- duplicate-invoice validation remains scoped to effective provenance instead of stale legacy fiscal-year ids;
- a legacy-aligned context keeps the pre-existing READY behavior;
- compatibility UI exposes Checklist/READY only through the guarded entry point.

First runtime attempt on 2026-09-19 did **not** pass: **2 failed / 534
assertions / 40 deprecations**. Both failures were the same persistence-boundary
bug: transient `mutation_context_*` metadata had been attached through Eloquent
`setAttribute()`, so the subsequent Paket `save()` tried to write nonexistent
columns such as `mutation_context_path`.

The fix stores compatibility metadata only in the in-memory
`v2MutationContext` relation. Regression now explicitly asserts that no
`mutation_context_*` keys exist in Paket/Transaction SQL attributes and none
are dirty before READY persistence.

Clean rerun evidence on 2026-09-19 is now PASS. The focused
`V2DMutationContextReadyTest` passed with **8 tests / 90 assertions / 8
deprecations**. The full Step 11A gate passed with **42 tests / 556 assertions /
42 deprecations** across mutation-context READY, effective-context compatibility,
package membership/read context/read-only workspace/list cutover, lifecycle,
pre-numbering, and transaction-detail authorization regressions.

`vendor/bin/pint --dirty --format agent` PASS, `npm run build` PASS, and
`git diff --check` clean. D4 step 11A is therefore **RUNTIME PASS**.

Production configuration remains `SPJ_V2_READ_PATH=legacy` because Step 11B+
write-path gates are still pending.

### D4 step 11B — legacy-authoritative package overlay writes

After Step 11A proved the narrow DRAFT -> READY mutation boundary, Step 11B
extends effective-context authorization only to operator-owned Paket overlay
fields on editable packages.

The write owner deliberately remains the existing legacy Transaction and SPJ
relations. V2 is used to authorize identity/context/source parity; it is not a
second write target and no dual-write is introduced in this step.

`SpjV2MutationContextService::authorizePackageWrite()` is distinct from the
Step 11A `preparePackage()` path:

- it requires the same unique V2 source, exact Paket/provenance bridge, fund
  source, reconciliation, and live canonical source-fact checks;
- it attaches only transient `v2MutationContext` relation metadata;
- it **does not normalize `transactions.fiscal_year_id`, even in memory**;
- therefore subsequent legacy Transaction/child-relation `save()` operations
  cannot accidentally persist an effective fiscal year into the legacy row.

The initial write consumers are deliberately limited to:

1. `UpdateSpjPackageDetailsUseCase` for DRAFT/READY operator overlay fields and
   category-specific legacy relations;
2. `SpjPackageCategoryUseCase` for category changes, including the existing
   READY -> DRAFT revalidation rule.

Audit rows for these effective-context writes use the active effective fiscal
year, while the legacy transaction keeps its original fiscal-year id.

A dedicated `spj.package-compat-edit` surface is used instead of exposing the
full Paket workspace. It reuses the canonical Paket form partials but does not
render the Penomoran tab or lifecycle controls. The compatibility editor:

- is available only for `SpjPackage::isEditable()` (DRAFT/READY);
- writes through the existing `spj.update` endpoint/use cases;
- keeps ARKAS/BKU source facts and tax values readonly;
- hides maintenance material/labor linkage mutation because that endpoint has
  not yet received effective-context authorization;
- skips normal JS refresh of validation/documents/numbering panels after category
  switch because those panels are intentionally absent;
- leaves Preview/Download on the separate read-compatible surface.

NUMBERED effective-context Paket remain blocked from normal Paket-overlay writes
in Step 11B. The lifecycle-specific follow-up gate now permits only the
historical `payment_description` narrative correction after the same exact
effective-context/source-parity checks; category, payment, vendor, detail
relations, and all other overlay writes remain blocked. FINAL/CANCELLED remain
locked.

`V2DPackageOverlayWriteCutoverTest` is staged to prove:

- stale DRAFT Paket can save operator overlay fields after V2 authorization;
- persisted legacy `fiscal_year_id` remains unchanged;
- source-owned facts, Paket status, and documents remain unchanged by a normal
  DRAFT overlay save;
- `PERBARUI_ISIAN` audit uses the effective fiscal year;
- READY category change still demotes to DRAFT and leaves legacy context intact;
- config rollback to `legacy` immediately blocks stale-context overlay writes;
- wrong Paket/V2 bridge fails closed without an audit mutation;
- unresolved reconciliation and `SOURCE_MISSING` fail closed without an audit
  mutation;
- synthetic raw-source financial drift fails closed before any overlay write;
- NUMBERED effective-context Paket remain locked for both normal overlay save and
  category mutation;
- the dedicated editor keeps legacy fiscal year in memory and does not expose
  numbering/bulk-final controls or maintenance-link mutation.

Negative-path hardening was added in commits `3aa331a` and `eb96214`.
Runtime evidence for the Step 11B baseline (before the Step 11C extension) on
branch `arkas-raw-mirror` is PASS: the focused
`V2DPackageOverlayWriteCutoverTest` passed with **9 tests / 109 assertions /
9 deprecations**, and the combined Step 11A/read-context/package
manual/category/transaction-boundary/authorization/NUMBERED regression passed
with **59 tests / 644 assertions / 59 deprecations**. `vendor/bin/pint
--dirty --format agent`, `php artisan view:cache --no-interaction`,
`npm run build`, and `git diff --check` also passed.

This gate remains deliberately limited to DRAFT/READY package overlay writes.
Numbering, FINAL, settlement, bulk-final, and period-close remain outside the
Step 11B scope and were not opened.

### D4 step 11C — NUMBERED narrative correction compatibility

The first lifecycle-specific follow-up is deliberately narrower than normal
Paket overlay editing. A stale effective-context Paket in `NUMBERED` may now
correct only `payment_description`; the existing exact bridge, source-fact,
fund-source, reconciliation, and V2 selector checks remain mandatory. The
operation records its audit in the active effective fiscal year and does not
change status, number, documents, category, payment fields, or legacy fiscal
year. `FINAL`, numbering, settlement, bulk-final, and period-close remain
closed.

Runtime evidence on 2026-09-19: focused NUMBERED compatibility regression
passed **3 tests / 32 assertions**; related lifecycle/category/READY regression
passed **16 tests / 129 assertions**. Pint, Blade cache, frontend build, and
`git diff --check` passed. The stale Detail Transaksi route now has its own
effective-context resolver and fail-closed regression for `item_description`.

The resolver requires exact effective-context Paket membership,
provenance/package bridge, fund source, reconciliation/source status, source
facts, and every transaction-item source fact to match. Item overlays remain
legacy-authoritative; no V2 dual-write or fiscal-year rewrite is introduced.
The Livewire save action records the audit in the active effective fiscal year.
`NUMBERED` status and document number remain unchanged; `FINAL`, source drift,
item drift, wrong bridge, and missing/unsafe context remain blocked.

Focused + related regression passed **25 tests / 193 assertions / 25
deprecations**. Pint, Blade cache, frontend build, and `git diff --check`
passed.

### D4 numbering lifecycle audit — BLOCKED / DEFERRED

The next audit does **not** open effective-context numbering. Existing legacy
numbering/lifecycle regression remains green, but it is not evidence for the
stale effective-context path. `SpjSingleNumberingUseCase`,
`SpjNumberingGateService`, `SpjNumberingOrderService`, and
`SpjQuarterNumberingUseCase` still scope reads through legacy
`transactions.fiscal_year_id`; `SpjDocumentNumberService` also derives the
sequence and audit fiscal year from that legacy value. The effective
provenance/package bridge, item/source parity, and package/document parity are
not enforced as a numbering authorization boundary.

The quarter path additionally selects packages through the legacy context and
can leave numbers issued before a later package failure because the outer run
is not one atomic transaction. Collision/idempotency checks are only evidenced
for the legacy numbering domain; no effective-context evidence proves sequence
isolation, duplicate protection, complete registry identities, period
constraints, or effective-fiscal-year audit attribution. A focused regression
now proves stale effective-context single numbering remains closed: **1 test /
9 assertions**. No production numbering boundary was opened. FINAL, settlement,
bulk-final, and period-close/open mutation remain closed.

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

### Effective numbering operator path — single PASS, batch BLOCKED

Evidence 2026-09-19: the existing single-numbering action now routes selector
V2 directly to `SpjV2NumberingIssuanceService`; selector legacy remains on the
legacy use case and there is no V2-to-legacy fallback. The effective package
workspace exposes the action only after server-side preflight and package
validation pass. Repeated operator submission resumes the completed V2
reservation without a new sequence or audit. Effective batch/quarter UI and
all other mutation-heavy lifecycle surfaces remain closed. Browser visual QA
and repository-wide canonical gate for this UI change are RVR.

Canonical gate follow-up evidence for `e8eb73d` (2026-09-19): browser
visual/runtime QA was **RVR / DEFERRED** and was not run. Composer validation,
platform checks, Pint, Blade cache, frontend build, and `git diff --check`
passed. Full `php artisan test --compact` failed with **11 failures, 17
passes, 7,435 assertions, 645 deprecations**, duration **885.60s**. Failures
were six legacy `SpjSixCategoryE2eTest` cases, plus
`LivewireMutationAuthorizationTest`, `TransactionsTableLivewireTest`,
`SpjProgramHierarchyPlaceholderTest`, and two `V2BIsolatedSchemaTest` cases
without explicit isolated-clone environment. Focused V2-D/read-path/numbering
regression passed **31 tests / 304 assertions / 31 deprecations**. No canonical
green promotion is claimed; effective batch/quarter UI audit is deferred and
remains blocked.

### Canonical gate promotion after blocker repair — 2026-09-19

The earlier failure record above is superseded by the serial rerun from HEAD
`3e872fb`: **17 tests passed, 7,973 assertions, 0 failures, 656 deprecated
notices, 955.64s**. Composer validation, platform checks, Pint, Blade cache,
frontend build, and `git diff --check` passed. This promotes the repository
canonical code gate to **PASS** for this checkpoint. Browser visual/runtime QA
remains **RVR / DEFERRED**; effective batch/quarter UI remains **BLOCKED**.

### Transaksi and Detail Transaksi read-path audit

Focused route and workspace regression on 2026-09-19 found and fixed a
pagination-only lazy-loading failure in `TransactionsTable`: source parent,
package, and item raw-mirror relations are now explicitly loaded before
statistics and paginator rendering. The fix is read-only and does not rewrite
legacy fiscal year or alter mutation/lifecycle boundaries. Transaksi passed
**8 tests / 44 assertions / 8 deprecations**; Detail Transaksi passed **8
tests / 29 assertions / 8 deprecations**; related V2-D/read/mutation/package/
workspace coverage passed (**805 assertions / 63 deprecations**). Browser QA
remains **RVR / DEFERRED**; the repository-wide canonical gate remains **FAIL**
from the previously recorded unrelated blockers. Effective batch/quarter UI,
FINAL, settlement, bulk-final, and period-close mutation remain blocked.

### Effective sequence reservation — PASS; issuance backend PASS

`SpjV2NumberingSequenceService` mempertahankan scope executable existing:
`effective fiscal year + fund source + document type + period_key`. Reservation
memakai counter transaction lock, unique intent/package identity, unique
context/candidate sequence, retry idempotency, dan completion ownership check.
Focused sequence evidence **3 tests / 96 assertions / 3 deprecations**;
related V2-D dan legacy numbering/lifecycle evidence **68 tests / 633
assertions / 68 deprecations**. Reservation/completion tidak menulis nomor ke
dokumen, tidak mengubah status Paket, dan tidak membuat numbering-issued audit.
Actual effective-context numbering issuance backend **PASS**; single operator
UI is now PASS after the focused operator regression, while effective
batch/quarter UI remains BLOCKED.

### Effective numbering authorization boundary — PASS; issuance backend PASS

`SpjV2NumberingAuthorizationService` menambahkan preflight authorization
read-only yang fail-closed untuk effective membership, exact Package-V2
provenance, fund/source and canonical/item parity, document relation, READY
lifecycle, dan effective period proof. Focused authorization runtime evidence:
**2 tests / 72 assertions / 2 deprecations**. Boundary ini tidak menerbitkan nomor, menulis
audit, mengubah status Paket, atau menulis ulang `transactions.fiscal_year_id`.

Effective year/quarter resolution kini **PASS** melalui
`SpjV2EffectiveNumberingPeriodResolver`: effective year berasal dari
canonical `spj_transactions.fiscal_year_id` yang diverifikasi terhadap
`FiscalYear.year`; quarter berasal dari canonical transaction date; open
period state wajib terbukti; legacy fiscal year hanya diagnostic. Focused
resolver + authorization evidence adalah **2 tests / 72 assertions / 2
deprecations**. Effective-context numbering issuance backend **PASS** setelah
gate sequence, collision/idempotency, atomic rollback, dan effective audit
trail. Single operator path PASS pada focused regression; batch/quarter UI
tetap BLOCKED.
### Effective batch/quarter operator UI — PASS for SPJ Main only (2026-09-19)

The audited action is `POST /spj/penomoran-triwulan`, still owned by
`SpjController@assignQuarterNumbers`. Under the V2 selector it now delegates
to `SpjV2NumberingBatchService` after read-only candidate discovery and
preflight. The legacy selector remains on `SpjQuarterNumberingUseCase` and is
not used as a fallback from V2.

The promoted surface exposes only the `SPJ` Main document domain. It sends the
whole discovered candidate set to the atomic service, so a blocked/poison
member cannot be filtered out to create partial success. The service enforces
all-or-nothing mutation, deterministic ordering, collision safety, idempotent
retry, exactly-once batch audit, and canonical redirect reload. No FINAL,
settlement, bulk-final, period close/open, or browser QA surface was opened.

Focused evidence: **11 tests / 121 assertions / 11 deprecations PASS** from
the batch UI contract and V2 numbering suites.

V2-D read-path source dependency kini mengikuti explicit ARKAS mirror manifest.
Tenant raw facts tetap dibaca dari school DB; reference candidates yang belum
terbukti global tidak dipromosikan ke central. Status mirror schema tetap
PARTIAL FREEZE sampai two-tenant parity audit selesai.

### Canonical SQLite lock-stability verification - 2026-09-19

The clean `598b74a` head passed the complete repository PHPUnit gate with
**17 tests / 7,979 assertions / 0 failures / 657 deprecated notices** in
**1,232.55s**. The complete Feature suite also passed with **5,135
assertions** and **593 deprecated notices**. Three serial authorization-suite
runs passed without a `database is locked` exception. This evidence does not
claim browser/runtime verification; browser QA remains RVR/DEFERRED.
### Reference resolver checkpoint after schema partial freeze

Reference promotion sekarang memiliki boundary isolated `ArkasReferenceResolver`
dengan mode `LEGACY_RAW`, `CENTRAL_COMPAT`, dan `CENTRAL_ONLY`. `CENTRAL_COMPAT`
melakukan normalized shadow comparison dan fail-closed ketika central result
berbeda; resolver tidak melakukan silent fallback.

Belum ada consumer yang di-switch. `ArkasReferenceController`,
`RkasBudgetController`, `RkasBudgetFilter`, dan `SpjV2CanonicalReadService`
tetap membaca tenant raw/canonical path existing. Production read cutover
**NOT READY** karena invalid `ref_acuan_barang` source row, semantic variant
`ref_kode`, dan consumer-wide parity evidence masih tersisa.
### Updated blocker state

Invalid `ref_acuan_barang` dan flat semantic conflict `ref_kode` sudah memiliki
explicit quarantine/variant contracts dan fail-closed regression. Namun
`ArkasReferenceController`, `RkasBudgetController`, `RkasBudgetFilter`,
`ArkasDomainAdapter`, dan `SpjV2CanonicalReadService` belum membaca melalui
central resolver. Consumer shadow matrix belum seluruhnya PASS, sehingga mode
production tetap `LEGACY_RAW` dan read cutover **NOT READY**.
Full-dump `ref_kode` meng-quarantine duplicate source anomaly pada context yang
sama. Consumer shadow untuk context tersebut tetap `BLOCKED_DATA_CONTRACT`; tidak
ada read authority yang diubah.

| Consumer | Current authority | Required context | Candidate central path | Status |
|---|---|---|---|---|
| `ArkasReferenceController` | tenant raw mirror | fiscal year; account/catalog context | resolver for `ref_rekening`/`ref_acuan_barang` | `BLOCKED_DATA_CONTRACT` |
| `RkasBudgetController` | staged/raw `ref_kode` plus activity projection | year, fund, education, tenant | code variant + applicability resolver | `BLOCKED_DATA_CONTRACT` |
| `RkasBudgetFilter` | tenant raw `ref_kode` | year, fund, tenant | code applicability resolver | `NOT_TESTED` |
| `ArkasDomainAdapter` | tenant snapshot/import rows | fiscal year and source mapping | central lookup adapter only for confirmed/versioned refs | `BLOCKED_CONSUMER_ASSUMPTION` |
| `SpjV2CanonicalReadService` | tenant raw facts with reference IDs | source tenant, year, fund | compatibility resolver for lookup labels only | `BLOCKED_CONTEXT` |

Critical consumer parity PASS count: **0/5** at this checkpoint. The matrix is
explicit rather than inferred; `CENTRAL_COMPAT` remains a rehearsal mode.

### Production cutover checkpoint — 2026-09-20

The stale matrix above is superseded by the completed request-level gate:
all five consumers pass semantic parity through the shared resolver boundary.
Production reference reads are now selected centrally as `CENTRAL_COMPAT`.
`LEGACY_RAW` remains an explicit rollback selector and `CENTRAL_ONLY` remains
opt-in. Resolver mismatch, missing context, unsupported context, and the
quarantined `ref_kode` context fail closed; no silent raw fallback is allowed.
Raw mirror tables remain available for compatibility and rollback.

### Effective FINAL mutation checkpoint — 2026-09-20

The V2 read-path boundary now has a matching FINAL mutation authority through
`SpjV2FinalizationService`. The operator FINAL action delegates to it only for
an explicit, uniquely resolved V2 selector. The service locks package,
canonical context, and period rows; validates membership, bridge/provenance,
fund and source/item parity, numbering/document completeness, and effective
period eligibility; then writes snapshots and `FINALISASI_PAKET_V2` atomically
with a verified post-condition. Missing or invalid audit storage rolls back the
entire mutation. Legacy FINAL remains unchanged under the legacy selector.

### Effective settlement mutation checkpoint — 2026-09-20

After effective FINAL, settlement is authorized only through
`SpjV2SettlementService` when the requested read path is explicitly V2. The
service resolves the same effective membership and provenance bridge, validates
canonical source and gross/payment parity, locks the effective period, and
writes persistent `spj_v2_settlements` state plus `SETTLEMENT_V2` audit
atomically. The package remains FINAL on failure; retries return the existing
settlement without duplicate state or audit. Existing staged payment and goods
receipt actions remain legacy for editable packages, while a FINAL package
invokes the V2 settlement authority. Bulk-final and period mutation remain
outside this gate.

### Effective bulk FINAL mutation checkpoint — 2026-09-20

The administrator bulk-final action delegates to
`SpjV2BulkFinalizationService` only when the requested path is explicitly V2.
The service selects candidates by effective fund and quarter, applies the
authoritative package ordering, preflights all candidates, locks them, and
invokes the effective single-package FINAL authority atomically. The batch
`FINALISASI_BATCH_V2` audit is written after verified package post-conditions;
retry returns the existing batch audit without duplicate lifecycle events.
Failure of any member leaves every package unchanged. Period close/open,
destructive cleanup, and browser QA remain outside the gate.
