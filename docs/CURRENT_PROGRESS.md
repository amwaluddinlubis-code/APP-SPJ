# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-12**

Dokumen ini adalah sumber status release utama untuk branch `gui-standardization`.

Definisi status:

- **FUNCTIONAL PASS**: dibuktikan oleh source + deterministic CI/regression;
- **REAL-DATA VERIFIED**: dibuktikan pada salinan database sekolah nyata tanpa mengarang data yang tidak tersedia;
- **RVR**: masih memerlukan real-value/runtime/operator verification untuk aspek yang tidak bisa dibuktikan hanya dari CI;
- **DEFERRED**: sengaja tidak dikerjakan pada fokus pengembangan saat ini, bukan berarti PASS.

---

## Checkpoint terbaru

### Release gate branch aktif

Evidence gate hidup di `P0_VERIFICATION_KIT.md` §1 dan tidak disalin ke sini agar checkpoint/angka tidak divergen. Pint advisory tetap non-blocking pada konfigurasi release sekarang dan bukan functional regression.

---

## Status release saat ini

Status keseluruhan:

```text
FUNCTIONAL CORE : PASS
REAL-DATA       : VERIFICATION ACTIVE
OFFICIAL OUTPUT : RVR ACTIVE
BROWSER/RUNTIME : RVR ACTIVE
FINAL RELEASE   : NOT YET
```

Aplikasi belum boleh disebut final release-ready hanya karena deterministic CI hijau. Official-template output, browser/operator QA, dan real-data verification tetap merupakan gate terpisah.

---

## GUI standardization audit

Status:

```text
GUI STANDARDIZATION CORE : ESTABLISHED
SOURCE-LEVEL CLEANUP      : PASS untuk GUI-AUDIT-01 s.d. 11 yang telah digate
DESKTOP SOURCE READINESS  : PASS
BROWSER DESKTOP/LAPTOP    : RVR ACTIVE
MOBILE SOURCE READINESS   : PASS
MOBILE/TABLET RUNTIME     : RVR / NON-BLOCKER
```

Bagian ini merangkum milestone source/evidence yang masih relevan terhadap status aktif. Detail kontrak dan kronologi GUI berada di `GUI_STANDARDIZATION.md` dan checklist runtime canonical berada di `GUI_RUNTIME_QA.md`.

Perbaikan audit yang sudah masuk antara lain:

