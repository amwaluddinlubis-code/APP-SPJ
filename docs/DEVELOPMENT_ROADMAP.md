# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: **2026-09-11**

Roadmap ini memuat urutan pekerjaan aktif pada branch `gui-standardization`. Status rinci dan evidence ada di `CURRENT_PROGRESS.md`; keputusan bisnis permanen berada di `SPJ_DESIGN_DECISIONS.md`.

## Release gate functional

Gate tunggal ada di `docs/README.md` (disalin dari `CURRENT_PROGRESS.md`). Jangan menyalin angka gate ke sini; angka di bawah ini hanya penanda waktu penulisan dan wajib dibaca ulang dari sana:

```text
Lihat docs/README.md → Functional gate aktif.
```

`FUNCTIONAL PASS` tidak sama dengan final production verification. `RVR` tetap membutuhkan real-data, browser, template resmi, atau runtime evidence sesuai konteks.

---

# P0 — Core Release Safety

## P0-01 — Six-category E2E

**Status: FUNCTIONAL PASS / REAL-DATA VERIFICATION ACTIVE / INSTALLED-RUNTIME DEFERRED.**

Evidence selesai ada di `CURRENT_PROGRESS.md` (tidak disalin ke sini agar tidak divergen).

Pekerjaan aktif:

- [ ] audit read-only seluruh 66 READY package dengan `spj:audit-quarter`;
- [ ] kelompokkan blocker legitimate per kategori dan canonical numbering order;
- [ ] koreksi hanya operator overlay yang mempunyai evidence;
- [ ] setelah clean, uji numbering pada isolated copy;
- [ ] pertahankan source transaction/item immutable.

Evidence selesai ada di `CURRENT_PROGRESS.md`. Jangan membuat data fiktif (aturan pengerjaan di bawah).

---

## P0-02 — Generator dokumen + template upload

**Status: FUNCTIONAL GENERATOR PASS / TEMPLATE UPLOAD HARDENED PASS / OFFICIAL-TEMPLATE VISUAL RVR.**

Evidence selesai ada di `CURRENT_PROGRESS.md`.

Remaining RVR:

- [ ] template resmi/aktual sekolah;
- [ ] visual fidelity Word/Excel/PDF;
- [ ] print area/page break/header-footer/tabel dinamis;
- [ ] hasil cetak aktual;
- [ ] pembukaan output pada Microsoft Word/Excel/PDF viewer target.

---

## P0-03 — Numbering + lifecycle

**Status: FUNCTIONAL PASS.** Panduan kanonis: `NUMBERING_CORRECTION_AND_ROLLBACK.md`. Evidence selesai ada di `CURRENT_PROGRESS.md`.

---

## P0-04 — Authorization

**Status: FUNCTIONAL PASS pada jalur yang diregresikan.** Evidence selesai ada di `CURRENT_PROGRESS.md`.

---

## P0-05 — Safe sync + reconciliation

**Status: FUNCTIONAL PASS.** Panduan kanonis: `SYNCHRONIZATION.md`. Evidence selesai ada di `CURRENT_PROGRESS.md`.

---

## P0-06 — Tenant/context isolation

**Status: FUNCTIONAL PASS.**

Boundary canonical:

```text
School + Fiscal Year + Fund Source
```

Evidence selesai ada di `CURRENT_PROGRESS.md`.

---

## P0-07 — APP DATA / backup / reset / restore

**Status: FUNCTIONAL PASS / INSTALLED-RUNTIME DEFERRED.**

Evidence selesai ada di `CURRENT_PROGRESS.md`.

Installed Windows runtime verification tetap DEFERRED pada fokus kerja sekarang.

---

## P0-08 — Generic ARKAS Importer

**Status: FUNCTIONAL HARDENING PASS / READY FOR OPERATOR DATA TEST.**

Evidence selesai ada di `CURRENT_PROGRESS.md`. Panduan kanonis: `SYNCHRONIZATION.md`.

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
- [ ] SiPLah policy/reference yang memang applicable (hanya BARANG);
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
- [ ] browser reload/category switching/payment-method consistency, termasuk radio SiPLah/Non SiPLah yang tetap UI-only.

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
Auto-fill KONSUMSI/SPPD = master Pegawai menyatu (ARKAS + Dapodik + Manual)
Participant manual      = allowed
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
- [ ] pastikan UI auto-fill KONSUMSI/SPPD memakai roster menyatu;
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
