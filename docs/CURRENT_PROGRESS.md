# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-12**

Dokumen ini adalah sumber status release utama untuk branch `gui-standardization`.

Definisi status:

- **FUNCTIONAL PASS**: dibuktikan oleh source + deterministic CI/regression;
- **REAL-DATA VERIFIED**: dibuktikan pada database sekolah nyata atau isolated copy tanpa mengarang data yang tidak tersedia;
- **RVR**: masih memerlukan real-value/runtime/operator verification;
- **DEFERRED**: sengaja tidak menjadi fokus aktif saat ini, bukan berarti PASS.

---

## Checkpoint terbaru

Evidence CI canonical berada di `P0_VERIFICATION_KIT.md` §1 agar detail hash/run/test tidak diduplikasikan di banyak dokumen.

Status code gate saat dokumentasi ini diperbarui:

```text
LATEST TESTED CODE HEAD   : e46cfd8945430505ad7d84ff3b8b8bfc9b55c6a2
LATEST COMPLETED CODE GATE: CI #458 / run 34688492879 / SUCCESS
WORKFLOW                  : SPJ Critical Verification
MASTER TEMPLATE LIFECYCLE : FUNCTIONAL PASS
NUMBERING REGISTRY        : CANONICAL / SOURCE OF TRUTH ACTIVE
REPOSITORY-WIDE PINT      : ADVISORY / 5 PRE-EXISTING UNRELATED STYLE ISSUES REMAIN
```

CI #458 berhasil setelah implementasi **Master Template Terbaru** dan regression yang meniru flow nyata `import master -> update satu XLSX -> download master`. Blocking frontend build, Blade compile, SPJ Critical, full Unit, dan full Feature suite semuanya PASS. Repository-wide Pint masih advisory dan command Pint pada run #458 tetap melaporkan 5 style issue lama pada file isolated-numbering/rollback yang tidak terkait perubahan template; detail canonical berada di `P0_VERIFICATION_KIT.md` §1.

Commit dokumentasi-only setelah `e46cfd8945430505ad7d84ff3b8b8bfc9b55c6a2` tidak memicu workflow karena `docs/**` di-ignore dan **tidak menggantikan** code gate tersebut.

Kontrak Master Template Terbaru sekarang:

```text
source of truth = template XLSX aktif per document type canonical
upload/update satu XLSX = versi itu dipakai pada master download berikutnya
master lama = tidak dimutasi/ditulis ulang sebagai source of truth
download master = dirakit on demand dari seluruh XLSX canonical aktif
master parsial = ditolak
hasil rakitan = divalidasi ulang melalui validator paket canonical
download per baris = tetap template individu, bukan paket
DOCX = tetap individual dan tidak digabung ke master XLSX
```

Refactor numbering registry **tidak berubah** oleh pekerjaan ini. Source of truth numbering tetap:

```text
app/Services/SpjNumberingDocumentRegistry.php
```

Registry canonical menyimpan metadata:

```text
code
label
numbered
applicable_categories
channel
event_date_rule
number_target
scope_rule
```

Halaman format penomoran, halaman penomoran triwulan, policy, gate, ordering/event-date resolver, allocator, lifecycle/finalization, cancel, dan replacement membaca definisi numbering dari registry yang sama. `SpjDocumentTypeRegistry` tetap mempunyai tanggung jawab berbeda sebagai registry template/placeholder, bukan sumber aturan numbering.

Token `{TW}` tetap menghasilkan angka Romawi triwulan (`I`, `II`, `III`, `IV`) tanpa prefix otomatis `TW.`. Operator dapat menambahkan literal `TW.` sendiri pada pattern bila dibutuhkan, misalnya `TW.{TW}`.

---

## Status release saat ini

```text
FUNCTIONAL CORE : PASS pada code gate e46cfd894... / CI #458
REAL-DATA       : VERIFIED untuk audit/preflight + isolated numbering/cancel/tail rollback; output QA masih ACTIVE
MASTER TEMPLATE : FUNCTIONAL PASS / EXCEL-LIBREOFFICE VISUAL QA RVR
OFFICIAL OUTPUT : RVR ACTIVE
BROWSER/RUNTIME : RVR ACTIVE
FINAL RELEASE   : NOT YET
```

Aplikasi belum boleh disebut final release-ready hanya karena CI hijau. Generated-document real-data, Master Template Terbaru pada viewer Office aktual, official-template visual QA, browser/operator runtime, dan installed-runtime yang masih deferred tetap merupakan gate terpisah.

---

## GUI standardization

```text
GUI STANDARDIZATION CORE : ESTABLISHED
SOURCE-LEVEL CLEANUP      : PASS untuk GUI-AUDIT-01 s.d. 13 source readiness
DESKTOP SOURCE READINESS  : PASS
BROWSER DESKTOP/LAPTOP    : RVR ACTIVE
MOBILE SOURCE READINESS   : PASS
MOBILE/TABLET RUNTIME     : RVR / NON-BLOCKER untuk target desktop-laptop
```

