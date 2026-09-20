<x-layouts.tailwind-app>
    @php
        $databaseCount = $list->count();
        $activeTableCount = count($tables ?? []);
        $centralTableCount = count($centralTables ?? []);
    @endphp

    <div id="database-control-center" class="db-control-center space-y-5">
        <x-page-header title="Pusat Kontrol Database Sekolah" subtitle="Pantau database aktif, kesehatan SQLite, struktur tabel, dan maintenance dari satu ruang kerja." kicker="PENGATURAN · DATABASE AKTIF">
            <x-slot:actions>
                <x-ui.button variant="secondary" :href="route('schools.select')">Ganti sekolah</x-ui.button>
                <x-ui.button variant="secondary" :href="route('school-backups.index')">Backup &amp; Restore</x-ui.button>
                @if($active['school'])<x-ui.button variant="secondary" :href="route('database-manager.reset-form')">Reset database</x-ui.button>@endif
            </x-slot:actions>

            <livewire:database-status-summary :active="$active" :active-status="$activeStatus" />
        </x-page-header>

        <livewire:database-manager-alerts :active="$active" :active-status="$activeStatus" />

        <section id="db-tabs" class="db-workspace">
            <livewire:database-manager-tabs :database-count="$databaseCount" :table-count="$activeTableCount" :central-table-count="$centralTableCount" />
            @include('database-manager.partials.overview')
            @include('database-manager.partials.school-list')
            @include('database-manager.partials.tables')
            @include('database-manager.partials.diagnostics')
            @include('database-manager.partials.maintenance')
        </section>

        <section class="db-connection-footnote"><div><p class="db-eyebrow">Sumber koneksi</p><strong>SchoolDatabaseManager adalah sumber kebenaran koneksi tenant.</strong><p>Aktivasi sekolah memperbarui session dan koneksi secara terpusat.</p></div><code>{{ $active['database'] ?: 'database belum aktif' }}</code></section>
    </div>
</x-layouts.tailwind-app>
