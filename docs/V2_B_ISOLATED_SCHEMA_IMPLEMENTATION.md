# V2-B — Isolated/Test Database Schema Implementation

Status: **ISOLATED TEST PASS / REAL TENANT EXECUTION BLOCKED**
Baseline: `a6a3d1c docs: clean V2-A2 report formatting`
Branch: `arkas-raw-mirror`

## Scope

V2-B diimplementasikan hanya pada clone isolated berikut:

```text
D:\lrvProject\spj-bosp-web-raw\storage\app\v2-b-isolated\tenant-10260756-v2b.sqlite
```

Clone dibuat dari database audited V2-A pada `storage/app/school-databases/10260786`.
Database sumber ARKAS yang dipakai untuk read-only hash adalah `D:\backupdata\datasmp.db`.
Tidak ada tenant asli, central registry produksi, atau database sumber ARKAS yang dimutasi.

## Implementasi

File baru:

- `app/Services/V2BIsolatedDatabaseGuard.php`
- `app/Services/V2BSourceIdentityRegistryService.php`
- `database/migrations/school/2026_09_19_000000_create_v2_b_overlay_schema.php`
- `tests/Feature/V2BIsolatedSchemaTest.php`

Migration additive membuat:

- `arkas_source_identity_registry`;
- `spj_transactions`;
- `spj_transaction_sources`;
- `spj_transaction_overlays`;
- `spj_item_overlays`;
- `legacy_transaction_v2_map`.

Tidak ada 56 typed live-copy table baru. `arkas_raw_mirror_rows.id` hanya pointer saat ini,
bukan identity canonical.

## Guard

Migration menolak target yang bukan file SQLite explicit dan isolated. Guard menolak seluruh
root database original, termasuk tenant 10208183, 10260756, dan path registry 10260786.
Manifest wajib menyamakan target path, NPSN, `source_id`, dan ARKAS source identity; source
path harus berbeda, ada, dan diberi tanda `source_read_only=true` serta `query_only=true`.
`UNSTABLE_FALLBACK` ditolak untuk critical relation. Serialisasi primary key mengikuti ordinal
yang diberikan caller dan tidak diurutkan alfabetis.

## Lifecycle dan bridge

Identity boundary adalah `source_id + source_table + source_key`. Registry mempertahankan
baris ketika source hilang, mengubah status ke `SOURCE_MISSING`, lalu mengaktifkannya kembali
dengan id yang sama ketika source kembali. Bridge V2-B bersifat additive; `transactions` dan
`spj_packages.transaction_id` tidak diganti.

## Verification evidence

Test:

```text
vendor/bin/phpunit tests/Feature/V2BIsolatedSchemaTest.php --do-not-cache-result
Tests: 2
Assertions: 74
Result: PASS
```

Hasil akhir clone setelah verification:

```text
migrations                         55
transactions                      291
transaction_items                 699
spj_packages                       67
spj_documents                     115
arkas_raw_mirror_tables            56
arkas_raw_mirror_rows          91,070
source identity registry            1 ACTIVE
spj_transactions                    2
spj_transaction_sources             1
transaction overlays                1
item overlays                       1
legacy bridge                       2 (EXACT=1, DETERMINISTIC=1)
PRAGMA integrity_check              ok
PRAGMA foreign_key_check            0 violations
```

Test juga membuktikan migration idempotent, duplicate identity ditolak, composite identity
mempertahankan urutan ordinal, identity `SOURCE_MISSING → ACTIVE` stabil, mapping
`DETERMINISTIC` masuk bridge tanpa rewrite `transactions.source_key`, overlay operator tetap
utuh, serta source ARKAS tidak berubah.

Pre/post manifest seluruh tabel existing selain `migrations` tidak berubah. Hash clone:

```text
pre  E2B2AFAE4369374CA1F8215B1D26785117D9A131CF784EDA9614243FFA57A27C
post AD2E9DD6772EE89D7592C2092107415F4B57A262282A13989BC98784F460C849
```

Perbedaan hash database berasal dari migration dan fixture verification pada clone, bukan
perubahan source tenant. Source ARKAS hash sebelum/sesudah tetap sama.

## Blocker real tenant

V2-B belum boleh dieksekusi pada tenant nyata. Masih diperlukan review lanjutan untuk:

1. tenant kedua dan mismatch `10260756` versus folder `10260786`;
2. pemetaan legacy seluruh transaction/package nyata;
3. finalisasi source identity registry terhadap full ARKAS mirror;
4. keputusan additive transition package untuk real tenant;
5. backup, approval, dan migration runbook production.