- Detail Transaksi: `item_description` tetap editable pada Paket `NUMBERED` tanpa membuka field manual lain; `FINAL` tetap terkunci.
- Laporan Audit: page-local `<style>`/custom audit table diganti ke theme token dan shared `x-ui.table`.
- RKAS planning: cleanup standardisasi GUI dan action export sudah masuk pada rangkaian commit sebelumnya.
- Master Siswa index: filter/surface/divider/typography desktop+mobile memakai semantic theme token; tabel desktop memakai `x-ui.table`; hard-coded non-semantic `slate/indigo/white` pada index dihilangkan.
- Master Siswa form: checkbox cards dan sticky action area memakai semantic theme token; action footer memakai `x-ui.sticky-actions`; field, route, validation, dan lifecycle CRUD tetap sama.
- Master Siswa detail: data identitas dan payload Dapodik memakai `x-ui.detail-list` / `x-ui.detail-item`; breadcrumb, surface, line, dan typography memakai semantic theme token; masking NIK/telepon tetap dipertahankan.
- Data Hasil Sinkron: overview/navigation/card/table menggunakan semantic theme token; hard-coded group palette `indigo/sky/violet/slate` dihapus; detail table memakai shared `x-ui.table`; filter/per-page/server pagination dan data binding tetap dipertahankan.
- Master Pegawai index: desktop table memakai shared `x-ui.table`; status/source memakai `x-ui.badge`; action desktop/mobile memakai `x-ui.button`; surface/line/typography memakai semantic `--ui-*`; hard-coded palette legacy dihapus tanpa mengubah filter, summary, pagination, role access, atau masking NIK.
- **GUI-AUDIT-09**: SPJ main navigation tabs memakai metadata icon canonical `archive/document/report/warning`; emoji navigation tidak lagi dipakai sebagai icon utama.
- **GUI-AUDIT-10**: halaman Reset Database Sekolah memakai theme token, `x-ui.button`, dan `x-ui.icon`; semantic danger/warning tetap dipertahankan tanpa hard-coded non-semantic slate/indigo action chrome.
- **GUI-AUDIT-11**: registry/rendering icon disatukan di `resources/views/components/ui/icon.blade.php`; `<x-ui-icon>` sekarang hanya compatibility adapter ke `<x-ui.icon>` dan tidak lagi memiliki registry SVG terpisah.
- **GUI-AUDIT-12**: source readiness desktop/laptop PASS melalui static regression untuk responsive fallback/table contract, tetapi browser visual/runtime QA **masih RVR**.
- **GUI-AUDIT-13**: source readiness mobile/tablet PASS melalui responsive source guard, tetapi runtime mobile/tablet usability **masih RVR** dan non-blocker untuk target desktop/laptop saat ini.
- Toolbar ekspor Laporan SPJ digabung ke dropdown `Ekspor` (`x-ui.action-menu`); strip ganda taxes/transaksi/RKAS disatukan kondisional; empty-state users/templates/taxes memakai `x-ui.empty-state`; baris DRAFT di Monitoring mengarah ke checklist paket.
- **Density pilot `/spj`**: scoped token pass menurunkan control/header/panel spacing agar workspace operator lebih nyaman pada zoom browser 100%; frontend build, theme contrast QA, dan GUI source-readiness regression lulus. Browser visual/runtime verification tetap **RVR** dan belum menjadi standar global.
- **Dashboard density/header pass**: dashboard productivity dipadatkan pada header, statistik, panel prioritas, dan panel kerja; desktop menengah menjaga topbar satu baris saat sidebar expanded. Browser evidence pada viewport `1101x889` menunjukkan topbar `76px` dan tanpa horizontal overflow; viewport resmi lainnya tetap **RVR**.
- **Typography token pass**: skala canonical `14px` body/control, `13px` label/table kecil, `12px` caption, `16px` panel heading, dan `24px` page title diterapkan pada shared controls serta page header. Computed browser evidence pada `/pegawai` menunjukkan title `24px`, description `14px`, dan button `14px`; viewport resmi lainnya tetap **RVR**.
- **SPJ 100% comfort pass**: topbar `/spj` pada desktop menengah dipadatkan menjadi satu baris; evidence browser `1101x889` menunjukkan tinggi `72px`, tanpa horizontal overflow, dengan typography tetap pada skala canonical. Theme QA dan GUI regression lulus; viewport resmi lainnya tetap **RVR**.
- **Sidebar typography pass**: label navigasi sidebar memakai `14px / 20px` dengan item klik sekitar `42px`; evidence browser menunjukkan ukuran tersebut aktif tanpa diagnostic error. Theme QA dan GUI regression lulus.
- **Dashboard operator redesign**: dashboard productivity menggunakan hierarki header/status, satu fokus prioritas, pekerjaan lanjutan, lalu antrean kerja. Evidence browser `1101x889` menunjukkan tanpa horizontal overflow dan tinggi dokumen turun menjadi sekitar `1938px`; theme QA dan GUI regression lulus. Viewport resmi lain tetap **RVR**.
- **Theme foreground/icon generalization**: seluruh dark appearance, termasuk ARKAS dark, kini memakai kontrak foreground content berdasarkan `data-ui-appearance`; `x-page-header` memiliki fallback ikon canonical berbasis konteks dan tab Paket SPJ tidak lagi memakai emoji. Build, theme QA, Blade cache, dan GUI regression lulus.
- **Shell page-marker generalization**: authenticated layout kini memberi `main[data-page]` dan `data-route`; kontrak topbar medium-desktop dipindahkan ke `layout-token-native.css`, sementara duplikasi topbar dashboard/SPJ dihapus. Selector feature lain masih menjadi migration debt dan belum seluruhnya dipindahkan.
- **Dashboard/SPJ selector migration**: seluruh boundary route pada `dashboard-standardization.css`, `spj-workspace-standardization.css`, dan pengecualian theme SPJ memakai `data-page`; grep tidak menemukan lagi `main:has(#spj-main-tabs)` atau deteksi dashboard lewat href. Build, theme QA, Blade cache, dan GUI regression lulus.
- **Database/documents/transactions selector migration**: boundary CSS utama berpindah ke `main[data-page="database"]`, `main[data-page="documents"]`, dan `main[data-page="transactions"]`; selector transaksi lama berbasis `/data-sinkron/bku` tidak diterapkan ke `synced-data` karena salah scope. Build, theme QA, Blade cache, dan GUI regression lulus.
- **Route performance transport baseline (2026-09-12)**: seluruh 51 GET route diprobe dengan placeholder parameter aman. Hasil `49x 302` auth/context redirect, `/masuk` `200`, `/setup` `404` pada runtime aktif; latency min `27ms`, median `32ms`, p95 `68ms`, max `94ms`. Ini belum merupakan authenticated page-render/browser performance PASS. Production asset baseline: CSS `903.4 KB` dan JS `195.8 KB` uncompressed; build output gzip sekitar `108.14 KB` CSS + `65.06 KB` JS. Authenticated route RVR sampai sesi browser dan active school/year tersedia.

