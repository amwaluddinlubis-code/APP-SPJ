# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: **2026-09-14**

Roadmap ini memuat urutan pekerjaan aktif pada branch `gui-standardization`. Status/evidence rinci berada di `CURRENT_PROGRESS.md`; code gate historis berada di `P0_VERIFICATION_KIT.md`; keputusan bisnis permanen berada di `SPJ_DESIGN_DECISIONS.md`.

Prinsip kerja aktif:

```text
stabilize integration gate -> operator flow -> temukan bug nyata -> perbaiki -> regression bila perlu
```

Jangan menambah smoke/regression test baru hanya untuk memperbesar coverage setelah contract inti cukup dibuktikan. Exception: mutation boundary baru wajib mempunyai negative authorization/tenant regression bila tanpa test tersebut permission tidak dapat dibuktikan.

---

# P0 — Core Release Safety

## P0-00 — Current HEAD integration + Livewire authorization hardening

**Status: SOURCE AUDIT COMPLETE / HARDENING REQUIRED / CI HEAD RED.**

Phase 1 source audit selesai pada HEAD `2e0f65cbd5c6e0fd8a8495f2d156805fd468ee35` dan mencakup seluruh 25 component di `app/Livewire/`.

Hasil audit:

```text
17  READ-ONLY / UI-STATE
 1  MUTATION GUARDED (SchoolSelector)
 1  CONTEXT MUTATION ACCEPTED (YearSelector)
 5  ACTIVE MUTATION BOUNDARIES NEED ROLE HARDENING
 1  UNMOUNTED MUTATION COMPONENT NEEDS HARDENING BEFORE REUSE
```

Phase 2 yang harus dikerjakan sebelum menambah area Livewire baru:

- [ ] harden `UserManagement::{createUser,updateUser,deleteUser}` sebagai ADMIN-only;
- [ ] harden `SchoolMaster::createSchool` sebagai ADMIN-only;
- [ ] harden `DatabaseMaintenance::run` sebagai ADMIN-only;
- [ ] harden `DatabaseResetForm::resetDatabase` sebagai ADMIN-only selain active-school + confirmation guard yang sudah ada;
- [ ] harden `DatabaseSchoolList::{activate,migrate}` sebagai ADMIN-only;
- [ ] tentukan nasib `DocumentStorageSettings`; bila tetap dipakai, beri operator/admin authorization sebelum reuse;
- [ ] tambahkan negative regression untuk actor yang tidak berhak;
- [ ] jangan mengubah lifecycle SPJ, numbering, sync, atau tenant ownership ketika hardening.

Integration gate setelah hardening:

- [ ] reproduce exact failure `SPJ Critical` current HEAD;
- [ ] perbaiki regression yang benar-benar menjadi penyebab;
- [ ] jalankan focused test terkait;
- [ ] jalankan kembali workflow sampai SPJ Critical PASS;
- [ ] pastikan full Unit PASS;
- [ ] pastikan full Feature PASS;
- [ ] baru promosikan current HEAD sebagai functional gate baru.

Evidence saat roadmap ini diperbarui:

```text
latest successful canonical gate : CI #469 / fd01fc6681...
current HEAD attempt              : CI #476 / 2e0f65c... / FAILURE
frontend build #476               : PASS
Blade compile #476                : PASS
SPJ Critical #476                 : FAILURE
full Unit / Feature #476          : SKIPPED
```

Tidak ada migrasi Livewire baru sampai P0-00 ditutup.

Panduan detail: `LIVEWIRE_MIGRATION_PLAN.md`.

---

## P0-01 — Six-category E2E

**Status: FUNCTIONAL BASELINE PASS / REAL-DATA BASELINE VERIFIED / GENERATED-DOCUMENT QA ACTIVE / INSTALLED-RUNTIME DEFERRED.**

Sudah dibuktikan pada successful baseline sebelumnya:

- [x] six-category functional lifecycle;
- [x] read-only real-data audit 2026/TW2 pada 66 READY package;
- [x] fund-source partition check pada scope yang diuji;
- [x] real-data numbering preflight read-only;
- [x] isolated first numbering;
- [x] individual cancel + reserved sequence;
- [x] isolated tail rollback;
- [x] source transaction/item tetap immutable pada baseline.

Pekerjaan aktif setelah P0-00 hijau:

- [ ] generate output nyata untuk kategori yang tersedia;
- [ ] koreksi hanya bug/operator overlay yang mempunyai evidence;
- [ ] jangan fabrikasi SPPD 2026 karena data nyata tidak tersedia.

---

## P0-02 — Generator dokumen + template

**Status: SUCCESSFUL BASELINE PASS / CURRENT HEAD OUTPUT-SENSITIVE CHANGES REQUIRE GREEN GATE / REAL-DATA OUTPUT QA ACTIVE / OFFICIAL-TEMPLATE VISUAL RVR.**

Baseline yang sudah dibuktikan:

