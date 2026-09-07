<x-layouts.tailwind-app>
    @php
        $rupiah = fn($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
        $totalItems = $transaction->items->sum('amount');
        $descriptionsComplete =
            $transaction->items->isNotEmpty() &&
            $transaction->items->every(fn($item) => filled($item->item_description));
        $descriptionsFilled = $transaction->items->filter(fn($item) => filled($item->item_description))->count();
        $spjTypeLabel = fn($value) => match (strtoupper((string) $value)) {
            'JASA_LAINNYA' => 'Jasa Lainnya',
            'SPPD' => 'SPPD',
            'HONOR_PEGAWAI' => 'Honor Pegawai',
            default => str_replace('_', ' ', (string) $value),
        };
        $spjGuidance = [
            'BARANG' => [
                'title' => 'Barang',
                'description' => 'Lengkapi uraian belanja barang serta data invoice atau pesanan pembelian.',
            ],
            'KONSUMSI' => [
                'title' => 'Konsumsi',
                'description' =>
                    'Lengkapi uraian belanja makanan/minuman (katering) serta data invoice atau pesanan pembelian.',
            ],
            'PEMELIHARAAN' => [
                'title' => 'Pemeliharaan',
                'description' => 'Lengkapi uraian pekerjaan, lokasi, periode, SPK, dan penandatangan.',
            ],
            'JASA_LAINNYA' => [
                'title' => 'Jasa Lainnya',
                'description' => 'Lengkapi uraian jasa, referensi pembayaran, serta penerima atau penandatangan.',
            ],
            'SPPD' => [
                'title' => 'SPPD',
                'description' => 'Lengkapi tujuan perjalanan, lokasi, periode, dan referensi pembayaran.',
            ],
            'HONOR_PEGAWAI' => [
                'title' => 'Honor Pegawai',
                'description' => 'Lengkapi uraian honor, periode pembayaran, dan nama penerima honor.',
            ],
        ];
        $selectedSpjType = strtoupper((string) $transaction->spj_category);
        $selectedSpjType = match ($selectedSpjType) {
            'BELANJA_MODAL' => 'BARANG',
            'PERJALANAN_DINAS' => 'SPPD',
            'HONOR_PEGAWAI' => 'HONOR_PEGAWAI',
            'LAINNYA' => 'JASA_LAINNYA',
            'UPAH' => 'PEMELIHARAAN',
            default => $selectedSpjType,
        };
        $participantRows = $transaction->participants
            ->map(
                fn($participant) => [
                    'name' => $participant->name,
                    'position' => $participant->position,
                    'portions' => (int) $participant->portions,
                ],
            )
            ->values()
            ->all();
        $dapodikParticipantRows = $dapodikTeachers
            ->map(
                fn($employee) => [
                    'employee_id' => $employee->id,
                    'name' => $employee->name,
                    'position' => $employee->position ?: $employee->staff_type,
                    'portions' => 1,
                ],
            )
            ->values()
            ->all();
        $employeeOptions = $dapodikEmployees
            ->map(
                fn($employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'position' => $employee->position ?: $employee->staff_type,
                ],
            )
            ->values()
            ->all();
        if ($selectedSpjType === 'KONSUMSI') {
            $existingParticipantNames = collect($participantRows)
                ->map(fn($row) => mb_strtolower(trim($row['name'])))
                ->all();
            $missingDapodikParticipants = collect($dapodikParticipantRows)->reject(
                fn($row) => in_array(mb_strtolower(trim($row['name'])), $existingParticipantNames, true),
            );
            $participantRows = collect($participantRows)->concat($missingDapodikParticipants)->values()->all();
        }
        $participantRows = old('participants', $participantRows);
        $purchaseDetails = $transaction->goods->first();
        $workDetails = $transaction->workOrder;
        $transactionDateLimit = $transaction->transaction_date?->format('Y-m-d');
        $effectiveTaxRate = fn($rate, $amount) => $rate !== null
            ? (float) $rate
            : ((float) $transaction->gross_amount > 0
                ? ((float) $amount / (float) $transaction->gross_amount) * 100
                : 0);
        $purchaseOrderDate =
            $purchaseDetails?->order_date?->format('Y-m-d') ?:
            $transaction->order_date?->format('Y-m-d') ?:
            $transactionDateLimit;
        $purchaseBapDate =
            $purchaseDetails?->bap_date?->format('Y-m-d') ?:
            $transaction->bap_date?->format('Y-m-d') ?:
            $transactionDateLimit;
        $purchaseBastDate =
            $purchaseDetails?->bast_date?->format('Y-m-d') ?:
            $transaction->bast_date?->format('Y-m-d') ?:
            $transactionDateLimit;
        $purchaseInvoiceDate = $transaction->invoice_date?->format('Y-m-d') ?: $transactionDateLimit;
        $workerRows = $transaction->workers
            ->map(
                fn($worker) => [
                    'name' => $worker->name,
                    'job_description' => $worker->job_description,
                    'work_days' => $worker->work_days,
                    'daily_rate' => $worker->daily_rate,
                    'is_receipt_recipient' => (bool) $worker->is_receipt_recipient,
                    'notes' => $worker->notes,
                ],
            )
            ->values()
            ->all();
        $workerRows = $workerRows ?: [
            [
                'name' => '',
                'job_description' => '',
                'work_days' => 1,
                'daily_rate' => 0,
                'is_receipt_recipient' => false,
                'notes' => '',
            ],
        ];
        $honorRows = $transaction->honors
            ->map(
                fn($honor) => [
                    'name' => $honor->name,
                    'job_description' => $honor->position,
                    'work_days' => $honor->honor_months,
                    'daily_rate' => $honor->rate_per_unit,
                    'is_receipt_recipient' => false,
                    'notes' => '',
                ],
            )
            ->values()
            ->all();
        $honorRows = old(
            'workers',
            $honorRows ?: [
                [
                    'name' => '',
                    'job_description' => '',
                    'work_days' => 1,
                    'daily_rate' => 0,
                    'is_receipt_recipient' => false,
                    'notes' => '',
                ],
            ],
        );
        $travelRows = $transaction->travels
            ->map(
                fn($travel) => [
                    'traveler_name' => $travel->traveler_name,
                    'destination' => $travel->destination,
                    'purpose' => $travel->purpose,
                    'departure_date' => optional($travel->departure_date)->format('Y-m-d') ?: $transactionDateLimit,
                    'assignment_letter_number' => $travel->assignment_letter_number,
                    'assignment_letter_date' =>
                        optional($travel->assignment_letter_date)->format('Y-m-d') ?: $transactionDateLimit,
                    'return_date' => optional($travel->return_date)->format('Y-m-d') ?: $transactionDateLimit,
                    'transport_mode' => $travel->transport_mode,
                    'amount' => $travel->amount,
                    'notes' => $travel->notes,
                ],
            )
            ->values()
            ->all();
        $travelRows = $travelRows ?: [
            [
                'traveler_name' => '',
                'destination' => '',
                'purpose' => '',
                'assignment_letter_number' => '',
                'assignment_letter_date' => $transactionDateLimit,
                'departure_date' => $transactionDateLimit,
                'return_date' => $transactionDateLimit,
                'transport_mode' => '',
                'amount' => 0,
                'notes' => '',
            ],
        ];
        $category = strtoupper((string) $selectedSpjType);
        $manualChecklist = [
            [
                'label' => 'Kategori SPJ',
                'ready' => filled($selectedSpjType),
                'hint' => 'Pilih kategori agar form sesuai skenario dokumen.',
            ],
            [
                'label' => 'Uraian dokumen',
                'ready' => filled($transaction->payment_description),
                'hint' => 'Isi uraian pembayaran yang akan masuk dokumen SPJ.',
            ],
            [
                'label' => 'Penerima kuitansi',
                'ready' => filled($transaction->effective_receipt_recipient_name),
                'hint' => 'Boleh berbeda dari penerima BKU/ARKAS.',
            ],
            [
                'label' => 'Metode pembayaran',
                'ready' => filled($transaction->payment_method),
                'hint' => 'Pilih Transfer Bank, SiPLah, atau Tunai.',
            ],
            [
                'label' => 'Uraian item SPJ',
                'ready' => $descriptionsComplete,
                'hint' => "{$descriptionsFilled} dari {$transaction->items->count()} item sudah lengkap.",
            ],
        ];
        $categoryChecklist = match ($category) {
            'BARANG' => [
                [
                    'label' => 'Data pembelian',
                    'ready' =>
                        filled($transaction->invoice_number) ||
                        filled($purchaseDetails?->order_number) ||
                        filled($transaction->order_number),
                    'hint' => 'Isi invoice atau nomor pesanan.',
                ],
                [
                    'label' => 'Dokumen penerimaan',
                    'ready' =>
                        filled($purchaseDetails?->bap_number) ||
                        filled($transaction->bap_number) ||
                        filled($purchaseDetails?->bast_number) ||
                        filled($transaction->bast_number),
                    'hint' => 'Isi BAP atau BAST jika sudah tersedia.',
                ],
            ],
            'KONSUMSI' => [
                [
                    'label' => 'Data acara',
                    'ready' => filled($transaction->event_name) || filled($transaction->event_location),
                    'hint' => 'Isi nama acara dan tempat.',
                ],
                [
                    'label' => 'Peserta/porsi',
                    'ready' => $transaction->participants->isNotEmpty(),
                    'hint' => 'Tambahkan minimal satu peserta atau dasar porsi.',
                ],
            ],
            'PEMELIHARAAN' => [
                [
                    'label' => 'Work order',
                    'ready' => filled($workDetails?->work_description),
                    'hint' => 'Isi deskripsi pekerjaan pemeliharaan.',
                ],
                [
                    'label' => 'Daftar pekerja',
                    'ready' => $transaction->workers->isNotEmpty(),
                    'hint' => 'Tambahkan pekerja dalam tabel.',
                ],
                [
                    'label' => 'Penerima kuitansi pekerja',
                    'ready' =>
                        filled($transaction->receipt_recipient_name) ||
                        $transaction->workers->contains(fn($worker) => (bool) $worker->is_receipt_recipient),
                    'hint' => 'Tandai salah satu pekerja atau isi penerima kuitansi manual.',
                ],
            ],
            'SPPD' => [
                [
                    'label' => 'Pelaksana perjalanan',
                    'ready' => $transaction->travels->isNotEmpty(),
                    'hint' => 'Satu pembayaran boleh berisi banyak pelaksana.',
                ],
                [
                    'label' => 'Tanggal perjalanan',
                    'ready' => $transaction->travels->contains(fn($travel) => filled($travel->departure_date)),
                    'hint' => 'Isi minimal tanggal berangkat.',
                ],
            ],
            'HONOR_PEGAWAI' => [
                [
                    'label' => 'Penerima honor',
                    'ready' => $transaction->honors->isNotEmpty(),
                    'hint' => 'Tambahkan minimal satu penerima honor.',
                ],
            ],
            'JASA_LAINNYA' => [
                [
                    'label' => 'Uraian jasa',
                    'ready' => filled($transaction->work_description) || filled($transaction->payment_description),
                    'hint' => 'Isi uraian jasa atau pembayaran.',
                ],
            ],
            default => [],
        };
        $isSiplah = strtolower((string) $paymentMethod) === 'siplah' || (bool) $transaction->is_siplah;
        if ($isSiplah) {
            $categoryChecklist = array_merge($categoryChecklist, [
                [
                    'label' => 'Penyedia SiPLah',
                    'ready' => filled($transaction->vendor_name),
                    'hint' => 'Informasi panduan; tidak memblokir status READY.',
                ],
                [
                    'label' => 'Nomor Pesanan SiPLah',
                    'ready' => filled($transaction->siplah_order_number),
                    'hint' => 'Nomor order marketplace, bukan Nomor Surat Pesanan SPJ.',
                ],
                [
                    'label' => 'Invoice SiPLah',
                    'ready' => filled($transaction->invoice_number),
                    'hint' => 'Informasi panduan; tidak memblokir status READY.',
                ],
                [
                    'label' => 'Referensi pembayaran',
                    'ready' => filled($transaction->payment_reference),
                    'hint' => 'Informasi panduan; tidak memblokir status READY.',
                ],
            ]);
        }
        $readinessChecklist = array_merge($manualChecklist, $categoryChecklist);
        $readyCount = collect($readinessChecklist)->where('ready', true)->count();
        $pendingChecklist = collect($readinessChecklist)->where('ready', false)->values();
        $completedChecklist = collect($readinessChecklist)->where('ready', true)->values();
        $packageLocked = $transaction->spjPackage && !$transaction->spjPackage->isEditable();
        $taxBreakdown = collect([
            'PPN' => $transaction->ppn,
            'PPh 21' => $transaction->pph21,
            'PPh 22' => $transaction->pph22,
            'PPh 23' => $transaction->pph23,
            'PPh 4(2)' => $transaction->pph4,
            'SSPD' => $transaction->sspd,
        ])->filter(fn($value) => (float) $value !== 0.0);
        $sourceStatus = strtoupper((string) ($transaction->source_status ?: 'ACTIVE'));
        $needsAttention = $sourceStatus === 'SOURCE_MISSING' || (bool) $transaction->requires_reconciliation;
    @endphp

    <div class="flex flex-col gap-6">
        @include('transactions.partials.detail.overview-header')
        @include('transactions.partials.detail.overview-status')
        @include('transactions.partials.detail.spj-builder-before-categories')
        @include('transactions.partials.spj.categories.index')
        @include('transactions.partials.detail.spj-builder-after-categories')
        @include('transactions.partials.detail.items')
    </div>
</x-layouts.tailwind-app>
