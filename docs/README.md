# SPJ BOSP Web — Dokumentasi

Terakhir diperbarui: **2026-09-19**

Dokumen ini adalah indeks dokumentasi untuk branch aktif `arkas-raw-mirror`. Tujuannya membedakan sumber status, kontrak permanen, arsitektur, panduan teknis, verification guide, RVR, dan dokumen historis agar catatan lama tidak mengalahkan kondisi project terbaru.

## Urutan sumber kebenaran

Jika ada perbedaan antar dokumen, gunakan urutan berikut:

1. `CURRENT_PROGRESS.md` — status release, evidence, blocker, RVR, dan deferred work terbaru.
2. `DEVELOPMENT_ROADMAP.md` — urutan pekerjaan aktif dan milestone berikutnya.
3. `SPJ_DESIGN_DECISIONS.md` — keputusan bisnis/domain permanen.
4. `ARCHITECTURE_COMPLETE.md` — arsitektur aktif, boundary tenant, ownership, dan layer aplikasi.
5. Feature guide/panduan teknis yang relevan.
6. Dokumen `HISTORICAL`, `SUPERSEDED`, atau `ARCHIVED` hanya untuk jejak sejarah.

Root `README.md` adalah entry point project, bukan pengganti `CURRENT_PROGRESS.md`.

Untuk metadata numbering executable, source of truth tetap:

```text
app/Services/SpjNumberingDocumentRegistry.php
```

Dokumentasi menjelaskan contract, tetapi tidak menggantikan registry executable.

## Functional gate aktif

Status saat indeks ini diperbarui mengikuti `CURRENT_PROGRESS.md`. Jangan mempromosikan source change baru menjadi FUNCTIONAL PASS sebelum CI/test aktual pada head terkait selesai hijau.

## Dokumen aktif utama

| Dokumen | Peran / status |
|---|---|
| `CURRENT_PROGRESS.md` | **AUTHORITATIVE STATUS** — status release, evidence, blocker, dan RVR terbaru. |
| `DEVELOPMENT_ROADMAP.md` | **ACTIVE** — prioritas dan milestone pengembangan aktif. |
| `DOCUMENTATION_MAINTENANCE.md` | **ACTIVE / REQUIRED** — Definition of Done dokumentasi, impact matrix, evidence rules, dan aturan wajib contributor/AI. |
| `SPJ_DESIGN_DECISIONS.md` | **ACTIVE CONTRACT** — aturan bisnis/domain permanen. |
| `ARCHITECTURE_COMPLETE.md` | **ACTIVE ARCHITECTURE** — layer aplikasi, ownership, tenant boundary, dan registry architecture. |
| `SYNCHRONIZATION.md` | **ACTIVE TECHNICAL GUIDE** — canonical sync ARKAS/BKU, Dapodik, reconciliation, employee identity, dan safe-sync semantics. |
| `NUMBERING_CORRECTION_AND_ROLLBACK.md` | **ACTIVE / IMPLEMENTED BASELINE** — numbering, cancel, rollback, correction, dan registry contract. |
| `USER_SCENARIOS.md` | **ACTIVE** — alur operator dan ownership workspace. |
| `GUI_STANDARDIZATION.md` | **ACTIVE CONTRACT** — layout/theme/primitive/icon dan aturan evidence visual. |
| `PERIODIC_REPORT_MODULE.md` | **ACTIVE FEATURE GUIDE / PRINT & PDF SOURCE IMPLEMENTED** — empat kelompok/39 slot laporan periode, generator internal, browser print, PDF, dan boundary terpisah dari template Laporan SPJ. Runtime visual QA masih RVR sampai diverifikasi. |
| `GUI_RUNTIME_QA.md` | **ACTIVE / RVR CHECKLIST** — browser desktop/laptop dan mobile/tablet verification. |
| `CSS_USAGE_GUIDE.md` | **ACTIVE** — CSS/theme contract. |
| `UI_ICON_MIGRATION.md` | **ACTIVE MIGRATION GUIDE** — icon canonical + compatibility bridge. |
| `LIVEWIRE_MIGRATION_PLAN.md` | **ACTIVE** — inventaris dan aturan migrasi Livewire/TALL. |

Semua contributor dan AI/coding agent wajib membaca `DOCUMENTATION_MAINTENANCE.md` dan melakukan **Documentation Impact Review** sebelum menyatakan pekerjaan selesai.

## Importer, generator, dan verification

| Dokumen | Peran / status |
|---|---|
| `ARKAS_IMPORTER.md` | **ACTIVE** — Generic ARKAS Importer, source key, profile-driven import, hardening, dan operator-data verification. |
| `DOCUMENT_TEMPLATE_PLACEHOLDERS.md` | **ACTIVE** — placeholder registry/usage dan template contract. |
| `TEMPLATE_MASTER_WORKFLOW.md` | **ACTIVE** — lifecycle import paket, update individual template, single-sheet download, dan master recomposition. |
| `P0_VERIFICATION_KIT.md` | **ACTIVE EVIDENCE KIT** — command dan successful canonical release-safety gate. |
| `P0_01_SOURCE_AUDIT.md` | **ACTIVE REAL-DATA GUIDE** — six-category source/real-data audit guidance. |