Regression guard GUI yang aktif pada area ini:

```text
tests/Feature/StudentIndexThemePrimitiveUiTest.php
tests/Feature/StudentFormDetailThemePrimitiveUiTest.php
tests/Feature/SyncedDataThemePrimitiveUiTest.php
tests/Feature/EmployeeIndexThemePrimitiveUiTest.php
tests/Feature/GuiAudit09To13SourceReadinessTest.php
tests/Feature/SpjActiveContractsGuardTest.php
```

Guard di atas diregresikan melalui suite release-safety/feature pada CI code gate aktif. Evidence gate canonical tetap berada di `P0_VERIFICATION_KIT.md` §1; snapshot test count lokal lama tidak dipakai sebagai status branch aktif.

Batas klaim:

- source/primitives/theme/icon contracts yang diregresikan boleh disebut PASS;
- browser visual QA desktop/laptop **belum** boleh disebut PASS sebelum checklist `docs/GUI_RUNTIME_QA.md` dijalankan;
- mobile/tablet minimum usability juga tetap RVR sampai runtime evidence tersedia;
- compatibility wrapper `<x-ui-icon>` masih ada untuk consumer legacy, tetapi bukan lagi registry kedua. Penghapusan wrapper menunggu migrasi consumer lama selesai.

---

## P0-01 — Six-category SPJ end-to-end

Status:

```text
FUNCTIONAL SIX-CATEGORY E2E : PASS
REAL-DATA VERIFICATION      : ACTIVE
INSTALLED-RUNTIME           : DEFERRED / RVR
```

Kategori canonical:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Deterministic workflow sudah menjaga:

```text
DRAFT
-> READY
-> NUMBERED
-> preview XLSX/PDF
-> FINAL
```

beserta validation, tenant context, lifecycle lock, dan audit yang relevan.

### Real-data baseline terakhir

Baseline audit yang sudah dicatat sebelumnya:

```text
tables                    : 43
transactions              : 170
transaction_items         : 407
spj_packages              : 66
spj_documents             : 0
document_number_sequences : 0
document_number_formats   : 0
operational_audit_logs    : 309
fiscal_years              : 6
fund_sources              : 2
```

Untuk 2026 pada baseline tersebut:

```text
BARANG          : 41
HONOR_PEGAWAI   : 12
JASA_LAINNYA    : 9
KONSUMSI        : 2
PEMELIHARAAN    : 2
SPPD            : 0
```

SPPD nyata tersedia pada data 2025, bukan 2026. Jangan fabrikasi SPPD 2026 untuk memaksa coverage.

Original baseline harus tetap immutable; mutation real-data hanya dilakukan pada isolated copy.

---

## P0-02 — Document generator / template

Status:

```text
FUNCTIONAL GENERATOR        : PASS
TEMPLATE UPLOAD HARDENING   : PASS
OFFICIAL-TEMPLATE VISUAL QA : RVR
```

Kontrak yang sudah dijaga:

- preview/download tidak menerbitkan nomor;
- template invalid tidak mengganti template aktif;
- unresolved placeholder tidak boleh diam-diam lolos ke final output;
- functional artifact generation tidak sama dengan visual verification dokumen resmi.

Detail placeholder canonical berada di `DOCUMENT_TEMPLATE_PLACEHOLDERS.md`.

---

## P0-03 — Numbering, lifecycle, correction & rollback

Status:

