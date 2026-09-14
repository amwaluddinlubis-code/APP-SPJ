<?php

namespace App\Livewire;

use App\Services\DocumentStoragePathService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DocumentStorageSettings extends Component
{
    public string $path = '';

    public function mount(DocumentStoragePathService $storage): void
    {
        $this->path = (string) $storage->configuredPath();
    }

    public function save(DocumentStoragePathService $storage): void
    {
        $this->validate(['path' => ['required', 'string']]);
        if ($error = $storage->validatePath(trim($this->path))) {
            $this->addError('path', $error);

            return;
        }
        $storage->savePath(trim($this->path));
        session()->flash('success', 'Path penyimpanan dokumen berhasil disimpan.');
    }

    public function render(): View
    {
        return view('livewire.document-storage-settings');
    }
}
