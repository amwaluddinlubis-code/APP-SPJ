# Arsitektur SPJ BOSP Web

Terakhir diverifikasi: **2026-09-12** terhadap kontrak/domain source aktif dan dokumentasi status canonical branch `gui-standardization`.

Dokumen ini menjelaskan arsitektur aktif branch `gui-standardization`. Untuk status release dan blocker gunakan `CURRENT_PROGRESS.md`; untuk evidence functional gate gunakan `P0_VERIFICATION_KIT.md` §1; untuk prioritas gunakan `DEVELOPMENT_ROADMAP.md`; untuk keputusan bisnis permanen gunakan `SPJ_DESIGN_DECISIONS.md`.

Dokumen arsitektur tidak menyimpan hash commit, nomor CI, atau jumlah test/assertion agar tidak menjadi stale ketika code gate bergerak.

## 1. Ringkasan

SPJ BOSP Web adalah aplikasi Laravel 12 untuk menyusun dokumen pertanggungjawaban BOSP berdasarkan RKAS/BKU yang disinkronkan dari ARKAS.

Tujuan arsitektur:

- source ARKAS/BKU tetap readonly;
- data operator disimpan sebagai overlay;
- multi-school memakai database tenant terpisah;
- tenant boundary canonical adalah `School + Fiscal Year + Fund Source`;
- workflow SPJ berjalan dari transaksi → Paket → validation → numbering → dokumen → final;
- source sync mempertahankan overlay dan identity;
- authorization dipisahkan dari tenant/context guard;
- controller tetap tipis dan orchestration berada di use case/service;
- preview/download tidak menjadi shortcut numbering;
- metadata domain numbering mempunyai satu canonical registry dan tidak diduplikasi di controller/UI/allocator.

Stack utama: PHP 8.2+, Laravel 12, Livewire 3, Alpine.js 3, Tailwind CSS 4, Vite 6, SQLite multi-koneksi, DomPDF, PhpSpreadsheet, PHPWord, PHPUnit 11.

## 2. Multi-database

### Database utama

Menyimpan user, sekolah, konfigurasi tenant, sumber ARKAS, backup/setup, dan metadata global.

### Database tenant/sekolah

Menyimpan fiscal year, fund source, source RKAS/BKU, transaksi, item, detail kategori SPJ, Paket, nomor dokumen, audit, importer staging/profile/run, employee, serta data kerja sekolah.

Boundary operasi tenant:

```text
School + Fiscal Year + Fund Source
```

Model connection `school` tidak boleh dibaca/ditulis sebelum tenant aktif benar. Request yang bergantung tahun/sumber dana harus memvalidasi context aktif.

### Reset tenant

Reset hanya membangun ulang database sekolah target:

```text
purge connection
→ hapus tenant sqlite + wal/shm
→ provision ulang tenant
→ migration tenant
→ reset sqlite_sequence
→ bersihkan context session terkait
```

Database utama tidak boleh ikut terhapus.

## 3. Source vs operator overlay

### Source ARKAS/BKU

Contoh source: nomor bukti, tanggal, uraian sumber, rekening, kegiatan, nilai bruto/pajak/neto, penerima source, dan payload sinkronisasi.

Source tidak diedit dari workspace operator.

### Overlay operator

Contoh:

- `item_description`;
- kategori SPJ;
- uraian pembayaran;
- payment method/reference;
- penerima utama;
- vendor/procurement fields;
- detail kategori;
- Paket/lifecycle;
- numbering;
- template/generated document metadata.

Safe sync tidak boleh menghapus overlay manual tanpa rule eksplisit.

## 4. Ownership workspace

### Detail Transaksi

Workspace source/context. Mutation item yang diizinkan hanya:

```text
item_description
```

`description`, quantity, unit, unit price, amount, dan source tax tetap readonly.

Gateway Paket memblokir create/open Paket bila `item_description` belum tersimpan.

### Paket SPJ

Workspace mutation dokumen SPJ:

- kategori;
- procurement/payment channel;
- penerima/vendor;
- data kategori;
- document requirements;
- numbering;
- preview/download;
- lifecycle/finalization.

Paket tidak boleh menulis ulang source tax atau `item_description`.

## 5. Kategori canonical

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

SiPLah bukan kategori. SiPLah adalah procurement/payment channel.

## 6. Lifecycle Paket