```text
FUNCTIONAL NUMBERING        : PASS
INDIVIDUAL CANCEL           : PASS
TAIL ROLLBACK               : PASS
QUARTER ROLLBACK            : PASS
FUND-SOURCE SEQUENCE SCOPE  : PASS
POST-NUMBERING EDIT RULE    : PASS
REAL-DATA OPERATOR QA       : ACTIVE
```

### Individual cancel

```text
nomor -> CANCELLED permanen
sequence tidak mundur
nomor tidak dipakai ulang
```

Reissue setelah individual cancel mendapat nomor/sequence baru.

### Tail rollback

Rollback dari sequence `N`:

```text
N ... tail aktif dilepas
Paket terdampak -> DRAFT
sequence dibangun ulang dari numbering yang masih sah
released number boleh dipakai kembali
```

Rollback tidak boleh melintasi nomor SPJ yang telah `CANCELLED` secara individual, karena nomor tersebut adalah history permanen.

### Cancel numbering triwulan

Dependency canonical:

```text
TW4 -> TW3 -> TW2 -> TW1
```

Triwulan lebih lama tidak dapat di-reset selama triwulan setelahnya masih mempunyai numbering aktif dalam context yang sama.

Boundary dependency dan sequence:

```text
School + Fiscal Year + Fund Source
```

Numbering sumber dana lain tidak boleh memblokir rollback atau mengubah sequence context aktif.

Full quarter reset ditolak bila target quarter memiliki nomor SPJ cancelled individual permanen.

### Sequence per fund source

`document_number_sequences` sekarang di-scope oleh:

```text
fiscal_year_id
+ fund_source_id
+ format_name
+ period_key
```

Migration tenant:

```text
database/migrations/school/2026_09_11_180000_scope_document_number_sequences_by_fund_source.php
```

### Edit setelah numbering

`item_description`:

```text
NUMBERED -> boleh dikoreksi
nomor/sequence -> tetap
FINAL -> terkunci
```

Data manual Paket seperti category/payment/vendor/penerima/procurement/detail kategori tetap terkunci pada NUMBERED/FINAL dan harus melalui rollback/lifecycle resmi terlebih dahulu.

Regression canonical:

```text
tests/Feature/SpjNumberingRollbackTest.php
tests/Feature/DocumentNumberingWorkflowTest.php
tests/Feature/SpjOwnershipMigrationTest.php
tests/Feature/SpjWorkspaceMigrationTest.php
```

Panduan domain lengkap: `docs/NUMBERING_CORRECTION_AND_ROLLBACK.md`.

---

## P0-04 — Authorization

Status: **FUNCTIONAL PASS**.

Contract:

- VIEWER read-only;
- OPERATOR workflow operasional sesuai permission;
- ADMIN lifecycle/maintenance/sensitive action;
- role authorization tidak menggantikan tenant/context isolation.

Rollback numbering dan cancel numbering triwulan adalah jalur administrator.

---

## P0-05 — Safe synchronization / reconciliation

Status:

```text
FUNCTIONAL PASS
REAL-DATA RECONCILIATION VERIFICATION : ACTIVE
```

Kontrak utama:

```text
ARKAS/BKU = readonly source
overlay operator = dipertahankan
source missing = jangan hapus pekerjaan operator
source returning = reuse identity yang sama
NUMBERED/FINAL = tidak dimutasi diam-diam
```

Detail canonical berada di `SYNCHRONIZATION.md`.

---

## P0-06 — Tenant isolation

Status: **FUNCTIONAL PASS**.

Boundary canonical:

```text
School + Fiscal Year + Fund Source
```

Boundary ini juga diterapkan pada sequence numbering/rollback sehingga sumber dana lain tidak ikut ter-reset.

---

## P0-07 — School database maintenance

Status:

```text
FUNCTIONAL MAINTENANCE : PASS
INSTALLED-RUNTIME      : DEFERRED / RVR
```

Reset/provision/backup/restore tetap harus menjaga primary database dan tenant lain.

---

## P0-08 — Generic ARKAS Importer

Status:

```text
FUNCTIONAL HARDENING : PASS
OPERATOR DATA TEST   : ACTIVE / NEXT VERIFICATION
```

Importer/sync tidak boleh menulis data fiktif untuk memaksa downstream SPJ PASS.

---

