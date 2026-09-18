# V2-A2 — Tenant Discovery & Source Identity Preflight

Status: **COMPLETE / READ-ONLY PREFLIGHT**
Baseline: `c4c7ce5bfa4c7d5e280fe6876f4a22c79b86aaf3`
Branch: `arkas-raw-mirror`
Date: **2026-09-19**

Tidak ada tenant asli yang dimutasi. Tidak ada migration, repair, rename, move,
restore, atau cleanup legacy yang dijalankan.

## 1. Central registry and locator

Central database hanya memiliki satu sekolah dan satu registry tenant:

| Registry | Evidence |
|---|---|
| `schools` | `id=1`, NPSN `10260756`, SMP Negeri 2 Ranto Baek |
| `school_databases` | `D:\lrvProject\spj-bosp-web-raw\storage\app\school-databases\10260786\spj.sqlite`, `READY` |
| `school_backups` | 0 row |
| `arkas_sources` | 1 row untuk `school_id=1`, source database `D:\backupdata\datasmp.db` |
| `users` | 1 ADMIN, `school_id=1` |

`arkas_sources.last_identity` juga menyatakan NPSN `10260756`.

`SchoolDatabaseManager` membentuk managed path dari
`config('spj.data_path')/school-databases/{sanitized NPSN}/spj.sqlite`, tetapi
runtime memakai `school_databases.database_path` dan hanya mengoreksinya bila
managed path tersebut sudah ada. Migration tooling V2 wajib mengikuti:

```text
central schools.npsn → school_databases.database_path → exact existing SQLite path
```

Ia tidak boleh menebak physical path hanya dari NPSN.

## 2. Physical tenant and backup inventory

Seluruh kandidat berikut valid secara SQLite (`header=true`, `integrity=ok`,
foreign-key violations `0`):

| Candidate | Size | Migrations | Transactions/items/packages | Identity/evidence | Classification |
|---|---:|---:|---:|---|---|
| `school-databases/10260756/spj.sqlite` | 10,219,520 | 53 | 170 / 407 / 67 | internal NPSN `10260756` | external tenant copy |
| `school-databases/10208183/spj.sqlite` | 4,251,648 | 53 | 46 / 90 / 46 | internal NPSN `10208183` | **tenant kedua, valid historical mapping** |
| `school-databases/10208246/spj.sqlite` | 3,678,208 | 53 | 52 / 113 / 1 | no internal NPSN | orphan/unknown |
| `school-databases-raw/10208183/spj.sqlite` | 4,218,880 | 50 | 46 / 90 / 46 | internal NPSN `10208183` | historical raw variant |
| `school-databases-raw/10208246/spj.sqlite` | 1,056,768 | 50 | 52 / 113 / 0 | no internal NPSN | raw/orphan variant |
| `school-databases-raw/10260756/spj.sqlite` | 10,219,520 | 50 | 170 / 407 / 67 | internal NPSN `10260756` | historical raw variant |
| `school-databases-raw/10269756/spj.sqlite` | 458,752 | 22 | 0 / 0 / 0 | no internal NPSN | stale/unknown |
| `school-databases-raw/1060756/spj.sqlite` | 471,040 | 22 | 0 / 0 / 0 | no internal NPSN | stale/unknown |
| `storage/app/school-databases/10260786/spj.sqlite` | 84,910,080 | 54 | 291 / 699 / 67 | raw mirror 56 tables / 91,070 rows | V2-A audited tenant |

Filesystem backups found under `backups/10208183` and `backups/10260756`, plus
historical backup artifacts in the clean checkout. Readable SQLite backups were
valid; central `school_backups` has no rows. No backup was restored or changed.

```text
TENANT_2_NOT_DISCOVERABLE = FALSE
tenant kedua              = 10208183
central registry entry    = BELUM ADA
physical path             = D:\lrvProject\spj-bosp-data\school-databases\10208183\spj.sqlite
```

## 3. NPSN/path mismatch

```text
central School.npsn       = 10260756
central database path      = ...\10260786\spj.sqlite
external managed-looking  = D:\lrvProject\spj-bosp-data\...\10260756\spj.sqlite
ARKAS identity             = 10260756
```

Classification: **DATABASE_REGISTRY_MISMATCH**. Ini bukan folder naming bug yang
boleh diperbaiki otomatis; registry path dan external copy harus direkonsiliasi
secara eksplisit sebelum migration tooling memilih salah satunya.

## 4. Legacy → full raw mirror mapping (V2-A tenant)

Audited database: `storage/app/school-databases/10260786/spj.sqlite`. Boundary:
`source_id=1`, `source_table=kas_umum`, mirror `ACTIVE`, 2,015 raw rows.
Matching menggunakan `transaction_items.source_item_id` ke raw payload
`id_kas_umum`; `NO_BUKTI` bukan identity.

| Classification | Count |
|---|---:|
| EXACT | 269 |
| DETERMINISTIC | 22 |
| PARTIAL | 0 |
| SOURCE_MISSING | 0 |
| AMBIGUOUS | 0 |
| LEGACY_ONLY | 0 |

All 699 item identities were found. Recomputed
`SHA256(sorted(ID_KAS_UMUM))` matches existing `transactions.source_key` for
269 rows and mismatches for 22 rows. The 22 are deterministic because all item
identities are present, but their old grouped key must not be rewritten silently.

Package-sensitive result:

| Package state | EXACT | DETERMINISTIC | Total |
|---|---:|---:|---:|
| DRAFT | 1 | 0 | 1 |
| NUMBERED | 44 | 22 | 66 |
| FINAL | 0 | 0 | 0 |
| no package | 224 | 0 | 224 |

There are 115 `spj_documents` with status `NUMBERED`. Tenant `10208183` is not
yet raw-mirror projected, so its mapping classification remains **PENDING**.