Untuk pekerjaan sinkronisasi, baca `SYNCHRONIZATION.md` lebih dulu. Untuk numbering/koreksi setelah NUMBERED, baca `NUMBERING_CORRECTION_AND_ROLLBACK.md`. Untuk penutupan GUI, baca `GUI_STANDARDIZATION.md` lalu `GUI_RUNTIME_QA.md`.

`OVERLAY_MIGRATION_RUNBOOK.md` adalah runbook operasi aktif untuk migrasi overlay operator dari ekspor database lama ke schema fresh SPJ.

`V2_A2_TENANT_DISCOVERY_SOURCE_IDENTITY_PREFLIGHT.md` adalah evidence preflight
aktif untuk discovery tenant, physical path mapping, legacy/source classification,
identity 56 tabel raw mirror, dan additive V2-B proposal.

`V2_B_ISOLATED_SCHEMA_IMPLEMENTATION.md` dan
`V2_C_TWO_TENANT_MIGRATION_REHEARSAL.md` adalah evidence additive schema dan
full-legacy rehearsal pada clone terisolasi; keduanya bukan bukti production
migration atau read-path cutover.

`V2_D_READ_PATH_CUTOVER.md` adalah guide aktif untuk shadow parity, canonical
read adapter, Paket/document relation bridge, downstream report/tax/period parity,
dan controlled read-path cutover. D1, D2, D3, serta downstream workflow parity
sudah memiliki focused runtime evidence pada isolated rehearsal. Dokumen ini
bukan bukti production cutover; authorization/active-context cutover regression,
rollback, dan write-through/overlay transition masih harus ditutup sebelum
consumer mutation-heavy membaca canonical V2 secara production.

## Feature verification / RVR aktif

| Dokumen | Status |
|---|---|
| `SIPLAH_MVP_PLAN.md` | **LEGACY FILENAME / ACTIVE VERIFICATION GUIDE** — core SiPlah baseline functional; generated-document/official-template output tetap RVR. |
| `GUI_RUNTIME_QA.md` | **RVR ACTIVE** — desktop/laptop dan mobile/tablet runtime checks. |
| `MOBILE_VISUAL_QA_TODO.md` | **LEGACY/ADDITIONAL MOBILE QA TODO** — jangan mengubah source readiness menjadi browser PASS. |

## Kontrak aktif lintas dokumentasi

- ARKAS/BKU adalah source readonly; operator SPJ adalah overlay.
- Source sync tidak menghapus overlay manual.
- Source missing/returning mempertahankan identity dan pekerjaan operator.
- NUMBERED/FINAL tidak dimutasi diam-diam oleh sync.
- Boundary tenant = `School + Fiscal Year + Fund Source`.
- Sequence numbering terisolasi per fund source.
- Metadata numbering mempunyai satu source of truth: `SpjNumberingDocumentRegistry`.
- `SpjDocumentTypeRegistry` khusus template/placeholder/output, bukan sequence numbering.
- Preview/download tidak menerbitkan nomor baru.
- Cancel individual mempertahankan nomor `CANCELLED`; rollback adalah operasi berbeda yang dapat melepas active tail number.
- Detail Transaksi hanya menulis `item_description`; correction `item_description` dan `payment_description` pada NUMBERED mengikuti contract aktif; FINAL tetap terkunci.
- Kategori canonical: `BARANG`, `KONSUMSI`, `PEMELIHARAAN`, `JASA_LAINNYA`, `SPPD`, `HONOR_PEGAWAI`.
- SiPlah adalah channel, bukan kategori.
- Master Pegawai menyatu (ARKAS + Dapodik + Manual); auto-fill KONSUMSI/SPPD memakai roster menyatu.
- Icon canonical dimiliki `<x-ui.icon>`; compatibility adapter lama tidak boleh menjadi registry kedua.
- Source-level responsive regression bukan bukti browser visual PASS.
- Livewire mutation wajib mempertahankan authorization dan tenant boundary; route GET middleware saja bukan bukti action Livewire independently authorized.
- Laporan Periode dimiliki generator internal APP-SPJ; perbaikan template Paket SPJ tidak menjadi dependency untuk browser print/PDF Laporan Periode.
- Jangan fabrikasi source data, penerima, vendor, SPPD, atau template untuk memaksa coverage.

## Klasifikasi status yang wajib dipakai

```text
FUNCTIONAL PASS
REAL-DATA VERIFIED
RVR
DEFERRED
HISTORICAL / SUPERSEDED / ARCHIVED
```

Jangan memakai kata “selesai”, “PASS”, atau “verified” bila yang tersedia hanya source path tanpa evidence yang sesuai.

## Aturan pemeliharaan dokumentasi

Aturan lengkap berada di `DOCUMENTATION_MAINTENANCE.md`. Ringkasannya:

1. dokumentasi adalah bagian Definition of Done;
2. setiap perubahan menjalani Documentation Impact Review;
3. status/evidence diperbarui di `CURRENT_PROGRESS.md`;
4. prioritas/milestone diperbarui di `DEVELOPMENT_ROADMAP.md`;
5. business rule, architecture, user flow, sync, numbering, GUI, dan feature guide diperbarui sesuai impact matrix;
6. dokumen baru/status dokumen berubah direfleksikan di indeks ini;
7. dokumentasi tidak boleh mengklaim PASS/verified melebihi evidence aktual;
8. instruksi agent tidak boleh hard-code prioritas feature yang cepat berubah;
9. dokumentasi usang/kontradiktif adalah defect dan harus diperbarui atau diarsipkan dengan aman.
