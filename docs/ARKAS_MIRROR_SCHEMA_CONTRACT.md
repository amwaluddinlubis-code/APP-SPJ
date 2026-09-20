# ARKAS Mirror Schema Contract

Status: **PARTIAL FREEZE**
Manifest: `ArkasMirrorManifest::VERSION` (`2026-09-19.v2`)

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

Pada checkpoint ini central reference tetap deferred. `ref_periode` dan
`ref_sumber_dana` adalah `CENTRAL_CANDIDATE`, sedangkan `ref_kode`,
`ref_rekening`, dan `ref_acuan_barang` tetap `UNKNOWN/HYBRID_CANDIDATE` karena
dump memiliki dimensi tahun, sumber dana, atau bentuk pendidikan. Pada
checkpoint awal belum ada bukti lintas sekolah; audit tiga dump terbaru pada
bagian akhir dokumen mengonfirmasi hanya subset reference yang aman sebagai
candidate central.

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

Reference candidates remain disabled: `ref_kode`, `ref_level_kode`,
`ref_periode`, `ref_rekening`, `ref_sumber_dana`, `ref_acuan_barang`.

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
source tables, mengimpor 13 tabel tenant dengan 7.771 rows, dan melewati
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

Comparator `ArkasReferenceParityService` dan rehearsal test membuktikan
comparison serta import order `A→B→C` dan `C→A→B` deterministic untuk sembilan
tabel confirmed. Status central promotion tetap **DEFERRED**: belum ada central
migration, central write-path, cutover read, atau penghapusan raw tenant copy.
