# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: **2026-09-11**

Roadmap ini memuat urutan pekerjaan aktif pada branch `gui-standardization`. Status rinci dan evidence ada di `docs/CURRENT_PROGRESS.md`. Aturan bisnis permanen tetap berada di `docs/SPJ_DESIGN_DECISIONS.md`.

## Release gate terbaru

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

`FUNCTIONAL PASS` berarti source + deterministic regression sudah membuktikan behavior. `RVR` tetap membutuhkan real-template/real-data/operator evidence. Installed-runtime verification saat ini **DEFERRED** dari fokus kerja aktif, bukan dianggap PASS.

---

# P0 — Core Release Safety

## P0-01 — Six-category E2E

**Status: FUNCTIONAL PASS / REAL-DATA VERIFICATION STARTED / INSTALLED-RUNTIME DEFERRED.**

Selesai:

- [x] BARANG sampai FINAL + preview/download;
- [x] KONSUMSI sampai FINAL + preview/download;
- [x] PEMELIHARAAN sampai FINAL + preview/download;
- [x] JASA_LAINNYA sampai FINAL + preview/download;
- [x] SPPD sampai FINAL + preview/download;
- [x] HONOR_PEGAWAI sampai FINAL + preview/download;
- [x] numbering/finalization/preview/download melalui runtime path aplikasi;
- [x] real XLSX/PDF regression;
- [x] preview/download tidak mengalokasikan nomor;
- [x] category READY → DRAFT revalidation regression;
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

Next real-data work:

- [ ] audit read-only seluruh 66 READY package terhadap current validation rules;
- [ ] identifikasi blocker legitimate per canonical numbering order;
- [ ] koreksi hanya operator overlay/data yang memang mempunyai evidence;
- [ ] jangan fabrikasi penerima/vendor/SPPD/template;
- [ ] setelah data clean, lanjutkan numbering pada isolated copy dalam urutan canonical;
- [ ] pertahankan source transaction/item immutable.

Catatan: SPPD nyata tersedia pada fiscal year 2025, bukan 2026. Tidak boleh dibuat SPPD 2026 hanya untuk memenuhi six-category real-data coverage.

---

## P0-02 — Generator dokumen + template upload

**Status: FUNCTIONAL GENERATOR PASS / TEMPLATE UPLOAD HARDENED PASS / OFFICIAL-TEMPLATE VISUAL RVR.**

Generator selesai secara functional:

- [x] DOCX/XLSX real artifact;
- [x] PDF real artifact;
- [x] render preflight;
- [x] unresolved placeholder guard;
- [x] generated artifact validation;
- [x] multi-template XLSX/PDF;
- [x] preview/download tanpa numbering side effect;
- [x] common placeholder enam kategori;
- [x] invalid upload tidak mengganti template aktif;
- [x] atomic replacement.

Upload halaman template sekarang selesai secara functional:

- [x] explicit `?upload=package` mode;
- [x] explicit `?upload=single` mode;
- [x] mode tetap benar jika oversized POST body dibuang PHP;
- [x] separate error bag untuk package dan single upload;
- [x] extension-based DOCX/XLSX validation tanpa ketergantungan MIME Windows;
- [x] UI menampilkan `upload_max_filesize`, `post_max_size`, dan effective limit;
- [x] oversized request menghasilkan pesan batas PHP;
- [x] save/validate/download/delete memakai disk `local` yang sama dengan generator;
- [x] regression `DocumentTemplateUploadValidationTest`;
- [x] regression `DocumentTemplateUploadRoutingRegressionTest`;
- [x] kedua regression masuk `SPJ Critical`.

Remaining RVR:

- [ ] template resmi/aktual untuk document type yang dipakai sekolah;
- [ ] visual fidelity Word/Excel/PDF;
- [ ] print area/page break/header-footer/tabel dinamis;
- [ ] hasil cetak aktual;
- [ ] output dibuka di Microsoft Word/Excel/PDF viewer target.

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

Boundary:

```text
School + Fiscal Year + Fund Source
```

- [x] cross-school guard;
- [x] cross-year guard;
- [x] cross-fund-source guard;
- [x] prev/next Paket context boundary;
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

Installed Windows runtime verification tetap DEFERRED untuk fokus sekarang.

---

## P0-08 — Generic ARKAS Importer

**Status: FUNCTIONAL HARDENING PASS / READY FOR OPERATOR DATA TEST.**

- [x] stable shared source key;
- [x] Upsert / Incremental / Full Refresh;
- [x] reconciliation preview read-only;
- [x] stable-key guard;
- [x] source-empty semantics;
- [x] schema drift block;
- [x] background tenant activation;
- [x] shared resource lock;
- [x] created-at preservation;
- [x] semantic metrics.

Scale/performance:

- [ ] Bridge-side incremental delta fetch;
- [ ] pagination/evaluation above row limit `100000`.

---

# P1 — Feature Completeness & Operational Quality

Urutan prioritas aktif setelah template upload ditutup:

## P1-01 — Audit 66 READY package real-data

- [ ] validate BARANG requirements;
- [ ] validate JASA recipient/vendor requirements;
- [ ] validate HONOR recipient/detail consistency;
- [ ] validate KONSUMSI participant semantics;
- [ ] validate PEMELIHARAAN materials+wages/link requirements;
- [ ] validate SiPLah reference requirements;
- [ ] validate source/reconciliation flags;
- [ ] validate blank item descriptions bila masih relevan;
- [ ] validate duplicate/orphan/no_bukti state;
- [ ] produce canonical numbering blocker report.

## P1-02 — JASA_LAINNYA multi-penerima E2E

- [ ] gross/tax/net tiap penerima;
- [ ] dokumen per penerima bila applicable;
- [ ] preview/download/final multi-penerima;
- [ ] aggregate reconciliation.

## P1-03 — PEMELIHARAAN bahan + upah

- [ ] material dari transaksi bahan;
- [ ] pekerja/upah dari transaksi upah;
- [ ] linkage sesuai active context;
- [ ] RAB/SPK/kuitansi/A2 konsisten;
- [ ] source BKU tetap immutable.

## P1-04 — SiPLah E2E

- [ ] source SiPLah authoritative;
- [ ] procurement channel UI benar;
- [ ] marketplace order/invoice/payment reference;
- [ ] internal purchase order tidak diwajibkan untuk SiPLah;
- [ ] output SiPLah benar.

## P1-05 — Browser QA desktop/laptop

- [ ] category/payment controls;
- [ ] Data Umum layout;
- [ ] Rincian Pajak tab;
- [ ] automatic number readonly;
- [ ] compact table/pagination;
- [ ] prev/next Paket context.

## P1-06 — Audit trail operasional

Pastikan aksi draft/update/ready/numbering/cancel/reissue/final/reopen/reconcile/reset/restore mempunyai actor, time, tenant context, entity, action, dan description.

## P1-07 — Employee identity / participant roster

Kontrak tetap:

```text
Auto-fill KONSUMSI = Employee.source_type DAPODIK
Participant manual  = allowed
```

- [ ] UI tidak memperluas auto-fill ke semua Employee;
- [ ] normalized-name-only tidak silent merge orang berbeda;
- [ ] NIP/NUPTK dipertahankan;
- [ ] regression same-name/different-identifier.

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
- numbering real-data harus mengikuti canonical order dan berhenti pada blocker legitimate;
- mutasi real-data selalu di isolated copy;
- perubahan domain/business rule baru harus dicatat di `docs/SPJ_DESIGN_DECISIONS.md`;
- docs status harus membedakan FUNCTIONAL PASS, REAL-DATA evidence, RVR, dan DEFERRED.
