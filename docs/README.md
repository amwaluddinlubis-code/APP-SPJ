# SPJ BOSP Web — Dokumentasi

Terakhir diperbarui: **2026-09-11**

Dokumen ini adalah indeks dokumentasi untuk branch pengembangan aktif `gui-standardization`. Tujuannya adalah membedakan **sumber status aktif**, **kontrak permanen**, **rencana kerja**, **panduan teknis**, dan **dokumen historis** agar informasi lama tidak mengalahkan kondisi project terbaru.

## Urutan sumber kebenaran

Jika ada perbedaan isi antar dokumen, gunakan urutan berikut:

1. `CURRENT_PROGRESS.md` — status release, checkpoint, evidence, blocker, RVR, dan deferred work terbaru.
2. `DEVELOPMENT_ROADMAP.md` — urutan pekerjaan aktif dan next milestone.
3. `SPJ_DESIGN_DECISIONS.md` — keputusan bisnis/domain permanen yang tidak boleh diregresikan.
4. `ARCHITECTURE_COMPLETE.md` — arsitektur, boundary tenant, dan pembagian ownership aplikasi.
5. Dokumen feature/panduan spesifik di bawah ini.
6. Dokumen berstatus `HISTORICAL`, `SUPERSEDED`, atau `ARCHIVED` hanya menjadi referensi sejarah dan tidak boleh dipakai untuk menentukan status project saat ini.

Root `README.md` adalah entry point project, bukan pengganti `CURRENT_PROGRESS.md` untuk status release rinci.

## Status release saat indeks ini dibuat

Release gate functional terbaru yang menjadi baseline dokumentasi:

```text
commit : 0df9b2ffbf14ed191e36c063e6355f9cb63c4a66
subject: test: gate template upload routing regression
result : PASS — 243 tests / 1848 assertions
```

Status keseluruhan tetap **belum final release**. Functional core sudah kuat, tetapi real-data verification dan official-template visual/runtime verification masih mempunyai pekerjaan terbuka.

## Dokumen aktif utama

| Dokumen | Peran |
|---|---|
| `CURRENT_PROGRESS.md` | Sumber status release utama. |
| `DEVELOPMENT_ROADMAP.md` | Prioritas dan urutan pekerjaan berikutnya. |
| `SPJ_DESIGN_DECISIONS.md` | Kontrak bisnis/domain permanen. |
| `ARCHITECTURE_COMPLETE.md` | Arsitektur aplikasi dan tenant boundary. |
| `USER_SCENARIOS.md` | Skenario penggunaan/operator. |
| `GUI_STANDARDIZATION.md` | Kontrak GUI dan standardisasi layout. |
| `CSS_USAGE_GUIDE.md` | Kontrak CSS/theme. |
| `UI_ICON_MIGRATION.md` | Strategi icon canonical dan compatibility migration. |

## Importer, template, dan generator dokumen

| Dokumen | Peran |
|---|---|
| `ARKAS_IMPORTER.md` | Kontrak dan pipeline Generic ARKAS Importer. |
| `DOCUMENT_TEMPLATE_PLACEHOLDERS.md` | Placeholder template dokumen. |
| `P0_VERIFICATION_KIT.md` | Kit/verifikasi P0 dan release-safety checks. |
| `P0_01_SOURCE_AUDIT.md` | Audit source untuk P0-01 dan evidence terkait. |

Status functional upload template terkini berada di `CURRENT_PROGRESS.md`; detail implementation terbaru mencakup explicit upload mode, separate error bag, extension validation, PHP upload-limit handling, dan lifecycle file pada disk `local` yang sama dengan generator.

## Feature plan / RVR aktif

| Dokumen | Status |
|---|---|
| `SIPLAH_MVP_PLAN.md` | Feature plan; status final harus dibaca bersama `CURRENT_PROGRESS.md`. |
| `MOBILE_VISUAL_QA_TODO.md` | TODO / RVR. Mobile penuh **bukan release blocker** untuk target operator desktop/laptop saat ini, tetapi aplikasi belum boleh disebut mobile-verified sebelum checklist selesai. |

## Dokumen historis / arsip

Dokumen berikut sengaja dipertahankan untuk jejak keputusan, tetapi **bukan sumber status aktif**:

| Dokumen | Status |
|---|---|
| `DEVELOPMENT_HANDOFF_2026-09-05.md` | HISTORICAL / SUPERSEDED. |
| `URGENT_TRANSACTION_SPJ_MIGRATION.md` | PASS / ARCHIVED; ownership migration sudah selesai. |

Jangan mengambil daftar next action dari dokumen historis bila bertentangan dengan `DEVELOPMENT_ROADMAP.md`.

## Kontrak aktif yang harus konsisten di semua dokumentasi

- ARKAS/BKU adalah source readonly; data operator SPJ adalah overlay.
- Boundary tenant canonical: `School + Fiscal Year + Fund Source`.
- Detail Transaksi hanya menulis `item_description` untuk rincian item.
- Paket SPJ memiliki ownership kategori, procurement/payment channel, penerima/vendor, detail kategori, numbering, template, preview/download, lifecycle, dan finalisasi.
- Kategori canonical: `BARANG`, `KONSUMSI`, `PEMELIHARAAN`, `JASA_LAINNYA`, `SPPD`, `HONOR_PEGAWAI`.
- SiPLah adalah procurement/payment channel, bukan kategori SPJ.
- Preview/download tidak boleh menerbitkan nomor baru.
- `NUMBERED`/`FINAL` tidak boleh diedit melalui mutation normal.
- Jangan fabrikasi source data, penerima, vendor, SPPD, atau template untuk memaksa coverage real-data.

## Aturan pemeliharaan dokumentasi

Ketika implementation berubah:

1. update `CURRENT_PROGRESS.md` bila status/evidence/release risk berubah;
2. update `DEVELOPMENT_ROADMAP.md` bila prioritas atau next milestone berubah;
3. update `SPJ_DESIGN_DECISIONS.md` hanya untuk keputusan bisnis/domain yang bersifat permanen;
4. update dokumentasi feature terkait bila contract teknisnya berubah;
5. jangan mengubah dokumen historis menjadi sumber status baru — beri banner superseded/archive bila perlu;
6. bedakan selalu `FUNCTIONAL PASS`, `REAL-DATA VERIFIED`, `RVR`, dan `DEFERRED`;
7. checkpoint CI/test yang ditulis harus menunjuk evidence yang benar-benar pernah dijalankan, bukan asumsi dari HEAD terbaru.
