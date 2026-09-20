# ARKAS Mirror Schema Contract

Status: **PARTIAL FREEZE**
Manifest: `ArkasMirrorManifest::VERSION` (`2026-09-20.v3`)

## Tujuan

APP-SPJ tidak menggunakan generic importer mapping sebagai authority untuk raw
mirror. ARKAS tetap readonly. Bridge hanya menjadi adapter eksplisit untuk
source table yang tercantum di manifest dan tidak boleh membuat target table
secara dinamis.

Arsitektur target:

```text
ARKAS source database (read-only)
        |
        +-- ArkasCentralReferenceBridge -> central DB (hanya reference yang
        |                                   sudah terbukti global)
        |
        +-- ArkasTenantDataBridge -------> school DB (data sekolah/source)
        |
        +-- APP-SPJ canonical/overlay ----> school DB
```

## Boundary central dan tenant

`CENTRAL` hanya boleh dipakai bila parity lintas sekolah, key stability, dan
absence of school override sudah dibuktikan. `TENANT` berarti source fact
memiliki konteks sekolah, transaksi, fiscal year, fund source, pegawai, atau
provenance sekolah dan harus tetap di database sekolah.

Central promotion tetap deferred, tetapi ownership keenam tabel drift/hybrid
sekarang sudah eksplisit. `ref_rekening`, `ref_acuan_barang`, dan `ref_bku`
adalah `VERSIONED_GLOBAL_REFERENCE`; `ref_sumber_dana` dan `ref_kode` adalah
`HYBRID_CENTRAL_BASE_TENANT_EXTENSION`; `ref_sumber_dana_sekolah` adalah
`OPTIONAL_TENANT_REFERENCE`. Classification ini menetapkan source authority,
version dimensions, dan drift policy, tetapi tidak mengaktifkan central
cutover atau menghapus raw tenant snapshot.

## Manifest resmi

`app/Services/ArkasMirrorManifest.php` adalah allow-list version-controlled.
Tabel tenant berikut enabled untuk raw mirror:

| Source table | Classification | Bridge | Key strategy | Target |
|---|---|---|---|---|
| `aktivasi_bku`, `anggaran` | TENANT_DATA | `ArkasTenantDataBridge` | explicit | school |
| `kas_umum`, `kas_umum_nota` | TENANT_DATA | `ArkasTenantDataBridge` | primary key | school |
| `kas_umum_nota_pajak` | TENANT_DATA | `ArkasTenantDataBridge` | `id_kas_nota + ntpn` | school |
| `ptk`, `rapbs_ptk`, `salur` | TENANT_DATA | `ArkasTenantDataBridge` | explicit composite | school |
| `rapbs`, `rapbs_periode` | TENANT_DATA | `ArkasTenantDataBridge` | primary key | school |
| `sekolah_history`, `sekolah_penjab` | TENANT_DATA | `ArkasTenantDataBridge` | explicit composite | school |
| `mst_sekolah` | TENANT_DATA | `ArkasTenantDataBridge` | primary key | school |
| `pegawai` | TENANT_DATA | `ArkasTenantDataBridge` | optional source key | school |

Reference candidates remain disabled for central promotion: `ref_kode`,
`ref_level_kode`, `ref_periode`, `ref_rekening`, `ref_sumber_dana`,
`ref_acuan_barang`, and `ref_bku`. `ref_sumber_dana_sekolah` is enabled as an
optional tenant raw reference because its ownership is already tenant-scoped.

Tabel source lain yang tidak tercantum di manifest tidak diimpor. Enumerasi
`tables` hanya digunakan untuk menemukan apakah entry eksplisit tersedia dan
untuk menghitung skipped table; tidak ada mapping atau target dinamis yang
dihasilkan dari hasil enumerasi.

## Bridge contract

