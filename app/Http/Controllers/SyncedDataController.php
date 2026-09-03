<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Read-only browser for data imported from ARKAS and the generated SPJ tables. */
class SyncedDataController extends Controller
{
    public function index(Request $request, string $type = 'overview'): View
    {
        $yearId = (int) session('active_fiscal_year_id');
        $tables = $this->tables();
        abort_unless($type === 'overview' || isset($tables[$type]), 404);

        $counts = [];
        foreach ($tables as $key => $table) {
            $query = DB::connection('school')->table($table['table']);
            $this->applyJoinsAndYearScope($query, $table, $yearId);
            $counts[$key] = $query->count();
        }
        if ($type === 'overview') {
            return view('synced-data.index', compact('tables', 'counts', 'type'));
        }

        $table = $tables[$type];
        $query = DB::connection('school')->table($table['table'])->select($table['select']);
        $this->applyJoinsAndYearScope($query, $table, $yearId);
        foreach ($table['order'] as [$column, $direction]) {
            $query->orderBy($column, $direction);
        }
        $perPageRaw = $request->input('perPage', 15);
        $perPage = $perPageRaw === 'all' ? 10000 : (int) $perPageRaw;
        $perPage = in_array($perPage, [15, 25, 50, 100, 10000]) ? $perPage : 15;
        $rows = $query->paginate($perPage)->withQueryString();

        return view('synced-data.index', compact('tables', 'counts', 'type', 'table', 'rows'));
    }

    /**
     * @param  array{year:?string,joins?:array<int,array{0:string,1:string,2:string,3:string}>}  $table
     */
    private function applyJoinsAndYearScope($query, array $table, int $yearId): void
    {
        foreach ($table['joins'] ?? [] as [$joinTable, $left, $operator, $right]) {
            $query->join($joinTable, $left, $operator, $right);
        }

        if ($table['year']) {
            $query->where($table['year'], $yearId);
        }
    }