## 5. Raw mirror identity classification

The 56 tables were classified from recorded schema `primary_order` and current
row data:

| Identity type | Count | Critical relation safe? |
|---|---:|---|
| `PRIMARY_KEY` / single PK | 38 | yes |
| `COMPOSITE_PRIMARY_KEY` | 13 | yes |
| `DETERMINISTIC_FALLBACK` | 1 | yes after explicit proof |
| `UNSTABLE_FALLBACK` | 4 | no |

Single-PK tables: `anggaran`, `app`, `app_config`, `app_log`, `indikator`,
`instansi`, `instansi_pengguna`, `kas_umum`, `kas_umum_nota`, `kas_umum_temp`,
`manage_app`, `migrations`, `mst_sekolah`, `mst_wilayah`, `pengesahan`,
`pengguna`, `rapbs`, `rapbs_periode`, `ref_bku`, `ref_bulan`, `ref_indikator`,
`ref_jabatan`, `ref_jenis_instansi`, `ref_level_kode`, `ref_level_wilayah`,
`ref_negara`, `ref_pajak`, `ref_periode`, `ref_satuan`, `ref_sumber_dana`,
`ref_tahun_anggaran`, `rencana_kerja_anggaran`, `report_bku`, `role`, `token`,
`unsend_tracker`, `user_role`.

Composite-PK tables, with ordered components:

```text
aktivasi_bku: id_anggaran,id_periode
config_anggaran: sekolah_id,id_ref_sumber_dana
ptk: sekolah_id,ptk_id,tahun_ajaran_id
rapbs_ptk: id_rapbs,ptk_id
ref_acuan_barang: id_barang,tahun
ref_kode: id_ref_kode,tahun,sumber_dana_id,bentuk_pendidikan_id
ref_rekening: kode_rekening,tahun
ref_sumber_dana_bentuk_pendidikan: id_ref_sumber_dana,bentuk_pendidikan_id
ref_sumber_dana_sekolah: id_ref_sumber_dana,tahun
sekolah_history: sekolah_id,tahun
sekolah_penjab: id_penjab,tahun
status: ref_id,ref_type
tmp_unduh_data: api,table_name,tahun
```

The sole deterministic fallback observed is `kas_umum_nota_pajak` using
`ID_KAS_NOTA`. Unstable tables are `ref_rekening_transfer`,
`ref_rekening_transfer_temp`, `rpt_bku`, and `salur`. No critical V2 relation
may reference `UNSTABLE_FALLBACK`.

`arkas_raw_mirror_rows.id` is not canonical because refresh deletes/reinserts
rows. Composite primary-key JSON must preserve declared ordinal order, for example:

```json
{"id_ref_kode":"...","tahun":"...","sumber_dana_id":"...","bentuk_pendidikan_id":"..."}
```

## 6. Locked identity registry contract

V2-B may add this table; V2-A2 does not execute it:

```text
arkas_source_identity_registry
id, source_id, source_table, source_key, primary_key_json,
identity_type, current_raw_mirror_row_id, payload_hash, source_status,
first_seen_at, last_seen_at, created_at, updated_at
```

Unique boundary: `source_id + source_table + source_key`.

```text
first seen       → create identity
raw refresh      → update current row pointer/hash
row disappears   → SOURCE_MISSING, retain identity
row returns      → same identity becomes ACTIVE
```

The registry stores identity/lifecycle metadata only; it must not become a second
live ARKAS fact store. Critical relations reject `UNSTABLE_FALLBACK`.

## 7. Typed adapter strategy

Keep the generic raw mirror as the source layer. Add only query adapters or SQL
views for hot paths:

```text
ArkasKasUmumSource
ArkasRapbsSource
ArkasRapbsPeriodSource
ArkasTaxSource
ArkasBudgetSource
```

Do not create 56 typed live copies.

## 8. Additive V2-B schema proposal

### `spj_transactions`

```text
id, fiscal_year_id, fund_source_id, source_id,
source_membership_hash, source_status, requires_reconciliation,
source_missing_since, created_at, updated_at
```

No source date, description, account, recipient, gross, tax, or net columns.

### `spj_transaction_sources`

```text
id, spj_transaction_id, arkas_source_identity_id, sort_order,
created_at, updated_at
```

Unique: `spj_transaction_id + arkas_source_identity_id`.

### Overlay tables

Transaction overlay:

```text
id, spj_transaction_id, spj_category, payment_description,
payment_method, payment_reference, receipt_recipient_name,
operator_metadata, created_at, updated_at
```

Item overlay:

```text
id, spj_transaction_source_id, item_description, operator_metadata,
created_at, updated_at
```

Source description, quantity, unit, price, and amount remain raw-source reads.

### Package transition

Prefer an additive bridge:

```text
legacy_transaction_v2_map
legacy_transaction_id unique
spj_transaction_id unique
mapping_status, mapping_reason, created_at, updated_at
```

`spj_packages.transaction_id` remains authoritative during transition. Package
ID, document number, status, snapshot, NUMBERED, and FINAL remain unchanged;
legacy transaction retirement is deferred to V2-G.

## 9. V2-B gate and conclusion

V2-B real-tenant execution remains blocked because tenant `10208183` has no
central school/source registry or full raw mirror audit, the 22 deterministic
NUMBERED mappings require additive bridge evidence, and central
`school_backups` does not represent the filesystem backups.

```text
[x] discoverable tenant files inventoried
[x] tenant 10208183 discovered
[x] central/path mismatch explained
[x] V2-A mapping and package-sensitive mapping measured
[x] 56-table identity classification completed
[x] stable registry contract and additive V2-B proposal locked
[x] original tenants untouched
[ ] V2-B real-tenant execution
```