```text
DRAFT
READY
NUMBERED
FINAL
CANCELLED
```

Aturan utama:

- READY hanya setelah validation applicable terpenuhi;
- category change pada READY mengembalikan Paket ke DRAFT hanya jika kategori benar-benar berubah;
- NUMBERED/FINAL terkunci dari mutation normal;
- cancel/reissue/reopen menyimpan history;
- preview/download tidak mengalokasikan nomor baru.

## 7. Layer aplikasi

### Controller

Controller menangani HTTP boundary, authorization/request validation, lalu mendelegasikan orchestration.

### Use case SPJ

Use case aktif antara lain:

```text
SpjWorkspaceUseCase
CreateSpjDraftUseCase
UpdateSpjPackageDetailsUseCase
SpjPackageCategoryUseCase
SpjNumberingUseCase
SpjSingleNumberingUseCase
SpjDocumentLifecycleUseCase
SpjDocumentUseCase
SpjReportUseCase
```

Pembagian tanggung jawab:

- workspace/query/navigation;
- create/open DRAFT;
- update Paket;
- category mutation;
- batch/single numbering;
- lifecycle/finalization/cancel/replacement;
- preview/download/generator;
- report/export.

### Service domain

Service reusable mencakup transaction details, package validation, document requirements, procurement policy, canonical numbering registry, numbering policy/gate/order/allocator, ARKAS synchronization, employee identity, tenant database maintenance, template/generator, dan operational audit.

Business validation tetap backend.

## 8. Sinkronisasi ARKAS

### Canonical source sync

Runtime utama mengorkestrasi staging/reference sync/adapter domain/reconciliation.

Kontrak:

- source key stabil;
- overlay manual dipertahankan;
- source missing/returning tidak membuat identity baru;
- NUMBERED/FINAL tidak dimutasi diam-diam;
- perubahan source dapat menghasilkan reconciliation;
- queue/background flow wajib mengaktifkan tenant yang benar.

### Generic ARKAS Importer

Pipeline utama:

```text
ArkasImporterController
→ ArkasDatabaseExplorer / Bridge
→ ArkasImportProfile
→ ArkasStagingService
→ ArkasReconciliationService
→ ArkasGenericImportService
→ ArkasDomainAdapter
→ target domain / snapshot
```

Status saat ini: **FUNCTIONAL HARDENING PASS / READY FOR OPERATOR DATA TEST**.

Sudah diregresikan:

- shared deterministic source key;
- tenant boundary;
- Upsert / Incremental / Full Refresh;
- reconciliation preview read-only;
- source-empty semantics;
- schema drift blocking;
- background tenant activation;
- shared resource lock;
- created-at preservation;
- semantic metrics.

Sisa aktif importer adalah operator-data verification dan scale/performance, terutama Bridge-side delta fetch dan pagination/evaluation di atas row limit `100000`.

Pernyataan lama bahwa importer belum operator-ready karena resolver source key/tenant route gap sudah superseded.

## 9. Employee identity

Employee identity core menggabungkan provenance ARKAS/PTK, Dapodik, dan manual tanpa menebak identity secara agresif.

Regression membuktikan:

- normalized-name backfill;
- dry-run duplicate fusion;
- strong-identifier merge;
- NUPTK sebagai match kuat;
- unique normalized-name hanya fallback bila tidak ambigu;
- ambiguous same-name tidak silent merge;
- source provenance dipertahankan;
- operator-locked row tidak disapu sync;
- ARKAS dan Dapodik memakai identity resolution yang sama.

Kontrak UI tetap:

```text
Auto-fill KONSUMSI/SPPD = master Pegawai menyatu (ARKAS + Dapodik + Manual)
Participant manual      = allowed
```

Roster menyatu adalah kontrak aktif; koreksi operator canonical via `operator_locked`.

## 10. Workflow SPJ aktif

```text
Sinkronisasi ARKAS/BKU
→ Daftar Transaksi
→ Detail Transaksi
→ simpan item_description
→ Create/Open Paket
→ Isian Manual Paket
→ validation
→ READY
→ numbering
→ preview/download
→ FINAL
```

## 11. Workspace Paket

Sub-tab canonical:

```text
1. Rincian
2. Isian Manual
3. Rincian Pajak
4. Penomoran
```

Summary:

```text
Periode | Penerima | Bruto | Pajak | Nilai Dibayarkan
```

`Rincian Pajak` readonly dari source transaction.

