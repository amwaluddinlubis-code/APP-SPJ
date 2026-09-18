# V2-C — Full Legacy Migration Dry-Run and Two-Tenant Isolated Rehearsal

Terakhir diverifikasi: **2026-09-19** pada branch `arkas-raw-mirror`.

V2-C membuktikan migrasi additive terhadap clone terisolasi, bukan terhadap tenant
produksi. Tenant asli, central registry, dan database ARKAS tidak menjadi target
mutasi. Rehearsal ini belum merupakan production cutover dan belum mengaktifkan
read-path V2.

## Implementasi

- `SpjV2LegacyMigrationService` memegang classification, source identity membership,
  operator overlay, item overlay, legacy bridge, package link additive, verification,
  dan report.
- `spj:v2-migrate` hanya melakukan orchestration dan menerima `--dry-run`,
  `--execute`, atau `--verify`.
- Migration `2026_09_19_000100_add_v2_c_transition_columns.php` hanya menambah
  `spj_packages.spj_transaction_id` dan provenance fields pada bridge.
- Guard menolak path tenant asli, database utama, dan path ARKAS; mode orphan
  `SOURCE_UNAVAILABLE_DRY_RUN` tidak membuat mapping tebakan.
- V2 rehearsal migrations berada di `database/migrations/v2-rehearsal`, terpisah
  dari `database/migrations/school`; migrasi tenant reguler tidak menjalankan
  schema rehearsal.

## Clone dan evidence

| Tenant | Clone | Hash clone sebelum | Hasil |
|---|---|---|---|
| 10260756 | `storage/app/v2-c-rehearsal/tenant-10260756-v2c.sqlite` | `E2B2AFAE4369374CA1F8215B1D26785117D9A131CF784EDA9614243FFA57A27C` | execute + idempotent verify |
| 10208183 | `storage/app/v2-c-rehearsal/tenant-10208183-v2c.sqlite` | `2AC46F6F96B4CAF266F1E27A8A1BB8025067233FB05ABA03C5FDA15060D54A8A` | source-unavailable dry-run |

Tenant A menghasilkan 291 V2 transactions, 699 source links, 291 transaction
overlays, 245 item overlays, 291 legacy maps, dan 67 package links. Classification
source-resolution adalah EXACT 269 dan DETERMINISTIC 22; canonical context
adalah 187 `ACTIVE_CANONICAL` dan 104 `LEGACY_DUPLICATE`. Seluruh 22 deterministic mempertahankan
`transactions.source_key` lama dan tidak mengubah Paket NUMBERED.

Sebanyak 104 row tambahan berasal dari fiscal-year record legacy id `3` yang
menunjuk fund source `1` tetapi transaction date 2025 dan source membership-nya
duplikat persis dengan context 2025/fund 1 pada fiscal-year id `4`. Reconciliation
ini read-only: row tidak dihapus dan tidak dipromosikan ke canonical production
read-path.

Database `10208183` adalah database legacy dari project sebelumnya, bukan Tenant B
current-project. Ia dipakai sebagai external/orphan negative fixture tanpa raw
mirror yang dapat dibuktikan. Semua 46 transaction diklasifikasikan
`SOURCE_MISSING`; tidak ada V2 transaction, source link, overlay, atau package
link yang dibuat.

Hash source tenant asli sebelum/sesudah rehearsal tetap sama:

- Tenant A original: `E2B2AFAE4369374CA1F8215B1D26785117D9A131CF784EDA9614243FFA57A27C`;
- Tenant B original: `2AC46F6F96B4CAF266F1E27A8A1BB8025067233FB05ABA03C5FDA15060D54A8A`;
- ARKAS source `D:\backupdata\datasmp.db`:
  `6DA6D6ECDEF7CEDCA92B0B8FEA88819E321553602C306F52EE48DC41736CC1B0` pada
  report execute; hash ulang akhir tidak dapat dibuka karena file sedang dikunci
  proses ARKAS, sehingga status akhir source dicatat sebagai **read-only lock
  prevents second hash**, bukan diasumsikan PASS.

Hash clone Tenant A setelah rehearsal terakhir adalah
`7E92CF8BC3B12B3702324B7864799D9CB98DE046D08A17C5280EF530A9A582F7`; Tenant B
dry-run tetap `2AC46F6F96B4CAF266F1E27A8A1BB8025067233FB05ABA03C5FDA15060D54A8A`.

## Verification evidence

- Tenant A: `PRAGMA integrity_check = ok`, foreign-key violations `0`, seluruh
  orphan checks `0`.
- Adapter source validation: PASS; 699 links resolved, unresolved `0`, gross
  `686015000`, tax `33316674`, net `652698326`, formula valid.
- Package/document protected manifest tetap identik; 67 Paket dan 115 dokumen
  dipertahankan, termasuk 66 Paket NUMBERED dan 115 dokumen NUMBERED.
- Execute kedua tidak membuat duplicate V2 transaction, membership, overlay,
  item overlay, legacy map, atau package link.
- Synthetic FINAL package/document regression lulus; hanya relation V2 additive.
- Dry-run external/orphan fixture tidak mengubah hash clone.
- Test V2-B/V2-C: 5 test, 101 assertions, 5 deprecations.
- Importer tenant-boundary regression: 4 test, 80 assertions, 4 deprecations.

Canonical `spj:verify --strict-style --skip-build` masih harus dijalankan pada
head final setelah dokumentasi selesai. V2-D belum dimulai: langkah berikutnya
adalah shadow read/comparison adapter dan review cutover, bukan production
migration. Source evidence dari fixture `10208183` bukan blocker tenant wajib.

## Report machine-readable

Report JSON berada di `storage/app/v2-c-rehearsal/reports/` dan tidak memuat nilai
personal. Report execute/dry-run mencatat identity, hash target/source bila dapat
dibaca, classification, counts, protected continuity, adapter validation, dan
errors.