Milestone source yang sudah selesai mencakup shared `x-ui` primitives, semantic theme tokens, canonical icon registry, density/typography pass, route/page-marker generalization, responsive source guards, dan cleanup halaman utama yang sudah digate. Browser visual/runtime PASS tetap harus dibuktikan melalui `docs/GUI_RUNTIME_QA.md`; source readiness tidak boleh dipromosikan menjadi browser PASS.

---

## P0-01 — Six-category SPJ end-to-end

```text
FUNCTIONAL SIX-CATEGORY E2E : PASS
REAL-DATA BASELINE/AUDIT    : PASS untuk scope yang tersedia
GENERATED-DOCUMENT REAL DATA: ACTIVE
INSTALLED-RUNTIME           : DEFERRED / RVR
```

Kategori canonical:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

### Baseline real-data 2026

School real-data yang digunakan pada verifikasi aktif mempunyai baseline:

```text
transactions              : 170
transaction_items         : 407
spj_packages              : 66
spj_documents             : 0
document_number_sequences : 0
document_number_formats   : 0
```

Distribusi kategori 2026:

```text
BARANG          : 41
HONOR_PEGAWAI   : 12
JASA_LAINNYA    : 9
KONSUMSI        : 2
PEMELIHARAAN    : 2
SPPD            : 0
```

SPPD nyata tersedia pada data 2025, bukan 2026. Jangan fabrikasi SPPD 2026 untuk memaksa coverage.

Audit real-data TW2 / Fund Source 1 sudah PASS pada 66 transaksi ber-item / 66 Paket READY. Fund Source 2 pada scope yang sama tidak mempunyai transaksi, dan partition yang diuji tidak menunjukkan leakage. Original baseline tetap immutable; mutation QA dilakukan pada isolated copy.

---

## P0-02 — Document generator / template

```text
FUNCTIONAL GENERATOR          : PASS
TEMPLATE UPLOAD HARDENING     : PASS
INDIVIDUAL TEMPLATE DOWNLOAD  : FUNCTIONAL PASS
MASTER TEMPLATE RECOMPOSITION : FUNCTIONAL PASS
MASTER EXCEL/LIBREOFFICE QA   : RVR
REAL-DATA GENERATED OUTPUT    : ACTIVE / OPERATOR QA
OFFICIAL-TEMPLATE VISUAL QA   : RVR
```

Kontrak yang sudah dijaga:

- preview/download tidak menerbitkan nomor;
- template invalid tidak mengganti template aktif;
- unresolved placeholder tidak boleh diam-diam lolos;
- per-row **Download Template** mengunduh dokumen terpilih, bukan seluruh paket;
- **Unduh Master Template Terbaru** merakit satu sheet canonical dari setiap template XLSX aktif pada fiscal year aktif;
- update satu XLSX individu langsung menjadi source document type tersebut pada master download berikutnya tanpa memutasi master historis;
- record template lain boleh tetap berasal dari salinan master multi-sheet hasil importer dan export hanya mengambil sheet canonical milik document type tersebut;
- nama/urutan sheet master mengikuti `SpjDocumentTypeRegistry`;
- master hasil rakitan wajib lolos `SpjTemplatePackageImporter::validatePackage()` sebelum dikirim;
- master parsial ditolak bila satu atau lebih XLSX canonical aktif tidak tersedia;
- DOCX tetap template individu dan tidak masuk master XLSX;
- functional generation/re-import contract tidak sama dengan visual verification dokumen resmi atau workbook Office aktual.

Focused regression `DocumentTemplateMasterExportTest` pada CI #458 secara eksplisit membuktikan flow `import master -> update RINCIAN_BELANJA individu -> download master terbaru`: sheet Rincian memakai versi baru, sheet lain memakai versi aktif masing-masing, output lengkap mengikuti registry, dan paket lolos validator re-import. Source/master yang sudah tersimpan tidak ditulis balik ketika download berlangsung.

Panduan lifecycle khusus fitur ini: `docs/TEMPLATE_MASTER_WORKFLOW.md`.

Fokus operator berikutnya untuk area template adalah membuka `MASTER-TEMPLATE-SPJ-TERBARU.xlsx` hasil aplikasi pada Microsoft Excel/LibreOffice dan memeriksa fidelity workbook nyata—terutama drawing, formula/reference lintas sheet, print area, page break, header/footer, serta hasil cetak. Sampai itu dilakukan, visual/document runtime tetap RVR meskipun struktur functional sudah PASS.