Setiap bridge harus mengetahui source tables dan scope target melalui code.
Manifest juga menyimpan availability (`REQUIRED`/`OPTIONAL`), key strategy,
key columns, required columns, dan school-scope strategy. Validator
`ArkasMirrorContractValidator` memeriksa table shape, kolom wajib, key,
duplicate identity, dan malformed rows sebelum metadata atau rows ditulis.
`kas_umum` wajib memiliki relasi `id_anggaran` ke `anggaran`; orphan relation
fail-closed.
Persistent authority checkpoint: migration `2026_09_20_120000_create_persistent_arkas_reference_authority` adds `central_reference_rows`, quarantine diagnostics, code variants/applicability, and tenant extensions. It is isolated from tenant raw databases and has not been applied as a live tenant mutation.

Full-dump audit menemukan satu context `ref_kode` yang masih tidak dapat
dibuktikan aman: tenant A, release `2026.09`, `id_kode=05.02.05.`, tahun 2025,
fund 1, jenjang 6 memiliki duplicate source anomaly. Kedua row di-quarantine
sebagai satu context; row lain tetap dapat dipromosikan. Contract context ini
tetap fail-closed sampai provenance tambahan menjelaskan row mana yang berlaku.

Raw mirror menyimpan capture/provenance readonly. Ia bukan canonical mutation
table. `spj_transactions`, overlays, Paket, dokumen, numbering, audit, dan
reconciliation tetap menjadi authority operasional APP-SPJ.

## Generic importer mapping

`ArkasGenericImportService`, `ArkasImportProfile`,
`ArkasImportConfigurationService`, `ArkasDomainAdapter`, dan controller
importer lama masih dipertahankan sementara sebagai compatibility shim untuk
data/profile historis. Mereka bukan authority raw mirror baru dan tidak boleh
menambah table ke manifest. Jalur berikutnya adalah:

1. freeze pembuatan mapping baru pada UI;
2. audit profile aktif dan migrasikan yang dibutuhkan ke bridge eksplisit;
3. pindahkan read-path ke manifest/bridge;
4. hapus shim setelah parity dan isolated rehearsal lulus.

Penghapusan sekarang akan memutus active importer flow dan belum aman tanpa
inventory profile tenant nyata.

## Schema drift dan failure policy

Bridge fail-closed bila required table/column/key berubah, duplicate composite
identity ditemukan, identity kosong, atau relasi tenant orphan. `pegawai`
adalah satu-satunya source optional pada manifest tahap ini; jika tidak ada,
mirror lanjut dengan diagnostic `optional_unavailable` tanpa membuat tabel
palsu. Unknown, empty, dan disabled table tidak boleh menjadi target baru.
Raw mirror mempertahankan snapshot valid sebelumnya bila refresh gagal.

## Existing schema disposition

`arkas_raw_mirror_tables` dan `arkas_raw_mirror_rows` tetap dipertahankan di
school DB sebagai transitional tenant raw capture. `arkas_import_profiles` dan
`arkas_import_rows` tetap compatibility-only. `arkas_rkas_items`,
`arkas_rkas_periods`, dan `arkas_bku_rows` tetap transitional domain tables
sampai read-path canonical dan source relation parity selesai.

Tidak ada drop/move/destructive migration pada fase ini. Central raw/reference
tables belum ditambahkan karena belum ada evidence parity lintas sekolah.

## Inventory implementation

| Area | Current component | Status |
|---|---|---|
| Bridge process | `ArkasBridgeClient`, `bridge/src/ARKASBridge/Program.cs` | KEEP, explicit read-only adapter |
| Raw capture | `ArkasRawMirrorService` | KEEP, now manifest-gated |
| Source discovery | `ArkasDatabaseExplorer` | KEEP, discovery only; not mapping authority |
| Full sync | `ArkasFullSynchronizationService` | KEEP, domain-specific orchestration |
| Legacy mapping | `ArkasGenericImportService`, `ArkasDomainAdapter` | DEPRECATE, compatibility shim |
| Mapping persistence | `ArkasImportProfile`, `ArkasImportConfigurationService` | DEPRECATE, migrate then remove |
| Staging | `ArkasStagingService` | KEEP temporarily for existing domain adapters |
| Reconciliation | `ArkasReconciliationService` | KEEP, no dynamic mapping expansion |
| Raw mirror schema | `create_arkas_raw_mirror_tables` | KEEP TENANT transitional |
| Central schema | no ARKAS mirror table yet | NOT READY; requires cross-school evidence |

