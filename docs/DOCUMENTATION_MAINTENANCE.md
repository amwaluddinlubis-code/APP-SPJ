# Documentation Maintenance Policy

Terakhir diperbarui: **2026-09-11**

Status: **ACTIVE / REQUIRED FOR ALL CONTRIBUTORS AND AI AGENTS**

Dokumen ini adalah aturan canonical untuk menjaga dokumentasi APP-SPJ tetap sinkron dengan source code, tests, migration, UI, dan keputusan bisnis. Aturan ini berlaku untuk manusia dan seluruh AI/coding agent yang bekerja pada repository ini.

## 1. Definition of Done

Perubahan belum boleh dinyatakan selesai hanya karena code sudah berjalan. Sebelum final/commit/PR, contributor atau agent wajib melakukan **Documentation Impact Review**.

Urutan minimum:

```text
1. Inspect source + docs yang terkait
2. Implement perubahan
3. Tambahkan/update regression test yang relevan
4. Jalankan verification yang sesuai
5. Audit dokumentasi terdampak
6. Update dokumentasi berdasarkan evidence aktual
7. Audit docs/README.md dan instruksi agent bila aturan kerja berubah
8. Pastikan tidak ada klaim docs yang lebih tinggi daripada evidence
9. Baru nyatakan selesai / buat commit final / buka PR
```

Jika tidak ada dokumentasi yang perlu diubah, contributor/agent tetap harus menyatakan pada PR/checklist bahwa **documentation impact sudah diperiksa dan tidak ada update yang diperlukan**.

## 2. Source of truth dokumentasi

Gunakan urutan berikut ketika terjadi konflik:

1. `docs/CURRENT_PROGRESS.md` — status release, evidence, blocker, RVR, deferred work.
2. `docs/DEVELOPMENT_ROADMAP.md` — prioritas dan milestone aktif.
3. `docs/SPJ_DESIGN_DECISIONS.md` — kontrak bisnis/domain permanen.
4. `docs/ARCHITECTURE_COMPLETE.md` — arsitektur, ownership, boundary tenant, layer.
5. Feature/technical guide terkait.
6. Dokumen historis hanya untuk jejak keputusan dan tidak boleh mengalahkan source aktif.

`README.md` adalah entry point project, bukan sumber status release utama.

## 3. Matriks dokumentasi wajib

| Jenis perubahan | Dokumen yang wajib diperiksa/update |
|---|---|
| Status implementasi, test, CI, blocker, release risk | `docs/CURRENT_PROGRESS.md` |
| Prioritas/next milestone | `docs/DEVELOPMENT_ROADMAP.md` |
| Business rule/domain invariant | `docs/SPJ_DESIGN_DECISIONS.md` |
| Arsitektur, ownership, tenant boundary, layer, dependency | `docs/ARCHITECTURE_COMPLETE.md` |
| Flow operator/user | `docs/USER_SCENARIOS.md` |
| Sync ARKAS/BKU/Dapodik/reconciliation | `docs/SYNCHRONIZATION.md` |
| Numbering/cancel/rollback | `docs/NUMBERING_CORRECTION_AND_ROLLBACK.md` |
| Template/generator/placeholder | `docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md` dan guide terkait |
| GUI/layout/component/theme | `docs/GUI_STANDARDIZATION.md`, `docs/CSS_USAGE_GUIDE.md`, `docs/UI_ICON_MIGRATION.md` bila relevan |
| Importer ARKAS | `docs/ARKAS_IMPORTER.md` |
| Dokumen baru/status dokumen berubah | `docs/README.md` |
| Aturan kerja contributor/AI agent berubah | `AGENTS.md`, `.ai/rules/index.md`, dan entrypoint agent terkait |

Tidak semua file harus diubah pada setiap pekerjaan; semuanya **harus diperiksa berdasarkan dampak**.

## 4. Aturan evidence

Dokumentasi tidak boleh mendahului bukti.

- Jangan menulis `PASS` jika test/CI/runtime terkait belum benar-benar dijalankan.
- Jangan menulis `REAL-DATA VERIFIED` jika hanya synthetic test yang tersedia.
- Jangan menulis browser/mobile/runtime verified tanpa pemeriksaan aktual.
- Perubahan docs-only tidak menghasilkan functional gate baru.
- Gunakan `RVR` bila verifikasi real-value/runtime/operator masih dibutuhkan.
- Cantumkan commit/run/test count hanya jika benar-benar diverifikasi.