Untuk generated SPJ output, fokus operator tetap generate dokumen dari aplikasi menggunakan Paket nyata, kemudian memperbaiki bug yang benar-benar terlihat pada output. Tidak perlu menambah test baru hanya untuk memperbesar coverage; regression baru ditambahkan bila ada bug nyata yang perlu dikunci.

---

## P0-03 — Numbering, registry, lifecycle, correction & rollback

```text
FUNCTIONAL NUMBERING             : PASS
CANONICAL NUMBERING REGISTRY     : PASS / ACTIVE SOURCE OF TRUTH
FORMAT PAGE + NUMBERING PAGE     : REGISTRY-DRIVEN
POLICY/GATE/ORDER/ALLOCATOR       : REGISTRY-DRIVEN
FINALIZE/CANCEL/REPLACEMENT       : REGISTRY-DRIVEN
READ-ONLY REAL-DATA PREFLIGHT     : PASS
ISOLATED FIRST NUMBER             : PASS
INDIVIDUAL CANCEL / RESERVE       : PASS
ISOLATED TAIL ROLLBACK            : PASS
FUNCTIONAL QUARTER ROLLBACK       : PASS
ISOLATED QUARTER ROLLBACK RUNTIME : PENDING / OPTIONAL unless needed by bug or operator flow
FUND-SOURCE SEQUENCE SCOPE        : PASS
POST-NUMBERING EDIT RULE          : PASS
```

### Canonical numbering registry

Source of truth:

```text
app/Services/SpjNumberingDocumentRegistry.php
```

Current numbered document definitions tetap sesuai aturan bisnis yang sudah disepakati:

```text
SPJ
PESANAN
BAP
BAST
SPK
RAB
SURAT_TUGAS_PERJALANAN_DINAS
```

Daftar tersebut **bukan lagi hardcoded pada consumer**. Consumer memperoleh kode, label, kategori applicable, channel, event-date rule, target field nomor, dan scope dari registry. Penambahan atau perubahan definisi numbering dilakukan pada registry canonical, lalu consumer yang relevan membaca metadata tersebut secara dinamis.

Alias lama seperti `ORDER`, `SURAT_PESANAN`, `WORK_ORDER`, `SPK_PEMELIHARAAN`, dan `RAB_PEMELIHARAAN` dinormalisasi melalui registry sebelum masuk workflow canonical.

SPJ utama berlaku untuk setiap Paket (`applicable_categories = ['*']`) agar kompatibel dengan paket legacy yang belum mempunyai kategori, sedangkan dokumen turunan tetap dibatasi oleh kategori/channel applicable.

### Evidence real-data yang sudah PASS

Read-only preflight:

```text
query_only               : ON
baseline SHA-256         : UNCHANGED
PREFLIGHT RESULT         : PASS
number issued            : NONE
```

Isolated first numbering:

```text
BPU01
0001/SPJ/SMPN.2/TW.II/2026   (format lama sebelum perubahan token {TW})
sequence 1
Paket NUMBERED
baseline hash UNCHANGED
```

Individual cancel + reserve:

```text
BPU01 sequence 1 -> CANCELLED permanen
sequence tetap 1
BPU02 -> sequence 2
baseline hash UNCHANGED
```

Tail rollback pada fresh isolated copy:

```text
initial          : 1,2,3
rollback from    : 2
after rollback   : sequence 1
renumber         : sequence 2 dapat dipakai kembali
baseline hash    : UNCHANGED
```

Quarter rollback dependency tetap FUNCTIONAL PASS melalui regression. Karena data nyata 2026 tidak mempunyai transaksi TW3/TW4, dependency lintas-triwulan tidak boleh diklaim sebagai real-data runtime coverage dan tidak boleh dipaksakan dengan data fiktif.

### Format token triwulan

Token numbering:

```text
{TW} -> I / II / III / IV
```

Aplikasi tidak menambahkan string `TW.` secara otomatis. Jika sekolah/operator membutuhkan prefix tersebut, pattern dapat ditulis manual:

```text
{SEQ}/SPJ/{SCHOOL}/TW.{TW}/{YEAR}
```

Nomor yang sudah pernah diterbitkan tidak diubah otomatis oleh perubahan format ini.

Panduan domain lengkap: `docs/NUMBERING_CORRECTION_AND_ROLLBACK.md`.

---

## P0-04 — Authorization

**FUNCTIONAL PASS.** VIEWER tetap read-only, OPERATOR mengikuti workflow operasional sesuai permission, dan ADMIN menangani lifecycle/maintenance/sensitive action. Authorization tidak menggantikan tenant/context isolation.

---

## P0-05 — Safe synchronization / reconciliation

```text
FUNCTIONAL PASS
REAL-DATA RECONCILIATION VERIFICATION : ACTIVE
```

Kontrak utama:

```text
ARKAS/BKU = readonly source
overlay operator = dipertahankan
source missing = jangan hapus pekerjaan operator
source returning = reuse identity yang sama
NUMBERED/FINAL = tidak dimutasi diam-diam
```