## Isolated dump rehearsal

Dump `datasmp.db.sql` dibaca melalui adapter read-only ke SQLite in-memory dan
ditulis hanya ke school database in-memory. Rehearsal aktual menemukan 56
source tables, mengimpor 14 tabel tenant dengan 7.771 rows, dan melewati
`pegawai` sebagai optional source yang tidak tersedia. Dry-run tidak menulis
metadata/rows; real-write rehearsal kemudian berhasil dan rerun tidak
menghasilkan row atau table tambahan. Composite identity pajak dan seluruh
key composite yang dipakai dump tidak memiliki duplicate group.

Evidence ini adalah tenant rehearsal satu dump. Cross-school reference parity
terbaru dicatat pada bagian audit; central promotion tetap **DEFERRED** dan
schema status tetap **PARTIAL FREEZE**.

## Next migration phases

1. Inventory both real school sources read-only: table schema, keys, row counts,
   and cross-school equality for reference candidates.
2. Promote only proven global references into a central typed/reference schema.
3. Add tenant bridge contract tests and isolated-copy backfill/parity reports.
4. Switch reads V2-first, freeze legacy writes, then remove compatibility
   mapping only after package/document/numbering gates remain green.

FINAL lifecycle, settlement, bulk-final, period close/open, browser QA, and
unrelated lifecycle mutations remain outside this contract.

## Cross-school central-reference parity audit — 2026-09-20

Audit read-only memakai tiga dump sekolah berbeda:

| Label | Dump | NPSN | Sekolah | Tables |
|---|---|---:|---|---:|
| A | `D:\PC Data\Documents\datasmp.db.sql` | `10260756` | SMP Negeri 2 Ranto Baek | 56 |
| B | `D:\backupdata\arkas318.db.sql` | `10208183` | SD Negeri 318 Bangun Saroha | 56 |
| C | `D:\backupdata\arkas316.db.sql` | `10208246` | SD Negeri 316 Ranto Panjang | 56 |

Schema hashes untuk seluruh 56 tabel sama pada ketiga dump. Kesamaan schema
tidak dianggap sebagai parity isi; comparator mengurutkan row berdasarkan
stable key, menghitung rowset hash, duplicate key, common rows, differing
common rows, dan only-in-source rows.

`GLOBAL_REFERENCE_CONFIRMED`: `mst_wilayah` (7.818 rows),
`ref_level_wilayah` (5), `ref_negara` (1), `ref_jabatan` (2),
`ref_jenis_instansi` (3), `ref_satuan` (43), `ref_periode` (16),
`ref_level_kode` (3), dan `ref_indikator` (5). Semua memiliki zero differing
common rows, zero only-in-source rows, dan zero duplicate keys.

`UNUSED_EMPTY`: `ref_bulan`, `ref_pajak`, `ref_rekening_transfer`,
`ref_rekening_transfer_temp`, `ref_sumber_dana_bentuk_pendidikan`, dan
`ref_tahun_anggaran` kosong pada ketiga dump.

`GLOBAL_REFERENCE_VERSIONED` atau `HYBRID_NEEDS_SPLIT` — belum dipromosikan:

- `ref_sumber_dana`: A/B 10 rows, C 11; 10 common rows, 1 common row berbeda,
  1 row hanya di C;
- `ref_rekening`: 1.170 rows per dump, tetapi 9 common rows berbeda;
- `ref_acuan_barang`: A/C 68.761, B 68.763; 68.761 common rows, 3 berbeda,
  2 hanya di B;