Nomor otomatis ditampilkan sebagai informasi, bukan input manual.

## 12. PEMELIHARAAN bahan + upah

Relationship state tetap transaction/context-owned:

```text
maintenance_material_transaction_id
maintenance_labor_transaction_id
```

Selector dapat tampil pada Paket untuk UX, tetapi relationship bukan field Paket biasa. Document context membaca bahan/upah dari transaksi terkait tanpa menulis ulang source BKU.

## 13. Pajak

PPN/PPh/SSPD/tax total/net amount adalah source transaction.

- Detail Transaksi menampilkan ringkasan;
- Paket menampilkan summary;
- tab Rincian Pajak readonly;
- forged tax input dari Paket harus diabaikan/ditolak.

## 14. Tanggal pengadaan

Rule canonical:

```text
order_date <= transaction_date
order_date <= bap_date
bap_date <= bast_date
```

## 15. Penomoran dokumen — canonical registry

### Source of truth

Metadata numbering berada di satu registry:

```text
app/Services/SpjNumberingDocumentRegistry.php
```

Setiap definisi numbering membawa:

```text
code
label
numbered
applicable_categories
channel
event_date_rule
number_target
scope_rule
```

Current canonical numbered definitions:

```text
SPJ
PESANAN
BAP
BAST
SPK
RAB
SURAT_TUGAS_PERJALANAN_DINAS
```

Daftar tersebut tidak boleh di-hardcode ulang pada consumer. Consumer memperoleh metadata melalui registry atau `SpjNumberingPolicyService` sebagai runtime policy adapter.

### Tanggung jawab consumer

```text
SpjNumberingDocumentRegistry
        │
        ├── DocumentNumberFormatController / Format Penomoran
        ├── SpjNumberingUseCase / Penomoran Triwulan
        ├── SpjNumberingPolicyService
        ├── SpjNumberingGateService
        ├── SpjNumberingOrderService
        ├── SpjDocumentNumberService
        ├── SpjSingleNumberingUseCase
        ├── SpjDocumentLifecycleService
        └── SpjDocumentLifecycleUseCase
```

`SpjNumberingPolicyService` tidak memiliki daftar document type sendiri. Service ini menerapkan runtime condition seperti SiPlah/non-SiPlah, category normalization, event-date resolution, dan persistence default format berdasarkan metadata registry.

### Event date dan target number

`event_date_rule` mendeskripsikan relasi + field tanggal canonical dan optional fallback. `number_target` mendeskripsikan tempat nomor turunan disinkronkan, misalnya Paket, goods, work order, atau travel.

Contoh behavior:

```text
SPJ      -> transaction.transaction_date -> package.document_number
PESANAN  -> goods.order_date             -> goods.order_number
BAP      -> goods.bap_date               -> goods.bap_number
BAST     -> goods.bast_date              -> goods.bast_number
SPK      -> workOrder.spk_date            -> workOrder.spk_number
RAB      -> workOrder.rab_date            -> workOrder.rab_number
SURAT_TUGAS_PERJALANAN_DINAS
         -> travels.assignment_letter_date
            fallback travels.departure_date
         -> travels.assignment_letter_number
```

SPJ memakai applicable category wildcard `*` karena dokumen utama berlaku untuk setiap Paket termasuk legacy package yang belum mempunyai category. Dokumen turunan tetap mengikuti category/channel applicable.

### Registry template berbeda tanggung jawab

`SpjDocumentTypeRegistry` tetap digunakan untuk contract template/placeholder/output. Ia bukan source keputusan numbering dan tidak boleh dipakai untuk memperluas domain sequence secara otomatis.

### Invariant numbering

- domain nomor dipisahkan per document type;
- nomor mengikuti tanggal/peristiwa canonical;
- nomor aktif tidak boleh ganda/ditimpa;
- cancelled number tetap history;
- NUMBERED/FINAL locked;
- preview/download tidak menerbitkan nomor;
- boundary sequence tetap `School + Fiscal Year + Fund Source`.

## 16. Generator dokumen dan template

Status generator: **FUNCTIONAL PASS**.

Sudah dibuktikan secara regression:

- DOCX/XLSX dapat dibuat dan dibuka ulang parser;
- PDF valid;
- render preflight;
- unresolved placeholder guard;
- final artifact validation;
- package XLSX multi-sheet/PDF;
- preview/download tanpa numbering side effect.

