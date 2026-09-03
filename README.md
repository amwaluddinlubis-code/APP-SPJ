# SPJ BOSP Web

Fondasi migrasi aplikasi Excel/VBA SPJ BOSP ke Laravel. Workbook `.xlsm` tetap menjadi aplikasi operasional selama fase migrasi; proyek ini tidak mengubahnya.

## Domain yang sudah disiapkan

- Satu sekolah dan satu tahun anggaran aktif.
- Transaksi BKU per `NO_BUKTI`, detail barang/jasa, lima jenis pajak, dan metode bayar.
- Paket SPJ dan urutan nomor dokumen per periode.
- Log sinkronisasi dan adapter aman ke ARKASBridge yang ada.
- Dashboard serta daftar/detail transaksi awal.

## Menjalankan (setelah Composer tersedia)

```powershell
copy .env.example .env
php composer.phar install
New-Item database\database.sqlite -ItemType File
php artisan key:generate
php artisan migrate
php artisan serve
```

Gunakan SQLite pada fase awal. Konfigurasikan `DB_CONNECTION=sqlite` dan `DB_DATABASE` sebagai path absolut ke `database.sqlite`. Isi `SPJ_ARKAS_BRIDGE_COMMAND` hanya dengan perintah ARKASBridge yang sudah Anda uji pada komputer tersebut.

## Batas fase ini

Belum ada impor ARKAS otomatis, login, atau pembentuk dokumen PDF/Excel. Ketiganya dikerjakan setelah fondasi database dan sinkronisasi transaksi berhasil diuji.
"# APP-SPJ" 
