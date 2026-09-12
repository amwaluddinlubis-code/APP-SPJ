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

Evidence CI canonical berada di `P0_VERIFICATION_KIT.md` §1 agar hash/run tidak diduplikasikan di banyak dokumen.

Status branch saat dokumentasi ini diperbarui:

```text
CURRENT SOURCE HEAD       : b167476d1b034cd19e9019234353cdafa7ac4fce
LATEST COMPLETED CODE GATE: CI #443 / run 34674547646 / SUCCESS
WORKFLOW                  : SPJ Critical Verification
NUMBERING REGISTRY        : CANONICAL / SOURCE OF TRUTH ACTIVE
```

CI #443 berhasil setelah refactor numbering registry. Blocking build, Blade compile, SPJ Critical, full Unit, dan full Feature suite semuanya PASS. Repository Pint tetap advisory pada workflow, tetapi run #443 juga PASS untuk Pint check.

Refactor ini **tidak mengubah aturan bisnis numbering**. Tujuannya menghilangkan duplikasi metadata dokumen bernomor dari controller/service/UI. Source of truth numbering sekarang:

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
FUNCTIONAL CORE : PASS pada HEAD b167476d... / CI #443
REAL-DATA       : VERIFIED untuk audit/preflight + isolated numbering/cancel/tail rollback; output QA masih ACTIVE
OFFICIAL OUTPUT : RVR ACTIVE
BROWSER/RUNTIME : RVR ACTIVE
FINAL RELEASE   : NOT YET
```

Aplikasi belum boleh disebut final release-ready hanya karena CI hijau. Generated-document real-data, official-template visual QA, browser/operator runtime, dan installed-runtime yang masih deferred tetap merupakan gate terpisah.

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
FUNCTIONAL GENERATOR        : PASS
TEMPLATE UPLOAD HARDENING   : PASS
REAL-DATA GENERATED OUTPUT  : ACTIVE / OPERATOR QA
OFFICIAL-TEMPLATE VISUAL QA : RVR
```

Kontrak yang sudah dijaga:

- preview/download tidak menerbitkan nomor;
- template invalid tidak mengganti template aktif;
- unresolved placeholder tidak boleh diam-diam lolos;
- functional generation tidak sama dengan visual verification dokumen resmi.

Fokus operator sekarang adalah generate dokumen dari aplikasi menggunakan Paket nyata, kemudian memperbaiki bug yang benar-benar terlihat pada output. Tidak perlu menambah test baru hanya untuk memperbesar coverage; regression baru ditambahkan bila ada bug nyata yang perlu dikunci.

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

1. generate dokumen melalui aplikasi untuk Paket nyata BARANG, KONSUMSI, PEMELIHARAAN, JASA_LAINNYA, dan HONOR_PEGAWAI;
2. perbaiki hanya bug nyata yang ditemukan pada data, nomor, tanggal, placeholder, layout, XLSX/PDF, atau lifecycle;
3. tambahkan regression test hanya bila bug tersebut perlu dikunci agar tidak kembali;
4. verifikasi JASA_LAINNYA multi-recipient dan PEMELIHARAAN bahan+upah pada generated output nyata;
5. lanjutkan official-template visual/output QA;
6. jalankan browser/operator QA desktop/laptop berdasarkan `GUI_RUNTIME_QA.md`;
7. mobile/tablet minimum usability tetap RVR/non-blocker untuk target desktop-laptop;
8. lanjutkan real-data reconciliation, employee identity, dan operational audit bila muncul pada operator flow.

Tidak ada kebutuhan aktif untuk memperbanyak smoke test numbering selama tidak ditemukan bug baru. Refactor canonical registry sudah digate hijau pada CI #443.

---

## Open verification / release blockers

Belum boleh diberi status final sampai evidence tersedia untuk:

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
