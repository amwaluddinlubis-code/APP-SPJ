# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: **2026-09-11**

Roadmap ini memuat urutan pekerjaan aktif pada branch `gui-standardization`. Status rinci dan evidence ada di `CURRENT_PROGRESS.md`; keputusan bisnis permanen berada di `SPJ_DESIGN_DECISIONS.md`.

## Release gate functional

```text
commit : 0df9b2ffbf14ed191e36c063e6355f9cb63c4a66
subject: test: gate template upload routing regression
CI run : 34578276166
CI job : 103195683045
PASS   : 243 tests / 1848 assertions
```

```text
Frontend build       PASS
Blade compile/cache  PASS
SPJ Critical         PASS
Repository Pint      ADVISORY — 2 style issues
```

Source aplikasi/test pada HEAD masih sama dengan gate tersebut; commit setelahnya hanya dokumentasi. `FUNCTIONAL PASS` tidak sama dengan final production verification. `RVR` tetap membutuhkan real-data, browser, template resmi, atau runtime evidence sesuai konteks.

---

# P0 — Core Release Safety

## P0-01 — Six-category E2E

**Status: FUNCTIONAL PASS / REAL-DATA VERIFICATION ACTIVE / INSTALLED-RUNTIME DEFERRED.**

Sudah selesai secara deterministic:

- [x] BARANG sampai FINAL + preview/download;
- [x] KONSUMSI sampai FINAL + preview/download;
- [x] PEMELIHARAAN sampai FINAL + preview/download;
- [x] JASA_LAINNYA sampai FINAL + preview/download;
- [x] SPPD sampai FINAL + preview/download;
- [x] HONOR_PEGAWAI sampai FINAL + preview/download;
- [x] numbering/finalization/preview/download melalui runtime path aplikasi;
- [x] real XLSX/PDF regression;
- [x] preview/download tidak mengalokasikan nomor;
- [x] READY category change kembali DRAFT bila kategori benar-benar berubah;
- [x] lifecycle audit dasar;
- [x] six-category test masuk `SPJ Critical`.

Real-data baseline terbaru:

```text
170 transactions
407 transaction_items
66 spj_packages
66 READY
0 spj_documents
0 document_number_sequences
0 document_number_formats
```

Pekerjaan aktif:

- [ ] audit read-only seluruh 66 READY package dengan `spj:audit-quarter`;
- [ ] kelompokkan blocker legitimate per kategori dan canonical numbering order;
- [ ] koreksi hanya operator overlay yang mempunyai evidence;
- [ ] setelah clean, uji numbering pada isolated copy;
- [ ] pertahankan source transaction/item immutable.

SPPD nyata tersedia pada fiscal year 2025, bukan 2026. Jangan membuat SPPD 2026 fiktif untuk mengejar coverage.

---

## P0-02 — Generator dokumen + template upload

**Status: FUNCTIONAL GENERATOR PASS / TEMPLATE UPLOAD HARDENED PASS / OFFICIAL-TEMPLATE VISUAL RVR.**

- [x] DOCX/XLSX real artifact;
- [x] PDF real artifact;
- [x] render preflight;
- [x] unresolved placeholder guard;
- [x] generated artifact validation;
- [x] multi-template XLSX/PDF;
- [x] preview/download tanpa numbering side effect;
- [x] common placeholder enam kategori;
- [x] invalid upload tidak mengganti template aktif;
- [x] atomic replacement;
- [x] explicit package/single upload mode;
- [x] oversized POST routing tetap teridentifikasi;
- [x] separate error bag;
- [x] extension-based DOCX/XLSX validation;
- [x] PHP upload-limit display;
- [x] storage lifecycle template dan generator sama-sama disk `local`;
- [x] upload regression masuk `SPJ Critical`.

Remaining RVR:

- [ ] template resmi/aktual sekolah;
- [ ] visual fidelity Word/Excel/PDF;
- [ ] print area/page break/header-footer/tabel dinamis;
- [ ] hasil cetak aktual;
- [ ] pembukaan output pada Microsoft Word/Excel/PDF viewer target.

---

## P0-03 — Numbering + lifecycle

**Status: FUNCTIONAL PASS.**

- [x] idempotent numbering;
- [x] duplicate active number guard;
- [x] source/order canonical;
- [x] NUMBERED/FINAL lock;
- [x] cancel/reissue/reopen history;
- [x] preview/download no numbering side effect;
- [x] READY category change revalidation.

---

## P0-04 — Authorization

**Status: FUNCTIONAL PASS pada jalur yang diregresikan.**

- [x] VIEWER read-only;
- [x] OPERATOR mutation operasional;
- [x] ADMINISTRATOR mutation sensitif;
- [x] tenant/context guard terpisah dari role guard;
- [x] template/configuration sensitive routes guarded.

---

## P0-05 — Safe sync + reconciliation

**Status: FUNCTIONAL PASS.**

- [x] source sync mempertahankan overlay manual;
- [x] source diff menghasilkan reconciliation;
- [x] source missing/returning mempertahankan identity;
- [x] NUMBERED/FINAL tidak dimutasi diam-diam;
- [x] field-level before/after diff;
- [x] stale reconciliation resolution guard.

---

## P0-06 — Tenant/context isolation

**Status: FUNCTIONAL PASS.**

Boundary canonical:

```text
School + Fiscal Year + Fund Source
```

- [x] cross-school guard;
- [x] cross-year guard;
- [x] cross-fund-source guard;
- [x] previous/next Paket context boundary;
- [x] forged resource ID guard;
- [x] Generic ARKAS Importer tenant boundary.

---

## P0-07 — APP DATA / backup / reset / restore

**Status: FUNCTIONAL PASS / INSTALLED-RUNTIME DEFERRED.**

