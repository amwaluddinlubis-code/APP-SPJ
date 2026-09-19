# ARKAS Mirror Schema Contract

Status: **PARTIAL FREEZE**
Manifest: `ArkasMirrorManifest::VERSION` (`2026-09-19.v1`)

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

Pada checkpoint ini lima tabel reference (`ref_kode`, `ref_level_kode`,
`ref_periode`, `ref_rekening`, `ref_sumber_dana`) masih `UNKNOWN` dan disabled.
Belum ada bukti dua sekolah yang cukup untuk mempromosikannya ke central.

## Manifest resmi

`app/Services/ArkasMirrorManifest.php` adalah allow-list version-controlled.
Hanya tabel berikut yang enabled untuk raw mirror tenant:

| Source table | Classification | Bridge | Key strategy | Target |
|---|---|---|---|---|
| `anggaran` | TENANT_DATA | `ArkasTenantDataBridge` | PRIMARY_KEY | school |
| `kas_umum` | TENANT_DATA | `ArkasTenantDataBridge` | PRIMARY_KEY | school |
| `kas_umum_nota` | TENANT_DATA | `ArkasTenantDataBridge` | PRIMARY_KEY | school |
| `kas_umum_nota_pajak` | TENANT_DATA | `ArkasTenantDataBridge` | PRIMARY_KEY | school |
| `pegawai` | TENANT_DATA | `ArkasTenantDataBridge` | PRIMARY_KEY | school |
| `ptk` | TENANT_DATA | `ArkasTenantDataBridge` | PRIMARY_KEY | school |
| `rapbs` | TENANT_DATA | `ArkasTenantDataBridge` | PRIMARY_KEY | school |
| `rapbs_periode` | TENANT_DATA | `ArkasTenantDataBridge` | PRIMARY_KEY | school |
| `sekolah_penjab` | TENANT_DATA | `ArkasTenantDataBridge` | PRIMARY_KEY | school |
| `mst_sekolah` | TENANT_DATA | `ArkasTenantDataBridge` | PRIMARY_KEY | school |

Reference candidates currently disabled/UNKNOWN:

`ref_kode`, `ref_level_kode`, `ref_periode`, `ref_rekening`,
`ref_sumber_dana`.

Tabel source lain yang tidak tercantum di manifest tidak diimpor. Enumerasi
`tables` hanya digunakan untuk menemukan apakah entry eksplisit tersedia dan
untuk menghitung skipped table; tidak ada mapping atau target dinamis yang
dihasilkan dari hasil enumerasi.

## Bridge contract

Setiap bridge harus mengetahui source tables dan scope target melalui code.
Kontrak minimalnya adalah `ArkasMirrorBridge::scope()` dan
`ArkasMirrorBridge::sourceTables()`. Handler harus ditambah secara eksplisit
ke manifest sebelum dapat dipakai. Transformasi domain khusus tetap berada di
service domain yang sudah ada (`ArkasReferenceSynchronizationService`,
`ArkasSynchronizationServiceV2`) dan bukan pada mapping UI.

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

Bridge harus fail-closed bila table yang diminta tidak ada atau contract
source/key berubah secara material. Raw mirror mempertahankan snapshot valid
sebelumnya bila refresh gagal. Unknown, empty, dan disabled table tidak boleh
menjadi target baru. Perubahan schema harus menghasilkan audit/error yang dapat
ditindaklanjuti sebelum import diteruskan.

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

## Audit classification result

The repository contains a bridge schema/table command surface, but no
configured readable ARKAS source database was available in this audit shell;
therefore total source-table count and cross-school row parity are **RVR**, not
fabricated. The code-level classification above is the current explicit
manifest inventory. Schema status is **PARTIAL FREEZE**, not FROZEN.

## Next migration phases

1. Inventory both real school sources read-only: table schema, keys, row counts,
   and cross-school equality for reference candidates.
2. Promote only proven global references into a central typed/reference schema.
3. Add tenant bridge contract tests and isolated-copy backfill/parity reports.
4. Switch reads V2-first, freeze legacy writes, then remove compatibility
   mapping only after package/document/numbering gates remain green.

FINAL lifecycle, settlement, bulk-final, period close/open, browser QA, and
unrelated lifecycle mutations remain outside this contract.
