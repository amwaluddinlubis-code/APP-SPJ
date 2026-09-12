# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: **2026-09-12**

Roadmap ini memuat urutan pekerjaan aktif pada branch `gui-standardization`. Status/evidence rinci berada di `CURRENT_PROGRESS.md`; code gate berada di `P0_VERIFICATION_KIT.md`; keputusan bisnis permanen berada di `SPJ_DESIGN_DECISIONS.md`.

Prinsip kerja aktif:

```text
operator flow -> temukan bug nyata -> perbaiki -> regression hanya bila perlu
```

Jangan menambah smoke/regression test baru hanya untuk memperbesar coverage setelah kontrak inti sudah cukup dibuktikan.

---

# P0 — Core Release Safety

## P0-01 — Six-category E2E

**Status: FUNCTIONAL PASS / REAL-DATA BASELINE VERIFIED / GENERATED-DOCUMENT QA ACTIVE / INSTALLED-RUNTIME DEFERRED.**

Sudah selesai:

- [x] six-category functional lifecycle;
- [x] read-only real-data audit 2026/TW2 pada 66 READY package;
- [x] fund-source partition check pada scope yang diuji;
- [x] real-data numbering preflight read-only;
- [x] isolated first numbering;
- [x] individual cancel + reserved sequence;
- [x] isolated tail rollback;
- [x] source transaction/item tetap immutable pada baseline.

Pekerjaan aktif:

- [ ] generate output nyata melalui aplikasi untuk kategori yang tersedia;
- [ ] koreksi hanya bug atau operator overlay yang mempunyai evidence;
- [ ] jangan fabrikasi SPPD 2026 karena data nyata tidak tersedia.

---

## P0-02 — Generator dokumen + template upload

**Status: FUNCTIONAL GENERATOR PASS / TEMPLATE UPLOAD HARDENED PASS / MASTER TEMPLATE RECOMPOSITION PASS / REAL-DATA OUTPUT QA ACTIVE / OFFICIAL-TEMPLATE VISUAL RVR.**

Sudah selesai:

- [x] download template individu tidak lagi mengembalikan seluruh paket master;
- [x] `Unduh Master Template Terbaru` merakit seluruh XLSX canonical aktif saat request dijalankan;
- [x] update satu XLSX individu otomatis menggantikan versi document type tersebut pada master download berikutnya;
- [x] master historis/source template tidak dimutasi saat update/download;
- [x] output master dinormalisasi mengikuti nama/urutan sheet `SpjDocumentTypeRegistry`;
- [x] master hasil rakitan divalidasi ulang melalui validator paket canonical;
- [x] master parsial ditolak bila satu template XLSX canonical aktif hilang;
- [x] regression meniru storage nyata importer: record lama berupa salinan master multi-sheet + satu update XLSX individu.

Pekerjaan aktif:

- [ ] buka `MASTER-TEMPLATE-SPJ-TERBARU.xlsx` pada Microsoft Excel/LibreOffice dan verifikasi drawing, formula/reference, print area, page break, header/footer, serta hasil cetak;
- [ ] buka/generate dokumen nyata BARANG;
- [ ] buka/generate dokumen nyata KONSUMSI;
- [ ] buka/generate dokumen nyata PEMELIHARAAN;
- [ ] buka/generate dokumen nyata JASA_LAINNYA;
- [ ] buka/generate dokumen nyata HONOR_PEGAWAI;
- [ ] verifikasi field penting: identitas sekolah, no bukti, tanggal, uraian, penerima/vendor, nominal, pajak, nomor/tanggal dokumen turunan;
- [ ] verifikasi XLSX/PDF dapat dibuka dan layout tidak clipping/berantakan;
- [ ] lanjutkan official-template visual fidelity dan print layout setelah bug output dasar bersih.

Lifecycle master canonical didokumentasikan di `TEMPLATE_MASTER_WORKFLOW.md`.

Jika ditemukan bug, perbaiki bug tersebut dan tambahkan regression hanya jika diperlukan untuk mencegah recurrence.

---

## P0-03 — Numbering + registry + lifecycle

**Status: FUNCTIONAL PASS / CANONICAL REGISTRY PASS / REAL-DATA CORE MUTATION QA PASS untuk numbering pertama, cancel/reserve, tail rollback.**