- [x] primary database deletion guard;
- [x] tenant-only reset;
- [x] WAL/SHM cleanup;
- [x] sqlite_sequence reset;
- [x] backup integrity check;
- [x] restore verified copy;
- [x] rollback snapshot;
- [x] corrupt backup rejection;
- [x] rollback on post-restore failure;
- [x] same-second backup uniqueness;
- [x] switch tenant after restore.

Installed Windows runtime verification tetap DEFERRED pada fokus kerja sekarang.

---

## P0-08 — Generic ARKAS Importer

**Status: FUNCTIONAL HARDENING PASS / READY FOR OPERATOR DATA TEST.**

- [x] shared deterministic source key;
- [x] tenant boundary;
- [x] Upsert / Incremental / Full Refresh;
- [x] reconciliation preview read-only;
- [x] source-empty semantics;
- [x] schema drift blocking;
- [x] background tenant activation;
- [x] shared resource lock;
- [x] created-at preservation;
- [x] semantic metrics.

Scale/performance lanjutan:

- [ ] Bridge-side incremental delta fetch;
- [ ] pagination/evaluation di atas row limit `100000`.

---

# P1 — Real Data, Output, dan Operational Quality

## P1-01 — Audit 66 READY package real-data

Gunakan jalur read-only `spj:audit-quarter` sebelum mutation apa pun.

- [ ] BARANG requirements dan procurement policy;
- [ ] JASA recipient/reconciliation;
- [ ] HONOR recipient/detail consistency;
- [ ] KONSUMSI participant semantics;
- [ ] PEMELIHARAAN materials+wages/link requirements;
- [ ] SiPLah policy/reference yang memang applicable;
- [ ] source/reconciliation flags;
- [ ] blank item description bila masih ada;
- [ ] duplicate/orphan/no_bukti state;
- [ ] canonical numbering blocker report.

## P1-02 — JASA_LAINNYA multi-penerima generated-document E2E

- [ ] gross/tax/net tiap penerima;
- [ ] dokumen per penerima bila applicable;
- [ ] preview/download/final multi-penerima;
- [ ] aggregate reconciliation pada output nyata.

## P1-03 — PEMELIHARAAN bahan + upah full-document QA

- [ ] material dari transaksi bahan;
- [ ] pekerja/upah dari transaksi upah;
- [ ] linkage sesuai active context;
- [ ] RAB/SPK/kuitansi/A2 konsisten;
- [ ] source BKU tetap immutable.

## P1-04 — SiPLah generated-document E2E

**Core procurement policy sudah FUNCTIONAL PASS.** Fokus tersisa adalah output dan browser flow, bukan membangun ulang model SiPLah.

- [x] SiPLah adalah channel, bukan kategori;
- [x] marketplace/order/invoice/reference persistence;
- [x] nomor marketplace dan Surat Pesanan internal dipisahkan;
- [x] BARANG SiPLah tidak dipaksa memakai internal purchase-order requirement yang tidak applicable;
- [x] placeholder/policy core mempunyai regression;
- [ ] generated-document E2E memakai data SiPLah;
- [ ] official-template output QA;
- [ ] browser reload/category switching/payment-method consistency.

## P1-05 — Browser QA desktop/laptop

- [ ] category/payment controls;
- [ ] Data Umum layout;
- [ ] Rincian Pajak tab;
- [ ] automatic number readonly;
- [ ] compact table/pagination;
- [ ] previous/next Paket context;
- [ ] template upload package/single UX.

## P1-06 — Audit trail operasional E2E

Pastikan draft/update/ready/numbering/cancel/reissue/final/reopen/reconcile/reset/restore mempunyai actor, time, tenant context, entity, action, dan description.

## P1-07 — Employee identity + participant roster real-data verification

**Unified identity core sudah FUNCTIONAL PASS.** Fokus tersisa adalah edge case nyata dan UX participant.

Kontrak tetap:

```text
Auto-fill KONSUMSI = Employee.source_type DAPODIK
Participant manual  = allowed
```

Sudah dibuktikan regression:

- [x] ARKAS/PTK + Dapodik identity fusion dengan strong identifier;
- [x] ambiguous normalized-name tidak silent merge;
- [x] NUPTK/unique normalized-name matching policy;
- [x] source provenance dipertahankan;
- [x] operator-locked row tidak disapu sync;
- [x] unified employee master.

Tersisa:

- [ ] audit duplicate/ambiguous identity pada data sekolah nyata;
- [ ] pastikan UI auto-fill KONSUMSI tetap Dapodik-only;
- [ ] participant roster/operator UX;
- [ ] same-name/different-identifier real-data review.

---

# P2 — Polish & Maintainability

- [ ] selesaikan 2 advisory Pint issues;
- [ ] field-level validation UX;
- [ ] GUI/compatibility cleanup;
- [ ] icon/action consistency;
- [ ] performance profiling;
- [ ] Bridge generated `bin/obj` cleanup;
- [ ] report foundation.

Mobile/responsive penuh bukan blocker release target operator laptop/desktop saat ini.

---

# Aturan pengerjaan

- jangan mengubah ARKAS/BKU source hanya untuk membuat test/audit lulus;
- jangan membuat data SPPD/vendor/penerima/template fiktif;
- audit real-data dilakukan read-only sebelum mutation;
- numbering real-data mengikuti canonical order dan berhenti pada blocker legitimate;
- mutation real-data hanya pada isolated copy;
- perubahan domain/business rule baru dicatat di `SPJ_DESIGN_DECISIONS.md`;
- docs status selalu membedakan FUNCTIONAL PASS, REAL-DATA VERIFIED, RVR, dan DEFERRED.
