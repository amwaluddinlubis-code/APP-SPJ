<div data-livewire-alerts="true">
    @if($type)
        <x-ui.alert :type="$type" :title="$title">{{ $message }}</x-ui.alert>
    @endif
</div>