Sudah selesai:

- [x] read-only numbering preflight;
- [x] canonical first number pada isolated copy;
- [x] individual cancel mempertahankan nomor sebagai `CANCELLED` permanen;
- [x] nomor berikutnya memakai sequence berikutnya;
- [x] tail rollback melepaskan tail dan membangun ulang checkpoint;
- [x] released sequence dapat dipakai kembali setelah tail rollback;
- [x] fund-source scoped sequence;
- [x] quarter rollback dependency functional regression;
- [x] `{TW}` tidak lagi memaksakan prefix literal `TW.`; operator dapat menambah `TW.{TW}` pada pattern bila dibutuhkan;
- [x] satu `SpjNumberingDocumentRegistry` menjadi source of truth metadata numbering;
- [x] kode + label document type pada Format Penomoran berasal dari registry;
- [x] kode + label document type pada Penomoran Triwulan berasal dari registry;
- [x] category/channel eligibility berasal dari registry + runtime policy adapter;
- [x] event-date rule berasal dari registry;
- [x] number target relation/field berasal dari registry;
- [x] scope `MAIN`/`TRAVEL` berasal dari registry;
- [x] allocator tidak lagi mempunyai loop document type hardcoded;
- [x] lifecycle FINAL/cancel/replacement membaca registry;
- [x] gate source HEAD setelah refactor registry hijau pada full release-safety workflow.

Kontrak maintainability baru:

```text
Tambah/ubah metadata numbering
-> edit SpjNumberingDocumentRegistry
-> consumer membaca registry
-> jangan membuat array type/label/event-date/target kedua
```

`SpjDocumentTypeRegistry` tetap khusus template/placeholder/output dan bukan source sequence numbering.

Tidak menjadi fokus aktif:

- [ ] isolated real-data quarter rollback runtime — **PENDING / OPTIONAL**, hanya dijalankan bila diperlukan oleh bug/operator flow;
- [ ] full 66-package mutation smoke — **tidak diwajibkan** bila operator flow normal tidak menemukan masalah;
- [ ] menambah test registry baru hanya untuk coverage — **tidak diperlukan** selama existing regression tetap hijau dan tidak ada bug baru.

Panduan canonical: `NUMBERING_CORRECTION_AND_ROLLBACK.md`.

---

## P0-04 — Authorization

**Status: FUNCTIONAL PASS.**

Tidak ada pekerjaan tambahan kecuali ditemukan bug permission/tenant boundary pada operator flow.

---

## P0-05 — Safe sync + reconciliation

**Status: FUNCTIONAL PASS / REAL-DATA RECONCILIATION ACTIVE.**

Pekerjaan berikutnya hanya ketika ditemukan mismatch source/overlay nyata:

- [ ] source missing/returning identity;
- [ ] overlay preservation;
- [ ] NUMBERED/FINAL protection;
- [ ] reconciliation flag/operator resolution.

---

## P0-06 — Tenant/context isolation

**Status: FUNCTIONAL PASS.**

Boundary canonical:

```text
School + Fiscal Year + Fund Source
```

Tidak ada test tambahan aktif tanpa bug baru.

---

## P0-07 — APP DATA / backup / reset / restore

**Status: FUNCTIONAL PASS / INSTALLED-RUNTIME DEFERRED.**

Installed Windows runtime verification tetap deferred sampai masuk fase release packaging/runtime.

---

## P0-08 — Generic ARKAS Importer

**Status: FUNCTIONAL HARDENING PASS / OPERATOR DATA TEST ACTIVE.**

Kerjakan hanya issue nyata yang muncul pada import/sync operator. Scale/performance lanjutan tetap maintenance item, bukan blocker saat ini.

---

# P1 — Real Data, Output, dan Operational Quality

## P1-01 — Generated-document real-data QA

**Prioritas tertinggi sekarang.**

Gunakan aplikasi seperti operator biasa. Untuk setiap kategori nyata yang tersedia, pilih minimal satu Paket representatif dan periksa hasil dokumen.

- [ ] BARANG;
- [ ] KONSUMSI;
- [ ] PEMELIHARAAN;
- [ ] JASA_LAINNYA;
- [ ] HONOR_PEGAWAI;
- [ ] SiPLah bila Paket nyata applicable.