Klasifikasi canonical:

```text
FUNCTIONAL PASS
REAL-DATA VERIFIED
RVR
DEFERRED
HISTORICAL / SUPERSEDED / ARCHIVED
```

## 5. Pencegahan dokumentasi usang

Dokumentasi yang berisi status sementara atau prioritas tidak boleh disalin ke banyak file tanpa kebutuhan.

Prinsip:

- **Status aktif** hidup di `CURRENT_PROGRESS.md`.
- **Prioritas aktif** hidup di `DEVELOPMENT_ROADMAP.md`.
- Instruksi AI agent tidak boleh hard-code prioritas feature yang cepat berubah; agent harus membaca dua dokumen tersebut saat mulai bekerja.
- Kontrak permanen boleh diringkas di beberapa tempat, tetapi detail canonical harus punya satu home document.
- Bila dokumen tidak lagi valid, update, tandai `SUPERSEDED/ARCHIVED`, atau hapus bila aman dan jejak git sudah cukup.
- Jangan mempertahankan dokumen lama dengan judul aktif tetapi isi sudah bertentangan dengan runtime.

## 6. Aturan untuk semua AI/coding agent

Setiap AI/coding agent yang bekerja di repo ini wajib:

1. membaca `AGENTS.md`;
2. membaca `.ai/rules/index.md` bila ada;
3. membaca `docs/README.md`;
4. membaca `docs/DOCUMENTATION_MAINTENANCE.md`;
5. membaca `docs/CURRENT_PROGRESS.md` dan `docs/DEVELOPMENT_ROADMAP.md` sebelum menentukan kondisi/prioritas project;
6. membaca domain/feature guide yang relevan dengan file yang akan diubah;
7. melakukan Documentation Impact Review sebelum menyatakan pekerjaan selesai;
8. tidak menganggap dokumentasi lama benar jika bertentangan dengan source/test/runtime terbaru;
9. tidak mengklaim test atau CI sudah PASS tanpa evidence aktual;
10. bila agent membuat file dokumentasi baru, wajib mengindeksnya di `docs/README.md` jika file tersebut aktif/canonical.

Instruksi ini berlaku untuk Codex/OpenAI agents, Claude Code, Gemini CLI, GitHub Copilot, Cursor, Windsurf, IDE agents, dan agent lokal lain.

## 7. Checklist sebelum final/commit/PR

```text
[ ] Source change selesai dan diff sudah diperiksa
[ ] Focused regression test ditambah/diupdate bila relevan
[ ] Verification aktual sudah dijalankan atau limitation dicatat
[ ] CURRENT_PROGRESS diperiksa
[ ] DEVELOPMENT_ROADMAP diperiksa
[ ] SPJ_DESIGN_DECISIONS diperiksa
[ ] ARCHITECTURE_COMPLETE diperiksa
[ ] USER_SCENARIOS diperiksa
[ ] Feature/technical docs terkait diperiksa
[ ] docs/README.md diperiksa
[ ] Dokumentasi lama yang bertentangan sudah diperbarui/diarsipkan
[ ] Tidak ada klaim PASS/verified tanpa evidence
[ ] Instruksi AI agent tetap menunjuk ke policy canonical ini
```

## 8. Review khusus setelah perubahan besar

Lakukan audit dokumentasi lebih luas bila perubahan menyentuh salah satu ini:

- lifecycle SPJ;
- numbering/rollback/cancel;
- tenant scope;
- source ownership/synchronization;
- category model;
- document generator/template;
- authorization/role;
- database maintenance/migration;
- arsitektur use case/service/controller;
- perubahan workflow operator utama.

Pada perubahan besar, cari istilah lama di seluruh `docs/`, `README.md`, `AGENTS.md`, dan `.ai/` untuk menemukan kontradiksi, bukan hanya mengupdate satu file yang paling jelas.

## 9. Pull request discipline

Setiap PR wajib mengisi checklist dokumentasi pada `.github/pull_request_template.md`.

Jika PR mengubah behavior tetapi tidak mengubah docs, author harus menjelaskan singkat mengapa dokumentasi yang ada tetap benar.

Reviewer harus memperlakukan dokumentasi usang sebagai defect, bukan cosmetic issue.