Template upload juga sudah hardened:

- explicit package/single mode;
- separate error bags;
- oversized POST mode tetap teridentifikasi;
- extension-based DOCX/XLSX validation;
- PHP upload-limit display;
- storage lifecycle memakai disk `local` yang sama dengan generator;
- atomic replacement.

Yang masih RVR: official-template visual fidelity, print area/page breaks/header-footer/tabel dinamis, real printing, dan target Office/PDF viewer.

## 17. SiPLah

SiPLah adalah procurement/payment channel.

Core sudah FUNCTIONAL PASS untuk:

- persistence metadata marketplace/order/invoice/reference;
- lifecycle Paket normal;
- pemisahan nomor marketplace vs Surat Pesanan internal;
- placeholder SiPLah;
- document/procurement policy;
- BARANG SiPLah tidak dipaksa memakai internal purchase-order requirement yang tidak applicable.

Sisa aktif: generated-document E2E, official-template output QA, safe-sync verification pada data nyata, dan browser flow.

## 18. Read-only quarter audit

`spj:audit-quarter` adalah jalur canonical audit real-data sebelum mutation.

Kontrak:

- existing tenant only;
- query-only SQLite;
- tidak provision/migrate;
- tidak mengubah hash database;
- tidak mengubah metadata `SchoolDatabase`;
- memahami policy kategori/SiPLah/reconciliation.

Command ini dipakai untuk audit 66 Paket READY sebelum numbering pada isolated copy.

## 19. Frontend/theme

Entry Vite canonical:

```text
resources/css/app.css
resources/js/app.js
```

Markup baru memakai token `--ui-*`, `--theme-*`, primitive `x-ui.*`, dan class `ui-*` sesuai `GUI_STANDARDIZATION.md` dan `CSS_USAGE_GUIDE.md`.

Authenticated shell memakai route/page marker (`main[data-page]` / `data-route`) untuk boundary styling feature yang sedang digeneralisasi. Compatibility CSS/JS boleh tetap ada sementara, tetapi tidak boleh menjadi alasan menghidupkan stale standalone Vite entry atau selector route heuristik baru.

Source-level GUI readiness tidak sama dengan browser/runtime PASS; checklist runtime canonical berada di `GUI_RUNTIME_QA.md`.

## 20. Security/authorization

Role utama: ADMIN, OPERATOR, VIEWER/read-only.

Mutation sensitif—numbering, finalization, cancel/reopen, reset/restore, configuration, reconciliation—harus dilindungi backend.

Authorization role dan tenant activation adalah boundary berbeda; lulus role check tidak berarti tenant context otomatis benar.

## 21. Functional gate dan area RVR

Functional gate aktif: lihat evidence gate di `P0_VERIFICATION_KIT.md` §1 (tidak disalin ke sini agar tidak divergen).

Area yang belum final:

1. generated-document QA pada real-data package yang tersedia;
2. JASA_LAINNYA multi-recipient generated-document E2E;
3. PEMELIHARAAN bahan+upah full-document QA;
4. SiPLah generated-document E2E;
5. browser QA Paket SPJ desktop/laptop;
6. operational audit trail E2E;
7. employee identity/participant real-data UX verification;
8. official-template visual/runtime verification;
9. importer operator-data/scale/performance verification;
10. authenticated page-render/browser performance profiling + P2 GUI/style/report cleanup.

Mobile/tablet runtime minimum usability tetap RVR/non-blocker untuk target operator desktop/laptop saat ini; status canonical mengikuti `CURRENT_PROGRESS.md` dan `GUI_RUNTIME_QA.md`.

## 22. Dokumen acuan

```text
docs/README.md
docs/CURRENT_PROGRESS.md
docs/P0_VERIFICATION_KIT.md
docs/DEVELOPMENT_ROADMAP.md
docs/SPJ_DESIGN_DECISIONS.md
docs/NUMBERING_CORRECTION_AND_ROLLBACK.md
docs/USER_SCENARIOS.md
docs/GUI_STANDARDIZATION.md
docs/GUI_RUNTIME_QA.md
docs/CSS_USAGE_GUIDE.md
docs/ARKAS_IMPORTER.md
docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md
docs/P0_01_SOURCE_AUDIT.md
docs/SIPLAH_MVP_PLAN.md
docs/MOBILE_VISUAL_QA_TODO.md
```