- `ref_kode`: 5.349 / 5.406 / 5.521 rows; stable composite identity dan
  membership berbeda luas lintas dump.

### Final ownership contract for the six drifted references

| Table | Final classification | Canonical/natural identity | Version dimensions | Scope | Drift policy |
|---|---|---|---|---|---|
| `ref_sumber_dana` | `HYBRID_CENTRAL_BASE_TENANT_EXTENSION` | central candidate `kode`; source PK remains raw provenance | `arkas_release`, fiscal-year applicability | common base + tenant membership/local source | common semantic definitions only; tenant-only rows and conflicts never overwrite central |
| `ref_rekening` | `VERSIONED_GLOBAL_REFERENCE` | `kode_rekening` | `tahun`, `arkas_release` | central versioned catalogue | ignore volatile create/last-update drift; semantic conflict fails closed |
| `ref_acuan_barang` | `VERSIONED_GLOBAL_REFERENCE` | `id_barang` | `tahun`, `arkas_release` | central versioned catalogue | ignore timestamp-format drift; same key/version conflict fails closed |
| `ref_kode` | `HYBRID_CENTRAL_BASE_TENANT_EXTENSION` | base candidate `id_kode`; raw PK is not portable | `arkas_release`, `tahun`, `sumber_dana_id`, `bentuk_pendidikan_id` | central definitions only after semantic match + tenant applicability | membership remains tenant-scoped; source-specific semantic definitions never silently globalize |
| `ref_bku` | `VERSIONED_GLOBAL_REFERENCE` | `id_ref_bku` | `arkas_release` | central versioned lookup | ignore volatile last-update drift; conflicting `bku`/`kode_bku` fails closed |
| `ref_sumber_dana_sekolah` | `OPTIONAL_TENANT_REFERENCE` | `(id_ref_sumber_dana, tahun)` | `tahun` | tenant extension | empty is valid; no membership row is synthesized or promoted centrally |

`arkas_release` adalah source-release context yang diberikan bridge/import run,
bukan versi opaque yang ditebak dari row. `tahun` adalah fiscal-year dimension;
`sumber_dana_id` dan `bentuk_pendidikan_id` adalah applicability dimensions
untuk `ref_kode`. A versioned identity terdiri dari natural key plus seluruh
declared dimensions. Natural key yang sama boleh hidup pada release/tahun yang
berbeda; natural key dan version dimensions yang sama dengan semantic conflict
wajib fail-closed.

Central base dan tenant extension adalah konsep terpisah: central hanya boleh
menyimpan definisi yang terbukti sama, sedangkan tenant menyimpan applicability,
local enablement, overrides, dan source-only rows. `ref_sumber_dana` memakai
`ref_sumber_dana_sekolah` sebagai membership extension; `ref_kode` memakai
applicability dimensions sebagai extension contract. Unsupported release atau
unknown semantic row tidak boleh dipromosikan.

### Consumer compatibility audit

- `RkasBudgetController` dan `RkasBudgetFilter` membaca `ref_kode` melalui raw
  payload dan memfilter tahun/sumber dana aktif; consumer tetap tenant-scoped.
- `ArkasReferenceController` membaca `ref_rekening`, `ref_acuan_barang`, dan
  `ref_kode` dari snapshot raw tenant; tidak ada asumsi ID central portable.
- `SpjV2CanonicalReadService` menelusuri
  `rapbs_periode -> rapbs -> ref_kode`, sehingga composite context boundary
  harus tetap aktif sampai adapter hybrid tersedia.
- `SpjFreshProjectionService` memakai `id_ref_bku` dari payload `kas_umum`
  untuk klasifikasi item/pajak; itu bukan pemindahan ownership lookup ke
  transaksi tenant.
- `ref_sumber_dana_sekolah` belum memiliki consumer central; mirror optional
  disiapkan untuk membership-aware resolution di masa depan.

