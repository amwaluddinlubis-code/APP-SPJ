# Claude Code Instructions — APP-SPJ

Sebelum mengubah repository ini, wajib membaca dan mematuhi:

1. `AGENTS.md`
2. `.ai/rules/index.md`
3. `docs/README.md`
4. `docs/DOCUMENTATION_MAINTENANCE.md`
5. `docs/CURRENT_PROGRESS.md`
6. `docs/DEVELOPMENT_ROADMAP.md`
7. domain/feature guide yang relevan dengan perubahan

Aturan penting:

- jangan hard-code asumsi prioritas project dari chat lama; ambil prioritas dari `CURRENT_PROGRESS.md` + `DEVELOPMENT_ROADMAP.md`;
- sebelum menyatakan pekerjaan selesai, lakukan **Documentation Impact Review** sesuai `docs/DOCUMENTATION_MAINTENANCE.md`;
- behavior change yang membuat dokumentasi usang wajib diikuti update docs pada pekerjaan yang sama;
- jangan mengklaim test/CI/runtime PASS tanpa evidence aktual;
- dokumentasi usang dianggap defect;
- bila tidak ada docs yang perlu diubah, tetap verifikasi bahwa docs existing masih benar.

Dokumen canonical untuk governance dokumentasi adalah `docs/DOCUMENTATION_MAINTENANCE.md`; jangan menduplikasi policy lengkap di file ini.
