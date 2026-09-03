<?php

return [
    'after' => ':attribute harus setelah tanggal pembanding.',
    'after_or_equal' => ':attribute tidak boleh lebih awal dari tanggal sebelumnya.',
    'before' => ':attribute harus sebelum tanggal pembanding.',
    'before_or_equal' => ':attribute tidak boleh melewati batas tanggal yang ditentukan.',
    'date' => ':attribute harus berupa tanggal yang valid.',
    'date_equals' => ':attribute harus sama dengan tanggal pembanding.',
    'date_format' => ':attribute harus sesuai dengan format :format.',
    'required' => ':attribute wajib diisi.',
    'attributes' => [
        'invoice_date' => 'Tanggal Invoice',
        'order_date' => 'Tanggal Pesanan',
        'bap_date' => 'Tanggal BAP',
        'bast_date' => 'Tanggal BAST',
        'spk_date' => 'Tanggal SPK',
        'rab_date' => 'Tanggal RAB',
        'work_started_at' => 'Tanggal Mulai',
        'work_completed_at' => 'Tanggal Selesai',
        'payment_date' => 'Tanggal Pembayaran',
        'receipt_date' => 'Tanggal Penerimaan',
        'travels.*.assignment_letter_date' => 'Tanggal Surat Tugas',
        'travels.*.departure_date' => 'Tanggal Berangkat',
        'travels.*.return_date' => 'Tanggal Pulang',
    ],
];
