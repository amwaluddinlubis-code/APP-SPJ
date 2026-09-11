# SPJ BOSP Web — Dokumentasi

Terakhir diperbarui: **2026-09-11**

Dokumen ini adalah indeks dokumentasi untuk branch aktif `gui-standardization`. Tujuannya adalah membedakan sumber status, kontrak permanen, arsitektur, panduan teknis, verification guide, RVR, dan arsip agar dokumen lama tidak mengalahkan kondisi project terbaru.

## Urutan sumber kebenaran

Jika ada perbedaan antar dokumen, gunakan urutan berikut:

1. `CURRENT_PROGRESS.md` — status release, evidence, blocker, RVR, dan deferred work terbaru.
2. `DEVELOPMENT_ROADMAP.md` — urutan pekerjaan aktif dan milestone berikutnya.
3. `SPJ_DESIGN_DECISIONS.md` — keputusan bisnis/domain permanen.
4. `ARCHITECTURE_COMPLETE.md` — arsitektur aktif, boundary tenant, ownership, dan layer aplikasi.
5. Feature guide/panduan teknis yang relevan.
6. Dokumen `HISTORICAL`, `SUPERSEDED`, atau `ARCHIVED` hanya untuk jejak sejarah.

Root `README.md` adalah entry point project, bukan pengganti `CURRENT_PROGRESS.md`.

## Functional gate aktif

Angka gate hidup di `P0_VERIFICATION_KIT.md` §1 (tidak disalin ke sini agar tidak divergen).

Status keseluruhan tetap **belum final release-ready** karena real-data verification, official-template output, dan browser/runtime RVR masih aktif.

## Dokumen aktif utama

| Dokumen | Peran / status |
|---|---|
| `CURRENT_PROGRESS.md` | **AUTHORITATIVE STATUS** — sumber status release utama. |
| `DEVELOPMENT_ROADMAP.md` | **ACTIVE** — prioritas dan urutan pekerjaan. |
| `DOCUMENTATION_MAINTENANCE.md` | **ACTIVE / REQUIRED** — Definition of Done dokumentasi, matriks impact, evidence rules, dan aturan wajib untuk semua AI/coding agent. |
| `SPJ_DESIGN_DECISIONS.md` | **ACTIVE CONTRACT** — aturan bisnis/domain permanen. |
| `ARCHITECTURE_COMPLETE.md` | **ACTIVE / REFRESHED 2026-09-11** — arsitektur aktif. |
| `SYNCHRONIZATION.md` | **ACTIVE TECHNICAL GUIDE** — canonical sync ARKAS/BKU, Dapodik, reconciliation, employee identity, tenant/concurrency guard, dan safe-sync semantics. |
| `NUMBERING_CORRECTION_AND_ROLLBACK.md` | **IMPLEMENTED / FUNCTIONAL GATE PASS** — cancel individual, rollback numbering, cancel numbering triwulan, reset sequence, dan aturan koreksi data setelah NUMBERED. |
| `USER_SCENARIOS.md` | **ACTIVE** — alur operator dan ownership workspace. |
| `GUI_STANDARDIZATION.md` | **ACTIVE CONTRACT** — kontrak GUI/layout, theme, primitive, icon canonical, dan aturan evidence visual. |
| `GUI_RUNTIME_QA.md` | **ACTIVE / RVR CHECKLIST** — checklist browser desktop/laptop dan mobile/tablet untuk GUI-AUDIT-12/13. |
| `CSS_USAGE_GUIDE.md` | **ACTIVE** — CSS/theme contract. |
| `UI_ICON_MIGRATION.md` | **ACTIVE MIGRATION GUIDE** — icon canonical + compatibility bridge. |

Semua contributor dan AI/coding agent wajib membaca `DOCUMENTATION_MAINTENANCE.md` dan melakukan **Documentation Impact Review** sebelum menyatakan pekerjaan selesai.

## Importer, generator, dan verification

| Dokumen | Peran / status |
|---|---|
| `ARKAS_IMPORTER.md` | **ACTIVE** — Generic ARKAS Importer; functional hardening PASS, operator-data test berikutnya. |
| `DOCUMENT_TEMPLATE_PLACEHOLDERS.md` | **ACTIVE** — placeholder template. |
| `P0_VERIFICATION_KIT.md` | **ACTIVE / REFRESHED** — command/gate release-safety dan real-tenant audit. |
| `P0_01_SOURCE_AUDIT.md` | **ACTIVE REAL-DATA GUIDE** — deterministic six-category sudah PASS; dokumen sekarang fokus audit real-data read-only. |

Untuk pekerjaan sinkronisasi, baca `SYNCHRONIZATION.md` lebih dulu. Gunakan `ARKAS_IMPORTER.md` bila perubahan khusus menyentuh Generic ARKAS Importer/profile-driven import.

Untuk pekerjaan penomoran/koreksi setelah NUMBERED, baca `NUMBERING_CORRECTION_AND_ROLLBACK.md` sebelum mengubah use case numbering atau lifecycle.

Untuk penutupan standardisasi GUI, baca `GUI_STANDARDIZATION.md` lalu jalankan `GUI_RUNTIME_QA.md`. Source/CI PASS tidak boleh diubah menjadi browser/mobile PASS tanpa runtime evidence.

## Feature verification / RVR aktif

