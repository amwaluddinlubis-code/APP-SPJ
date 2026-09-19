# V2-D — Read-Path Cutover

Status: **D1 SHADOW PARITY FUNCTIONAL PASS / PRODUCTION CUTOVER BLOCKED**.

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

## D2 — Canonical read adapter (next)

Only after D1 runtime parity is PASS:

- introduce a canonical V2 read DTO/adapter for transaction list/detail;
- derive ARKAS-owned fields through `spj_transaction_sources ->
  arkas_source_identity_registry -> current_raw_mirror_row_id`;
- derive operator-owned transaction/item fields only from V2 overlay tables;
- preserve the active `Fiscal Year + Fund Source` boundary;
- keep production UI on the existing read path while shadow comparison remains
  enabled in tests/rehearsal.

## D3 — Paket/document bridge

Before any production cutover, map every relation consumed by Paket/document
flows. Existing `spj_packages.spj_transaction_id` is additive provenance from
V2-C and does not by itself authorize switching package lifecycle writes.
NUMBERED/FINAL state, document numbers, snapshots, and generated artifacts remain
protected.

## D4 — Controlled cutover

A production switch requires all of the following:

- V2-C3 semantic verify PASS;
- D1 shadow parity PASS on fresh real-data rehearsal;
- canonical read adapter regression PASS;
- Paket/document relation parity PASS;
- report/tax/period workflow parity PASS;
- authorization and active-context regression PASS;
- rollback strategy documented and tested;
- Pint, focused regression, `git diff --check`, and applicable CI evidence.

No production read path has been switched in D1.