---

## P0-06 — Tenant isolation

**FUNCTIONAL PASS.** Boundary canonical:

```text
School + Fiscal Year + Fund Source
```

Sequence numbering/rollback juga mengikuti boundary tersebut.

---

## P0-07 — School database maintenance

```text
FUNCTIONAL MAINTENANCE : PASS
INSTALLED-RUNTIME      : DEFERRED / RVR
```

---

## P0-08 — Generic ARKAS Importer

```text
FUNCTIONAL HARDENING : PASS
OPERATOR DATA TEST   : ACTIVE
```

Importer/sync tidak boleh menulis data fiktif untuk memaksa downstream SPJ PASS.

---

## Unified Employee Identity

```text
FUNCTIONAL IDENTITY CORE : PASS
REAL-SCHOOL VERIFICATION : ACTIVE
```

Identity matching tetap konservatif; normalized name ambigu tidak boleh menyebabkan silent merge. Participant manual tetap diperbolehkan dan provenance harus dipertahankan.

---

## Quarter Audit

```text
FUNCTIONAL PASS
REAL-DATA READ-ONLY AUDIT : PASS untuk scope 2026/TW2 yang diuji
```

Audit tetap read-only dan bukan jalur auto-repair.

---

## SiPLah

```text
FUNCTIONAL CORE          : PASS
GENERATED-DOCUMENT E2E   : RVR
OFFICIAL-TEMPLATE OUTPUT : RVR
```

SiPLah tetap channel/payment context, bukan `spj_category`.

---

## Fokus kerja aktif

Prioritas sekarang sengaja dipersempit ke penggunaan aplikasi nyata:

1. buka dan inspeksi `MASTER-TEMPLATE-SPJ-TERBARU.xlsx` hasil aplikasi pada Microsoft Excel/LibreOffice untuk menutup visual/document RVR;
2. generate dokumen melalui aplikasi untuk Paket nyata BARANG, KONSUMSI, PEMELIHARAAN, JASA_LAINNYA, dan HONOR_PEGAWAI;
3. perbaiki hanya bug nyata yang ditemukan pada data, nomor, tanggal, placeholder, layout, XLSX/PDF, atau lifecycle;
4. tambahkan regression test hanya bila bug tersebut perlu dikunci agar tidak kembali;
5. verifikasi JASA_LAINNYA multi-recipient dan PEMELIHARAAN bahan+upah pada generated output nyata;
6. lanjutkan official-template visual/output QA;
7. jalankan browser/operator QA desktop/laptop berdasarkan `GUI_RUNTIME_QA.md`;
8. mobile/tablet minimum usability tetap RVR/non-blocker untuk target desktop-laptop;
9. lanjutkan real-data reconciliation, employee identity, dan operational audit bila muncul pada operator flow.

Tidak ada kebutuhan aktif untuk memperbanyak smoke test numbering selama tidak ditemukan bug baru. Canonical numbering registry tetap digate hijau oleh code gate terbaru #458.

---

## Open verification / release blockers

Belum boleh diberi status final sampai evidence tersedia untuk:

- Master Template Terbaru visual/runtime QA pada Excel/LibreOffice untuk workbook nyata;
- generated-document real-data per kategori yang masih aktif;
- official-template visual/output RVR;
- GUI-AUDIT-12 browser/operator runtime QA;
- mobile/tablet runtime QA bila ingin menutup minimum usability;
- installed-runtime checks yang masih DEFERRED.

Quarter rollback real-data isolated runtime bukan blocker aktif bila tidak ada bug/operator requirement yang menuntutnya; kontrak functional-nya sudah PASS.

---

## Aturan evidence dan pengembangan

1. Jangan mengubah source data hanya agar test/audit real-data PASS.
2. Jangan memakai deterministic fixture sebagai bukti bahwa real-data verified.
3. Jangan memakai screenshot/UI appearance sebagai pengganti backend regression.
4. Jangan menyatakan CI baru untuk commit docs-only.
5. Setiap source change setelah code gate hijau terakhir harus memperoleh CI hijau baru sebelum menjadi canonical gate HEAD.
6. Jika business rule berubah, sinkronkan `SPJ_DESIGN_DECISIONS.md`, feature guide terkait, dan dokumen status.
7. GUI source cleanup hanya boleh disebut source-level PASS; browser visual QA tetap RVR sampai diverifikasi runtime.
8. Setelah kontrak inti PASS, gunakan pendekatan **operator flow -> temukan bug -> perbaiki -> regression bila perlu**, bukan menambah test tanpa kebutuhan nyata.
9. Metadata numbering baru atau perubahan metadata numbering dilakukan melalui `SpjNumberingDocumentRegistry`; consumer tidak boleh membuat daftar/label/event-date/target numbering hardcoded sendiri.
