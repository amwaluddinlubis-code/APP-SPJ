<div data-livewire-tabs="true" class="db-tabbar">
    <nav class="flex gap-1 overflow-x-auto" aria-label="Navigasi database">
        <button type="button" wire:click="selectTab('overview')" data-tab="overview" class="tab-btn" data-active="{{ $activeTab === 'overview' ? 'true' : 'false' }}">Ringkasan</button>
        <button type="button" wire:click="selectTab('list')" data-tab="list" class="tab-btn" data-active="{{ $activeTab === 'list' ? 'true' : 'false' }}">Database Sekolah <span class="db-tab-count">{{ $databaseCount }}</span></button>
        <button type="button" wire:click="selectTab('tables')" data-tab="tables" class="tab-btn" data-active="{{ $activeTab === 'tables' ? 'true' : 'false' }}">Explorer Tabel <span class="db-tab-count">{{ $tableCount }}</span></button>
        <button type="button" wire:click="selectTab('diagnostic')" data-tab="diagnostic" class="tab-btn" data-active="{{ $activeTab === 'diagnostic' ? 'true' : 'false' }}">Diagnostik</button>
        <button type="button" wire:click="selectTab('maintenance')" data-tab="maintenance" class="tab-btn" data-active="{{ $activeTab === 'maintenance' ? 'true' : 'false' }}">Maintenance</button>
    </nav>
</div>