SPPD 2026 tidak dipaksakan karena tidak ada real data.

Kriteria bug yang perlu langsung diperbaiki:

- data kosong/salah;
- tanggal/nomor tidak sinkron;
- placeholder tidak terisi;
- penerima/vendor salah;
- total/pajak salah;
- nomor dokumen turunan salah;
- XLSX/PDF gagal dibuka;
- layout clipping/page break/header/footer bermasalah.

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

**Core procurement policy: FUNCTIONAL PASS.**

Tersisa:

- [ ] generated-document nyata;
- [ ] official-template output;
- [ ] browser reload/category/payment-method consistency bila ditemukan pada flow operator.

## P1-05 — Browser QA desktop/laptop — GUI-AUDIT-12

**Status: SOURCE READINESS PASS / RUNTIME RVR.** Checklist: `GUI_RUNTIME_QA.md`.

Prioritas viewport:

- [ ] 1366×768;
- [ ] 1440×900;
- [ ] 1920×1080.

Fokus hanya pada flow operator nyata: sidebar, Paket SPJ, tab, modal, dropdown, preview/download, pagination, dan overflow/clipping.

## P1-06 — Official-template visual/output QA

- [ ] buka Master Template Terbaru pada Microsoft Excel/LibreOffice dan pastikan fidelity template sumber tetap layak;
- [ ] template resmi/aktual sekolah;
- [ ] visual fidelity Word/Excel/PDF;
- [ ] print area/page break/header-footer;
- [ ] hasil cetak/print preview target;
- [ ] pembukaan output pada aplikasi target.

## P1-07 — Operational audit E2E

Audit pada flow nyata harus mampu menjelaskan actor, time, tenant context, entity, action, dan description untuk action sensitif. Kerjakan ketika operator flow menyentuh lifecycle tersebut; tidak perlu membuat skenario tambahan hanya untuk coverage.

## P1-08 — Employee identity + participant roster real-data

**Functional identity core: PASS.**

Tersisa bila muncul pada Paket KONSUMSI/SPPD/operator flow:

- [ ] duplicate/ambiguous identity nyata;
- [ ] auto-fill roster menyatu;
- [ ] participant manual UX;
- [ ] same-name/different-identifier review.

## P1-09 — Mobile/tablet minimum usability — GUI-AUDIT-13

**Status: SOURCE READINESS PASS / RUNTIME RVR / NON-BLOCKER untuk target desktop-laptop.**

Viewport canonical:

- [ ] 375×812;
- [ ] 768×1024;
- [ ] 1024×768.

Kerjakan setelah desktop operator flow stabil atau jika ditemukan bug mobile yang menghambat penggunaan nyata.

---

# P2 — Polish & Maintainability

- [ ] migrasikan consumer `<x-ui-icon>` lama secara bertahap;
- [ ] cleanup compatibility CSS/JS setelah consumer legacy hilang;
- [ ] field-level validation UX;
- [ ] selesaikan Pint advisory bila masuk maintenance window;
- [ ] authenticated page-render/browser performance profiling setelah operator flow stabil;
- [ ] Bridge generated `bin/obj` cleanup;
- [ ] report foundation;
- [ ] mobile polish lanjutan.

---

# Aturan pengerjaan

- jangan mengubah ARKAS/BKU source hanya untuk membuat test/audit lulus;
- jangan membuat data SPPD/vendor/penerima/template fiktif;
- audit real-data dilakukan read-only sebelum mutation;
- mutation real-data hanya pada isolated copy;
- numbering mengikuti canonical order;
- metadata numbering mempunyai satu source of truth: `SpjNumberingDocumentRegistry`;
- consumer numbering tidak boleh membuat daftar type/label/category/event-date/number-target/scope sendiri;
- perubahan domain/business rule dicatat di `SPJ_DESIGN_DECISIONS.md` dan feature guide terkait;
- docs status membedakan FUNCTIONAL PASS, REAL-DATA VERIFIED, RVR, dan DEFERRED;
- source-responsive PASS tidak sama dengan browser/mobile PASS;
- setelah kontrak utama PASS, **jangan menambah test tanpa alasan nyata**;
- bila ada bug: reproduce -> fix -> focused regression bila perlu -> operator re-check.