- [x] Download Template XLSX individu true single-sheet;
- [x] source/master tersimpan tetap utuh;
- [x] Cek Placeholder read-only memakai resolver generator yang sama;
- [x] master template terbaru dirakit dari XLSX canonical aktif;
- [x] update satu XLSX individu mengganti source document type berikutnya tanpa memutasi master historis;
- [x] master parsial ditolak;
- [x] canonical XLSX HTML preview memilih worksheet document type yang benar.

Current HEAD menambahkan optimasi validator/template load dan PDF/report writer path. Sebelum statusnya dinaikkan:

- [ ] current HEAD harus memperoleh code gate hijau;
- [ ] buka satu individual template nyata di Microsoft Excel/LibreOffice;
- [ ] buka `MASTER-TEMPLATE-SPJ-TERBARU.xlsx` pada Excel/LibreOffice;
- [ ] periksa drawing, formula/reference, defined name, print area, page break, header/footer, dan repair prompt;
- [ ] generate dan inspeksi XLSX/PDF nyata per kategori yang tersedia;
- [ ] verifikasi field identitas, bukti, tanggal, uraian, penerima/vendor, nominal, pajak, dan nomor dokumen turunan.

Panduan canonical: `TEMPLATE_MASTER_WORKFLOW.md` dan `DOCUMENT_TEMPLATE_PLACEHOLDERS.md`.

---

## P0-03 — Numbering + registry + lifecycle

**Status: FUNCTIONAL BASELINE PASS / REGISTRY CANONICAL.**

Source of truth executable:

```text
app/Services/SpjNumberingDocumentRegistry.php
```

Sudah dibuktikan pada successful gate sebelumnya:

- [x] first numbering;
- [x] individual cancel mempertahankan `CANCELLED` permanen;
- [x] nomor berikutnya memakai sequence berikutnya;
- [x] tail rollback melepaskan tail dan membangun ulang checkpoint;
- [x] released sequence dapat dipakai kembali setelah valid tail rollback;
- [x] fund-source scoped sequence;
- [x] quarter rollback dependency regression;
- [x] `{TW}` tidak memaksakan prefix literal `TW.`;
- [x] registry dipakai consumer numbering utama.

Tidak menambah smoke test numbering tanpa bug/operator requirement baru.

---

## P0-04 — Authorization

**Status: HTTP/ROUTE BASELINE PASS / LIVEWIRE MUTATION HARDENING OPEN.**

Pekerjaan authorization saat ini identik dengan P0-00 Phase 2. Temuan source menunjukkan route role middleware tidak cukup untuk dijadikan bukti bahwa action Livewire sensitif independently authorized; custom persistent middleware aplikasi hanya memuat active-school dan active-year.

Definition of Done:

- [ ] action-level/policy/persistent mechanism yang sah untuk seluruh mutation sensitif;
- [ ] negative ADMIN/OPERATOR/VIEWER regression sesuai matrix permission;
- [ ] tenant scope tetap canonical;
- [ ] no privilege widening melalui Livewire update endpoint;
- [ ] code gate hijau.

---

## P0-05 — Safe sync + reconciliation

**Status: FUNCTIONAL BASELINE PASS / REAL-DATA RECONCILIATION ACTIVE.**

Kerjakan hanya ketika ditemukan mismatch source/overlay nyata:

- [ ] source missing/returning identity;
- [ ] overlay preservation;
- [ ] NUMBERED/FINAL protection;
- [ ] reconciliation flag/operator resolution.

---

## P0-06 — Tenant/context isolation

**Status: FUNCTIONAL BASELINE PASS.**

Boundary canonical:

```text
School + Fiscal Year + Fund Source
```

Phase 2 Livewire hardening wajib mempertahankan boundary ini. Tidak ada test tambahan aktif tanpa bug/boundary baru selain negative authorization yang diperlukan oleh mutation migration.

---

## P0-07 — APP DATA / backup / reset / restore

**Status: FUNCTIONAL BASELINE PASS / LIVEWIRE RESET ROLE HARDENING OPEN / INSTALLED-RUNTIME DEFERRED.**

`DatabaseResetForm` sudah mempunyai active-school match dan exact confirmation, tetapi role action guard masih harus ditutup pada P0-00.

---

## P0-08 — Generic ARKAS Importer

**Status: FUNCTIONAL BASELINE HARDENING PASS / OPERATOR DATA TEST ACTIVE.**

Importer mapping → preview → sync tidak menjadi target migrasi Livewire opportunistic. Kerjakan hanya issue nyata yang muncul pada operator flow.

---

# P1 — Real Data, Output, dan Operational Quality

P1 kembali menjadi fokus produk utama **setelah P0-00 integration gate hijau**.

## P1-01 — Generated-document real-data QA

Untuk setiap kategori nyata yang tersedia, pilih Paket representatif dan periksa hasil dokumen:

- [ ] BARANG;
- [ ] KONSUMSI;
- [ ] PEMELIHARAAN;
- [ ] JASA_LAINNYA;
- [ ] HONOR_PEGAWAI;
- [ ] SiPLah bila Paket nyata applicable.