## Unified Employee Identity

Status:

```text
FUNCTIONAL IDENTITY CORE : PASS
REAL-SCHOOL VERIFICATION : ACTIVE
```

Identity matching harus konservatif. Normalized name ambigu tidak boleh menyebabkan silent merge.

Auto-fill peserta KONSUMSI/SPPD menggunakan master Pegawai menyatu `(ARKAS + Dapodik + Manual)`; participant manual tetap diperbolehkan dan provenance source harus tetap dipertahankan.

---

## Quarter Audit

Status:

```text
FUNCTIONAL PASS
READY FOR REAL-DATA AUDIT
```

Quarter audit harus tetap read-only ketika digunakan untuk menentukan baseline.

---

## SiPLah

Status:

```text
FUNCTIONAL CORE          : PASS
GENERATED-DOCUMENT E2E   : RVR
OFFICIAL-TEMPLATE OUTPUT : RVR
```

SiPLah tetap channel/payment context, bukan `spj_category`.

---

## P1 aktif

Prioritas aktif:

1. jalankan read-only audit pada baseline real-data 66 Paket READY;
2. perbaiki hanya blocker legitimate yang ditemukan audit;
3. lakukan operator/real-data QA untuk rollback numbering dengan isolated copy, terutama alignment nomor SPJ terhadap source order ARKAS;
4. jalankan **GUI-AUDIT-12 browser QA desktop/laptop** berdasarkan `docs/GUI_RUNTIME_QA.md`;
5. jalankan **GUI-AUDIT-13 mobile/tablet minimum usability**; non-blocker untuk target desktop/laptop tetapi tetap RVR sampai ada runtime evidence;
6. lanjutkan migrasi consumer legacy `<x-ui-icon>` secara bertahap dan compatibility cleanup hanya setelah consumer lama hilang;
7. verifikasi JASA_LAINNYA multi-recipient pada generated document nyata;
8. verifikasi PEMELIHARAAN bahan + upah end-to-end dokumen;
9. verifikasi SiPLah generated-document + official template;
10. operational audit E2E dan employee identity real-school + participant roster.

---

## P2 / maintenance debt

- field validation UX;
- Pint advisory repository pada 3 file yang sudah dikenal;
- migrasi consumer legacy `<x-ui-icon>` menuju `<x-ui.icon>`;
- GUI compatibility-layer cleanup setelah consumer legacy benar-benar selesai;
- authenticated page-render/browser performance profiling, optimization, dan regression budget setelah transport baseline;
- Bridge `bin/obj` hygiene;
- report foundation;
- mobile polish setelah target desktop/laptop stabil.

---

## Open verification / release blockers

Belum boleh diberi status final sampai evidence tersedia untuk:

- official template visual/output RVR;
- GUI-AUDIT-12 browser/operator runtime QA;
- GUI-AUDIT-13 mobile/tablet runtime QA bila ingin menutup minimum usability;
- real-data numbering/rollback operator verification pada isolated copy;
- real-data category-specific document QA yang belum selesai;
- installed-runtime checks yang masih DEFERRED.

Tidak ada blocker functional deterministic baru pada CI code gate aktif yang dicatat di `P0_VERIFICATION_KIT.md` §1. Perubahan dokumentasi-only setelah gate tersebut tidak dianggap sebagai code gate baru.

---

## Aturan evidence

1. Jangan mengubah source data hanya agar test/audit real-data PASS.
2. Jangan memakai deterministic fixture sebagai bukti bahwa real-data verified.
3. Jangan memakai screenshot/UI appearance sebagai pengganti backend regression.
4. Jangan menyatakan CI baru untuk commit docs-only.
5. Setiap source/test change setelah code gate aktif harus memperoleh CI code gate hijau baru sebelum menggantikan evidence canonical di `P0_VERIFICATION_KIT.md` §1.
6. Jika business rule berubah, sinkronkan `SPJ_DESIGN_DECISIONS.md`, feature guide terkait, test, dan dokumen ini.
7. GUI source cleanup hanya boleh diberi status source-level PASS; browser visual QA tetap RVR sampai diverifikasi di runtime.
8. Source responsive fallback tidak boleh dipromosikan menjadi desktop/mobile visual PASS tanpa menjalankan `docs/GUI_RUNTIME_QA.md`.