Comparator `ArkasReferenceParityService` dan rehearsal test membuktikan
comparison serta import order `A→B→C` dan `C→A→B` deterministic untuk sembilan
tabel confirmed. Manifest regression mengunci classification, natural key,
version dimensions, bridge, availability, dan central-disabled status keenam
tabel drift/hybrid. Status central promotion tetap **DEFERRED**: belum ada
central migration, central write-path, cutover read, atau penghapusan raw
tenant copy.
## Central promotion rehearsal and read resolver checkpoint

Checkpoint `d267839` menambahkan kontrak isolated untuk promotion central tanpa
membuat migration atau menulis database tenant:

- `ArkasReferenceCentralSchema` mendefinisikan target table, natural key,
  version dimension, dan semantic columns untuk 9 confirmed reference,
  `ref_rekening`, `ref_acuan_barang`, `ref_bku`, `ref_sumber_dana`, dan
  `ref_kode`.
- `ArkasReferencePromotionService` menyimpan rehearsal central secara
  isolated, canonical, idempotent, dan atomic terhadap conflict.
- `ArkasReferenceResolver` memiliki mode eksplisit `LEGACY_RAW`,
  `CENTRAL_COMPAT`, dan `CENTRAL_ONLY`. `CENTRAL_COMPAT` hanya mengembalikan
  central setelah shadow parity; mismatch tidak fallback diam-diam.

Evidence dari dump A/B/C:

- 9 `GLOBAL_REFERENCE_CONFIRMED`, `ref_rekening`, dan `ref_bku` lulus import
  reverse-order serta idempotency rehearsal.
- `ref_sumber_dana` lulus central base dedup dan tenant extension isolation.
- `ref_acuan_barang` belum promotion-ready: ditemukan row source dengan
  `id_barang` kosong dan row tersebut ditolak sebelum central write. Invalid
  source rows tidak boleh di-skip diam-diam.
- `ref_kode` belum promotion-ready: `id_kode` yang sama memiliki definisi
  semantic berbeda (`uraian_kode`/flag BOS) bahkan dalam satu dump. Promotion
  flat base gagal tertutup; applicability/semantic variant perlu kontrak baru.

Read authority production tetap tenant raw mirror. Controller dan Livewire
consumer (`ArkasReferenceController`, `RkasBudgetController`,
`RkasBudgetFilter`, dan `SpjV2CanonicalReadService`) belum di-switch. Resolver
baru menjadi boundary compatibility/shadow rehearsal, bukan production-wide
cutover.

Central promotion gate karena itu **PARTIAL / BLOCKED FOR TWO REFERENCES**;
read cutover tetap belum READY sampai invalid `ref_acuan_barang`, semantic
variant `ref_kode`, dan parity seluruh consumer diselesaikan.

## Blocker repair checkpoint — quarantine and code variants

`ref_acuan_barang` memakai report contract tanpa persistent quarantine table.
Setiap row menghasilkan status `ACCEPTED` atau `QUARANTINED_INVALID_ID`; null,
empty, whitespace-only, dan control-character identity tidak pernah ditulis ke
central canonical rowset. Report menyimpan row asli dan alasan sehingga
quarantine deterministic, idempotent, dan tidak silent-drop. Source dump tetap
immutable.

`ref_kode` tidak lagi diperlakukan sebagai flat `id_kode` base. Central base
menyimpan semantic variant dengan key deterministik SHA-256 atas tuple canonical
`parent_kode`, `uraian_kode`, `id_level_kode`, dan `tipe`, bersama `id_kode` dan
release. Tenant applicability menyimpan `tenant_id`, `tahun`, `sumber_dana_id`,
`bentuk_pendidikan_id`, release, dan source `id_kode`. Semantic variant yang
sama dideduplicate; variant berbeda dapat coexist hanya di context applicability
berbeda. Contradictory same-context, orphan applicability, dan dimension kosong
fail-closed.
