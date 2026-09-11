# App SPJ BOS — AI Development Rules

Dokumen ini adalah aturan kerja untuk semua AI/coding agent pada repository APP-SPJ.

Baca bersama `AGENTS.md` dan dokumentasi project sebelum mengubah source.

## 1. Sumber status dan prioritas

Jangan hard-code prioritas aktif di file ini.

Setiap agent wajib menentukan kondisi project dari:

```text
docs/CURRENT_PROGRESS.md
docs/DEVELOPMENT_ROADMAP.md
```

`CURRENT_PROGRESS.md` adalah sumber status/evidence utama. `DEVELOPMENT_ROADMAP.md` adalah sumber prioritas/milestone utama.

Jika chat lama, prompt lama, handoff lama, atau file historis bertentangan dengan dua dokumen tersebut dan source/test terbaru, gunakan source aktif + evidence terbaru dan sinkronkan dokumentasi sesuai `docs/DOCUMENTATION_MAINTENANCE.md`.

## 2. Dokumentasi adalah bagian Definition of Done

Sebelum membuat perubahan, baca:

```text
AGENTS.md
.ai/rules/index.md
docs/README.md
docs/DOCUMENTATION_MAINTENANCE.md
docs/CURRENT_PROGRESS.md
docs/DEVELOPMENT_ROADMAP.md
```

Lalu baca domain/feature guide yang relevan.

Sebelum menyatakan pekerjaan selesai, wajib melakukan **Documentation Impact Review** sesuai `docs/DOCUMENTATION_MAINTENANCE.md`.

Behavior change tanpa audit dokumentasi dianggap belum selesai.

Jangan mengklaim test, CI, browser/runtime, atau real-data verification tanpa evidence aktual.

## 3. Source data ownership

ARKAS/BKU adalah sumber data readonly.

AI tidak boleh membuat perubahan yang menyebabkan sinkronisasi:

- menimpa data manual/operator;
- menghapus package SPJ manual;
- menghapus `receipt_recipient_name`;
- menghapus `payment_description` operator;
- mengganti data final tanpa reconciliation/lifecycle yang sah.

`manual_description` tidak digunakan dan tidak boleh dihidupkan kembali.

Boundary tenant canonical:

```text
School + Fiscal Year + Fund Source
```

## 4. Kategori dan SiPlah

Kategori SPJ canonical:

```text
BARANG
KONSUMSI
PEMELIHARAAN
SPPD
HONOR_PEGAWAI
JASA_LAINNYA
```

SiPlah bukan kategori SPJ. Jangan menambah `spj_category = SIPLAH`.

Perlakukan SiPlah sebagai karakteristik/channel proses pembelian sesuai kontrak aktif di dokumentasi project.

Sebelum menambah field/migration baru, audit model, migration, ownership source/operator, transaction detail, package SPJ, generator/template, dan tests existing. Jangan membuat field yang menduplikasi makna field canonical.

## 5. SPJ lifecycle invariants

Jangan mengubah kontrak lifecycle tanpa instruksi user dan update domain docs.

Kontrak penting saat ini:

- preview/download tidak boleh membuat nomor secara diam-diam;
- numbering dilakukan setelah data siap;
- urutan numbering SPJ harus mengikuti source order canonical ARKAS/BKU, bukan urutan insert lokal;
- `NUMBERED` terkunci untuk perubahan manual/package, kecuali koreksi `item_description` yang memang diizinkan oleh kontrak aktif;
- `FINAL` tetap terkunci;
- cancel individual mempertahankan nomor `CANCELLED` sebagai history permanen dan sequence tidak mundur;
- rollback numbering adalah operasi berbeda: melepas active tail number untuk dipakai ulang;
- cancel/rollback triwulan mengikuti dependency mundur pada scope tenant+tahun+sumber dana yang sama;
- perubahan kategori, pembayaran, vendor/penerima, procurement, dan Isian Manual setelah numbering harus melalui rollback yang sah;
- quarter numbering untuk READY adalah workflow utama.

Sebelum mengubah numbering/cancel/rollback, baca:

```text
docs/NUMBERING_CORRECTION_AND_ROLLBACK.md
docs/SPJ_DESIGN_DECISIONS.md
```

## 6. Architecture discipline

Pertahankan domain logic di use case/service layer. Jangan mengembalikan orchestration besar ke controller.

Sebelum refactor architecture, cek `docs/ARCHITECTURE_COMPLETE.md` dan sibling use cases/services.

Untuk perubahan frontend, jangan mengubah backend lifecycle hanya demi mempermudah UI.

## 7. GUI rules

Sebelum mengubah Blade, layout, theme, component, atau frontend interaction, baca:

```text
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
```

Gunakan primitive existing bila sesuai:

```text
x-ui.field
x-ui.input
x-ui.select
x-ui.textarea
x-ui.button
x-ui.form-section
x-ui.status-badge
x-ui.badge
x-ui.toolbar
x-page-header
```

Jangan membangun design system baru bila primitive canonical sudah tersedia.

Gunakan semantic tokens `--ui-*`, `--theme-*`, atau token canonical lain yang sudah ada.

## 8. Testing strategy

Gunakan focused verification untuk perubahan terarah.

Minimum checkpoint yang relevan dapat meliputi:

```text
npm run theme:qa
npm run build
php artisan view:cache --no-interaction
git diff --check
php artisan test --compact <focused-test>
```

Jangan menjalankan full suite pada setiap perubahan kecil kecuali perubahan memang luas atau user meminta.

Jangan mengklaim full suite hijau tanpa menjalankannya.

Jika perubahan PHP dilakukan, ikuti aturan Pint di `AGENTS.md`.

## 9. Browser/runtime claims

Jangan mengklaim browser/runtime PASS tanpa bukti aktual.

Jika kondisi tidak tersedia pada dataset, laporkan `RVR`, bukan PASS.

Mobile visual QA tetap mengikuti status di dokumentasi aktif; jangan menyebut mobile-complete kecuali evidence dan TODO terkait sudah benar-benar ditutup.

## 10. Protected local working files

Jangan restore, reset, stash, checkout, overwrite, atau commit file berikut tanpa instruksi eksplisit user:

```text
resources/views/dashboard.blade.php
resources/views/students/index.blade.php
```

File berikut harus tetap untracked:

```text
spj-bosp-web.code-workspace
```

## 11. Change scope discipline

Untuk file besar seperti `resources/views/spj/index.blade.php`, hindari full-file rewrite bila patch lokal lebih aman.

Jangan formatting seluruh file bila scope hanya satu panel/control.

Selalu audit diff sebelum commit.

## 12. Required documentation by area

Gunakan matriks di `docs/DOCUMENTATION_MAINTENANCE.md`.

Contoh:

- business rule → `docs/SPJ_DESIGN_DECISIONS.md`;
- architecture/ownership/boundary → `docs/ARCHITECTURE_COMPLETE.md`;
- user/operator flow → `docs/USER_SCENARIOS.md`;
- sync/reconciliation → `docs/SYNCHRONIZATION.md`;
- numbering/cancel/rollback → `docs/NUMBERING_CORRECTION_AND_ROLLBACK.md`;
- GUI/theme/icon → GUI/CSS/icon docs;
- status/evidence → `docs/CURRENT_PROGRESS.md`;
- priority/milestone → `docs/DEVELOPMENT_ROADMAP.md`;
- docs baru/status docs berubah → `docs/README.md`.

## 13. Finalization gate untuk agent

Sebelum agent mengatakan "selesai", "ready", "fixed", atau setara:

```text
[ ] diff diperiksa
[ ] test/verification sesuai scope dijalankan atau limitation dicatat
[ ] documentation impact diperiksa
[ ] docs terdampak sudah diupdate
[ ] docs usang/kontradiktif sudah ditangani
[ ] tidak ada klaim PASS tanpa evidence
[ ] status/prioritas tidak di-hard-code di agent instruction
```

Jika salah satu belum terpenuhi, pekerjaan belum final.
