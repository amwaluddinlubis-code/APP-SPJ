<x-layouts.tailwind-app>
    <div class="mx-auto max-w-4xl space-y-5">
        <x-page-header title="Reset Database Sekolah" subtitle="Hapus dan bangun ulang database sekolah aktif. Tindakan ini menghapus seluruh data tenant." kicker="Administrasi · Database Sekolah" />
        <livewire:database-reset-form :active="$active" :active-status="$activeStatus" />
    </div>
</x-layouts.tailwind-app>