| Dokumen | Status |
|---|---|
| `SIPLAH_MVP_PLAN.md` | **LEGACY FILENAME / ACTIVE VERIFICATION GUIDE** — core SiPLah sudah FUNCTIONAL PASS; generated-document E2E + official-template output masih RVR. |
| `GUI_RUNTIME_QA.md` | **RVR ACTIVE** — GUI-AUDIT-12 desktop/laptop dan GUI-AUDIT-13 mobile/tablet. |
| `MOBILE_VISUAL_QA_TODO.md` | **LEGACY/ADDITIONAL MOBILE QA TODO** — tetap dapat dipakai sebagai detail tambahan; checklist canonical penutupan GUI sekarang `GUI_RUNTIME_QA.md`. |

`SIPLAH_MVP_PLAN.md` sengaja belum di-rename agar link lama tidak rusak. Jangan membaca nama file sebagai tanda bahwa core SiPLah masih berada pada fase MVP awal.

## Dokumen historis / arsip

Arsip yang pekerjaannya sudah selesai (`DEVELOPMENT_HANDOFF_2026-09-05.md`, `URGENT_TRANSACTION_SPJ_MIGRATION.md`) telah dihapus dari `docs/` agar tidak menyesatkan. Jejaknya tetap tersedia di git history bila diperlukan audit.

## Kontrak aktif lintas dokumentasi

- ARKAS/BKU = source readonly; operator SPJ = overlay.
- Source sync tidak menghapus overlay manual.
- Source missing/returning mempertahankan identity dan pekerjaan operator.
- Perubahan source setelah pekerjaan operator dapat memerlukan reconciliation; NUMBERED/FINAL tidak dimutasi diam-diam.
- Boundary tenant = `School + Fiscal Year + Fund Source`.
- Sequence numbering terisolasi per sumber dana.
- Detail Transaksi hanya menulis `item_description`.
- Koreksi `item_description` tetap boleh pada NUMBERED tanpa membatalkan nomor atau mengubah sequence; FINAL tetap terkunci.
- Paket SPJ memiliki ownership kategori, procurement/payment channel, penerima/vendor, detail kategori, numbering, template, output, lifecycle, dan finalisasi.
- Perubahan kategori, data pembayaran, atau Isian Manual pada NUMBERED wajib didahului rollback numbering yang sesuai.
- Kategori canonical: `BARANG`, `KONSUMSI`, `PEMELIHARAAN`, `JASA_LAINNYA`, `SPPD`, `HONOR_PEGAWAI`.
- SiPLah adalah channel, bukan kategori; hanya berlaku untuk BARANG. Radio SiPLah/Non SiPLah UI-only (tidak menulis `payment_method`).
- READY + category benar-benar berubah => DRAFT untuk revalidation.
- Preview/download tidak menerbitkan nomor baru.
- Cancel individual mempertahankan nomor `CANCELLED` sebagai history permanen dan sequence tidak mundur.
- Rollback numbering melepas nomor aktif dari titik rollback sampai ekor sequence; nomor yang dilepas boleh dipakai kembali.
- Rollback tidak boleh melintasi nomor `CANCELLED` individual permanen.
- Cancel Penomoran Triwulan berjalan mundur `TW4 -> TW3 -> TW2 -> TW1` pada context tenant+tahun+sumber dana yang sama.
- Full quarter reset ditolak bila target memiliki nomor SPJ cancelled individual permanen.
- Operational audit rollback tetap dipertahankan walaupun history numbering domain yang di-rollback dilepas.
- Employee identity tidak boleh silent-merge orang berbeda hanya karena normalized name ambigu.
- Operator-locked Employee tidak boleh ditimpa source sync.
- Master Pegawai menyatu (ARKAS + Dapodik + Manual); auto-fill KONSUMSI/SPPD memakai roster menyatu, participant manual diperbolehkan.
- Icon canonical dimiliki `<x-ui.icon>`; `<x-ui-icon>` hanya compatibility adapter dan tidak boleh memiliki registry SVG sendiri.
- Source-level responsive regression bukan bukti browser visual PASS.
- Audit database real-data dilakukan read-only sebelum mutation.
- Jangan fabrikasi source data, penerima, vendor, SPPD, atau template untuk memaksa coverage.

## Klasifikasi status yang wajib dipakai

```text
FUNCTIONAL PASS
REAL-DATA VERIFIED
RVR
DEFERRED
HISTORICAL / SUPERSEDED / ARCHIVED
```

Jangan memakai kata “selesai” bila yang tersedia hanya source path tanpa regression/runtime evidence.

## Aturan pemeliharaan dokumentasi

Aturan lengkap dan checklist canonical berada di:

```text
docs/DOCUMENTATION_MAINTENANCE.md
```

Ringkasannya:

1. dokumentasi adalah bagian Definition of Done;
2. setiap perubahan wajib menjalani Documentation Impact Review;
3. status/evidence diperbarui di `CURRENT_PROGRESS.md`;
4. prioritas/milestone diperbarui di `DEVELOPMENT_ROADMAP.md`;
5. business rule, architecture, user flow, sync, numbering, GUI, dan feature guide diperbarui sesuai matriks dampak;
6. dokumen baru/status dokumen berubah wajib direfleksikan di indeks ini;
7. dokumentasi tidak boleh mengklaim PASS/verified melebihi evidence aktual;
8. instruksi AI agent tidak boleh hard-code prioritas feature yang cepat berubah;
9. dokumentasi usang atau kontradiktif dianggap defect dan harus diperbarui/diarsipkan/dihapus dengan aman.