SPPD 2026 tidak dipaksakan karena tidak ada real data.

Bug yang perlu langsung diperbaiki bila ditemukan: data kosong/salah, tanggal/nomor tidak sinkron, placeholder gagal, penerima/vendor salah, total/pajak salah, nomor turunan salah, XLSX/PDF gagal dibuka, clipping/page break/header/footer bermasalah.

## P1-02 — JASA_LAINNYA multi-penerima output

- [ ] gross/tax/net tiap penerima;
- [ ] aggregate reconciliation;
- [ ] dokumen per penerima bila applicable;
- [ ] preview/download/final dengan data nyata.

## P1-03 — PEMELIHARAAN bahan + upah output

- [ ] material dari transaksi bahan;
- [ ] pekerja/upah dari transaksi upah;
- [ ] linkage active context;
- [ ] RAB/SPK/kuitansi/A2 konsisten;
- [ ] source BKU tetap immutable.

## P1-04 — SiPLah generated-document E2E

Core procurement policy baseline tetap PASS. Tersisa:

- [ ] generated-document nyata;
- [ ] official-template output;
- [ ] browser reload/category/payment-method consistency bila ditemukan pada flow operator.

## P1-05 — Browser QA desktop/laptop

**Status: SOURCE READINESS / RUNTIME RVR.** Checklist: `GUI_RUNTIME_QA.md`.

Viewport prioritas:

- [ ] 1366×768;
- [ ] 1440×900;
- [ ] 1920×1080.

Fokus: sidebar, Paket SPJ, SPA tab navigation, modal preview, dropdown, pagination, preview/download, overflow/clipping, serta repeated Livewire navigation/update.

## P1-06 — Official-template visual/output QA

- [ ] individual template tidak meminta repair;
- [ ] master template terbaru tidak meminta repair;
- [ ] template resmi/aktual sekolah;
- [ ] visual fidelity Word/Excel/PDF;
- [ ] print area/page break/header-footer;
- [ ] hasil cetak/print preview target.

## P1-07 — Operational audit E2E

Audit flow nyata harus mampu menjelaskan actor, time, tenant context, entity, action, dan description untuk action sensitif. Mutation Livewire Database Manager yang dipertahankan harus tetap menggunakan `OperationalAuditService`.

## P1-08 — Employee identity + participant roster real-data

Functional identity baseline PASS. Tersisa bila muncul pada operator flow:

- [ ] duplicate/ambiguous identity nyata;
- [ ] auto-fill roster menyatu;
- [ ] participant manual UX;
- [ ] same-name/different-identifier review.

## P1-09 — Mobile/tablet minimum usability

**Status: SOURCE READINESS / RUNTIME RVR / NON-BLOCKER untuk target desktop-laptop.**

- [ ] 375×812;
- [ ] 768×1024;
- [ ] 1024×768.

Kerjakan setelah desktop operator flow stabil atau jika ditemukan bug mobile yang menghambat penggunaan nyata.

---

# P2 — Polish & Maintainability

Setelah P0/P1 stabil:

- [ ] migrasikan consumer `<x-ui-icon>` lama secara bertahap;
- [ ] cleanup compatibility CSS/JS setelah consumer legacy hilang;
- [ ] field-level validation UX;
- [ ] selesaikan Pint advisory bila masuk maintenance window;
- [ ] authenticated page-render/browser performance profiling;
- [ ] cleanup generated `bin/obj` bila relevan;
- [ ] report foundation/polish;
- [ ] mobile polish lanjutan;
- [ ] lanjutkan kandidat Livewire read-only berikutnya hanya bila manfaat operator jelas.

Kandidat Livewire setelah stabilization gate:

1. Rekonsiliasi read-only filter/search;
2. Template Dokumen filter katalog (upload/update tetap canonical flow);
3. Laporan Audit pagination.

Tetap ditunda: ARKAS importer stateful, workspace detail Paket mutation-heavy, dan protected Siswa tanpa instruksi eksplisit.

---

# Aturan pengerjaan

- jangan mengubah ARKAS/BKU source hanya agar test/audit lulus;
- jangan fabrikasi SPPD/vendor/penerima/template;
- audit real-data read-only sebelum mutation;
- mutation real-data hanya pada isolated copy;
- numbering mengikuti canonical order dan registry;
- Livewire mutation wajib mempunyai authorization boundary yang dapat dibuktikan;
- custom role middleware route tidak boleh dianggap otomatis persisten pada Livewire action;
- business rule tetap di use case/service/model, bukan component UI;
- docs status membedakan FUNCTIONAL PASS, REAL-DATA VERIFIED, RVR, dan DEFERRED;
- source-responsive PASS tidak sama dengan browser/mobile PASS;
- setelah contract utama PASS, jangan menambah test tanpa alasan nyata;
- bila ada bug: reproduce -> fix -> focused regression bila perlu -> operator re-check.