    /** @return array<string,array{group:string,label:string,table:string,year:?string,select:array,order:array,joins?:array}> */
    private function tables(): array
    {
        return [
            'profil' => ['group' => 'REFERENSI / MASTER', 'label' => 'Profil Penanggung Jawab', 'table' => 'school_profiles', 'year' => 'fiscal_year_id', 'select' => ['principal_name as KEPALA_SEKOLAH', 'principal_nip as NIP_KEPALA', 'treasurer_name as BENDAHARA', 'treasurer_nip as NIP_BENDAHARA', 'principal_email as EMAIL_KEPALA', 'treasurer_email as EMAIL_BENDAHARA'], 'order' => [['id', 'desc']]],
            'pegawai' => ['group' => 'REFERENSI / MASTER', 'label' => 'Pegawai dan PTK', 'table' => 'employees', 'year' => null, 'select' => ['source_type as SUMBER', 'name as NAMA', 'nip as NIP', 'nuptk as NUPTK', 'position as JABATAN', 'staff_type as JENIS_PTK', 'employment_status as STATUS_PEGAWAI', 'is_active as AKTIF'], 'order' => [['name', 'asc']]],
            'rekening' => ['group' => 'REFERENSI / MASTER', 'label' => 'Referensi Rekening', 'table' => 'account_references', 'year' => 'fiscal_year_id', 'select' => ['account_code as KODE_REKENING', 'account_name as NAMA_REKENING', 'spj_category as KATEGORI_SPJ', 'is_honor as HONOR', 'is_ppn as PPN', 'is_pph21 as PPH21', 'is_pph22 as PPH22', 'is_pph23 as PPH23', 'is_pph4 as PPH4', 'is_sspd as SSPD'], 'order' => [['account_code', 'asc']]],
            'hierarki-rekening' => ['group' => 'REFERENSI / MASTER', 'label' => 'Hierarki Rekening', 'table' => 'account_hierarchies', 'year' => null, 'select' => ['account_code as KODE_REKENING', 'account_name as NAMA_REKENING', 'level as LEVEL'], 'order' => [['account_code', 'asc']]],
            'periode' => ['group' => 'REFERENSI / MASTER', 'label' => 'Periode ARKAS', 'table' => 'arkas_periods', 'year' => null, 'select' => ['source_period_id as ID_PERIODE', 'name as PERIODE'], 'order' => [['source_period_id', 'asc']]],
            'kegiatan' => ['group' => 'REFERENSI / MASTER', 'label' => 'Kegiatan RKAS', 'table' => 'activity_references', 'year' => 'fiscal_year_id', 'select' => ['activity_code as KODE_KEGIATAN', 'activity_name as NAMA_KEGIATAN', 'source_ref_code as ID_REF_KODE'], 'order' => [['activity_code', 'asc']]],
            'rekanan' => ['group' => 'REFERENSI / MASTER', 'label' => 'Rekanan / Penyedia', 'table' => 'business_partners', 'year' => null, 'select' => ['name as NAMA_REKANAN', 'npwp as NPWP', 'phone as TELEPON', 'address as ALAMAT', 'is_business_entity as BADAN_USAHA', 'is_arkas_synced as DARI_ARKAS'], 'order' => [['name', 'asc']]],
            'rkas' => ['group' => 'ANGGARAN & BUKU KAS', 'label' => 'RKAS Rinci', 'table' => 'arkas_rkas_items', 'year' => 'fiscal_year_id', 'select' => ['source_rapbs_id as ID_RAPBS', 'activity_code as KODE_KEGIATAN', 'activity_name as NAMA_KEGIATAN', 'account_code as KODE_REKENING', 'description as URAIAN', 'amount as ANGGARAN'], 'order' => [['activity_code', 'asc'], ['account_code', 'asc']]],
            'bku' => ['group' => 'ANGGARAN & BUKU KAS', 'label' => 'BKU Mentah', 'table' => 'arkas_bku_rows', 'year' => 'fiscal_year_id', 'select' => ['source_kas_id as ID_KAS_UMUM', 'parent_kas_id as PARENT_ID', 'category as KATEGORI_BKU', 'no_bukti as NO_BUKTI', 'transaction_date as TANGGAL', 'amount as NILAI'], 'order' => [['transaction_date', 'desc'], ['no_bukti', 'asc']]],
            'transaksi' => ['group' => 'HASIL SPJ', 'label' => 'Transaksi SPJ', 'table' => 'transactions', 'year' => 'fiscal_year_id', 'select' => ['id as ID_TRANSAKSI', 'no_bukti as NO_BUKTI', 'transaction_date as TANGGAL', 'description as URAIAN', 'recipient_name as PENERIMA', 'account_code as KODE_REKENING', 'gross_amount as BRUTO', 'tax_total as PAJAK', 'net_amount as DIBAYARKAN', 'status as STATUS'], 'order' => [['transaction_date', 'desc'], ['no_bukti', 'asc']]],
            'detail-transaksi' => ['group' => 'HASIL SPJ', 'label' => 'Detail Transaksi', 'table' => 'transaction_items as ti', 'year' => 't.fiscal_year_id', 'joins' => [['transactions as t', 't.id', '=', 'ti.transaction_id']], 'select' => ['ti.transaction_id as ID_TRANSAKSI', 'ti.source_item_id as ID_KAS_UMUM', 'ti.description as URAIAN', 'ti.item_description as URAIAN_BARANG', 'ti.quantity as VOLUME', 'ti.unit as SATUAN', 'ti.unit_price as HARGA_SATUAN', 'ti.amount as NILAI'], 'order' => [['ti.transaction_id', 'desc'], ['ti.id', 'asc']]],
            'spj-paket' => ['group' => 'ENTITAS SPJ / DOMAIN NO_*', 'label' => 'SPJ · Paket Dokumen', 'table' => 'spj_packages as sp', 'year' => 't.fiscal_year_id', 'joins' => [['transactions as t', 't.id', '=', 'sp.transaction_id']], 'select' => ['sp.id as ID_PAKET', 't.no_bukti as NO_BUKTI', 'sp.document_number as NO_SPJ', 'sp.quarter_code as TRIWULAN', 'sp.semester_code as SEMESTER', 'sp.status as STATUS', 'sp.numbered_at as DINOMORI_PADA', 'sp.finalized_at as DIFINALKAN_PADA'], 'order' => [['sp.id', 'desc']]],
            'spj-dokumen' => ['group' => 'ENTITAS SPJ / DOMAIN NO_*', 'label' => 'SPJ · Dokumen Bernomor', 'table' => 'spj_documents as sd', 'year' => 't.fiscal_year_id', 'joins' => [['spj_packages as sp', 'sp.id', '=', 'sd.spj_package_id'], ['transactions as t', 't.id', '=', 'sp.transaction_id']], 'select' => ['sd.id as ID_DOKUMEN', 't.no_bukti as NO_BUKTI', 'sp.document_number as NO_SPJ', 'sd.document_number as NO_DOKUMEN', 'sd.document_type as JENIS_DOKUMEN', 'sd.sequence_number as NO_URUT', 'sd.document_date as TANGGAL_DOKUMEN', 'sd.status as STATUS'], 'order' => [['sd.document_date', 'desc'], ['sd.id', 'desc']]],
            'spj-barang' => ['group' => 'ENTITAS SPJ / DOMAIN NO_*', 'label' => 'SPJ · Barang & Berita Acara', 'table' => 'spj_goods as sg', 'year' => 't.fiscal_year_id', 'joins' => [['transaction_items as ti', 'ti.id', '=', 'sg.transaction_item_id'], ['transactions as t', 't.id', '=', 'ti.transaction_id']], 'select' => ['sg.id as ID_BARANG_SPJ', 't.no_bukti as NO_BUKTI', 'sg.order_number as NO_PESANAN', 'sg.order_date as TANGGAL_PESANAN', 'sg.bap_number as NO_BAP', 'sg.bap_date as TANGGAL_BAP', 'sg.bast_number as NO_BAST', 'sg.bast_date as TANGGAL_BAST', 'ti.item_description as URAIAN_BARANG'], 'order' => [['sg.id', 'desc']]],
            'spj-peserta' => ['group' => 'ENTITAS SPJ / DOMAIN NO_*', 'label' => 'SPJ · Peserta Kegiatan', 'table' => 'spj_participants as spp', 'year' => 't.fiscal_year_id', 'joins' => [['transaction_items as ti', 'ti.id', '=', 'spp.transaction_item_id'], ['transactions as t', 't.id', '=', 'ti.transaction_id']], 'select' => ['spp.id as ID_PESERTA', 't.no_bukti as NO_BUKTI', 'spp.name as NAMA', 'spp.position as JABATAN', 'spp.portions as PORSI'], 'order' => [['spp.id', 'desc']]],
            'spj-perjalanan' => ['group' => 'ENTITAS SPJ / DOMAIN NO_*', 'label' => 'SPJ · Perjalanan Dinas', 'table' => 'spj_travels as st', 'year' => 't.fiscal_year_id', 'joins' => [['transactions as t', 't.id', '=', 'st.transaction_id']], 'select' => ['st.id as ID_PERJALANAN', 't.no_bukti as NO_BUKTI', 'st.assignment_letter_number as NO_SURAT_TUGAS_PERJALANAN_DINAS', 'st.assignment_letter_date as TANGGAL_SURAT_TUGAS', 'st.traveler_name as NAMA_PELAKSANA', 'st.destination as TUJUAN', 'st.purpose as KEPERLUAN', 'st.departure_date as TANGGAL_BERANGKAT', 'st.return_date as TANGGAL_KEMBALI', 'st.transport_mode as TRANSPORTASI', 'st.amount as NILAI'], 'order' => [['st.departure_date', 'desc'], ['st.id', 'desc']]],
            'spj-pemeliharaan' => ['group' => 'ENTITAS SPJ / DOMAIN NO_*', 'label' => 'SPJ · Pemeliharaan', 'table' => 'spj_maintenances as sm', 'year' => 'sm.fiscal_year_id', 'select' => ['sm.id as ID_PEMELIHARAAN', 'sm.name as NAMA_PEKERJAAN', 'sm.description as URAIAN', 'sm.default_location as LOKASI', 'sm.status as STATUS'], 'order' => [['sm.id', 'desc']]],
            'spj-spk' => ['group' => 'ENTITAS SPJ / DOMAIN NO_*', 'label' => 'SPJ · SPK / Pekerjaan', 'table' => 'spj_work_orders as swo', 'year' => 't.fiscal_year_id', 'joins' => [['transactions as t', 't.id', '=', 'swo.transaction_id'], ['spj_maintenances as sm', 'sm.id', '=', 'swo.maintenance_id']], 'select' => ['swo.id as ID_PEKERJAAN', 't.no_bukti as NO_BUKTI', 'swo.spk_number as NO_SPK', 'swo.spk_date as TANGGAL_SPK', 'swo.rab_number as NO_RAB', 'swo.rab_date as TANGGAL_RAB', 'swo.expense_type as JENIS_BIAYA', 'swo.work_description as URAIAN_PEKERJAAN', 'swo.work_location as LOKASI', 'sm.name as PEMELIHARAAN'], 'order' => [['swo.id', 'desc']]],
            'spj-pekerja' => ['group' => 'ENTITAS SPJ / DOMAIN NO_*', 'label' => 'SPJ · Pekerja / Upah', 'table' => 'spj_workers as sw', 'year' => 't.fiscal_year_id', 'joins' => [['spj_work_orders as swo', 'swo.id', '=', 'sw.work_order_id'], ['transactions as t', 't.id', '=', 'swo.transaction_id']], 'select' => ['sw.id as ID_PEKERJA', 't.no_bukti as NO_BUKTI', 'swo.spk_number as NO_SPK', 'sw.name as NAMA', 'sw.nik as NIK', 'sw.job_description as PEKERJAAN', 'sw.work_days as HARI_KERJA', 'sw.daily_rate as TARIF_HARIAN', 'sw.amount as NILAI', 'sw.is_receipt_recipient as PENERIMA_KUITANSI'], 'order' => [['sw.id', 'desc']]],
            'spj-honor' => ['group' => 'ENTITAS SPJ / DOMAIN NO_*', 'label' => 'SPJ · Honorarium', 'table' => 'spj_honors as sh', 'year' => 't.fiscal_year_id', 'joins' => [['transaction_items as ti', 'ti.id', '=', 'sh.transaction_item_id'], ['transactions as t', 't.id', '=', 'ti.transaction_id']], 'select' => ['sh.id as ID_HONOR', 't.no_bukti as NO_BUKTI', 'sh.name as NAMA', 'sh.nip as NIP', 'sh.nik as NIK', 'sh.npwp as NPWP', 'sh.position as JABATAN', 'sh.golongan as GOLONGAN', 'sh.gross_amount as BRUTO', 'sh.tax_amount as PAJAK', 'sh.net_amount as DIBAYARKAN'], 'order' => [['sh.id', 'desc']]],
            'riwayat-sinkron' => ['group' => 'RIWAYAT OPERASIONAL', 'label' => 'Riwayat Sinkronisasi ARKAS', 'table' => 'sync_runs', 'year' => 'fiscal_year_id', 'select' => ['source as SUMBER', 'status as STATUS', 'records_read as DATA_DIBACA', 'records_written as DATA_DITULIS', 'message as KETERANGAN', 'started_at as DIMULAI', 'finished_at as SELESAI'], 'order' => [['id', 'desc']]],
            'riwayat-paket' => ['group' => 'RIWAYAT OPERASIONAL', 'label' => 'Riwayat Perubahan Paket SPJ', 'table' => 'operational_audit_logs', 'year' => 'fiscal_year_id', 'select' => ['entity_id as ID_PAKET', 'action as AKSI', 'description as KETERANGAN', 'user_id as ID_PENGGUNA', 'created_at as WAKTU'], 'order' => [['id', 'desc']]],
        ];
    }
}
