<?php

namespace App\Livewire;

use Livewire\Component;

class DatabaseManagerAlerts extends Component
{
    public string $type = '';

    public string $title = '';

    public string $message = '';

    public function mount(array $active, ?array $activeStatus = null): void
    {
        if (! ($active['school'] ?? null)) {
            $this->type = 'warning';
            $this->title = 'Belum ada database sekolah aktif';
            $this->message = 'Pilih sekolah terlebih dahulu untuk menampilkan status dan alat maintenance.';
        } elseif (! ($active['connected'] ?? false)) {
            $this->type = 'danger';
            $this->title = 'Koneksi database aktif bermasalah';
            $this->message = (string) ($active['error'] ?? 'Koneksi SQLite tidak dapat dibuka.');
        } elseif ($activeStatus && ! empty($activeStatus['issues'])) {
            $this->type = 'warning';
            $this->title = 'Database aktif memerlukan perhatian';
            $this->message = implode(' · ', $activeStatus['issues']);
        }
    }

    public function render()
    {
        return view('livewire.database-manager-alerts');
    }
}
