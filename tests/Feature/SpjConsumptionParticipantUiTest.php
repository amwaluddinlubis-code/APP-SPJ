<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpjConsumptionParticipantUiTest extends TestCase
{
    public function test_consumption_participants_support_drag_ordering_without_losing_primary_recipient(): void
    {
        $view = file_get_contents(resource_path('views/spj/partials/package/categories/konsumsi.blade.php'));
        $service = file_get_contents(app_path('Services/SpjTransactionDetailsService.php'));

        $this->assertStringContainsString('draggable="true"', $view);
        $this->assertStringContainsString('@dragstart.stop="startDrag(index, $event)"', $view);
        $this->assertStringContainsString('@drop.prevent="dropAt(index)"', $view);
        $this->assertStringContainsString('moveRow(fromIndex, toIndex)', $view);
        $this->assertStringContainsString(':key="row._key"', $view);
        $this->assertStringContainsString('const primaryRow = this.primaryIndex !== null ? this.rows[this.primaryIndex] : null;', $view);
        $this->assertStringContainsString('this.primaryIndex = this.rows.indexOf(primaryRow)', $view);
        $this->assertStringContainsString("'sort_order' => $sortOrder", $service);
    }

    public function test_consumption_participant_count_tracks_people_and_portions_remain_separate(): void
    {
        $view = file_get_contents(resource_path('views/spj/partials/package/categories/konsumsi.blade.php'));
        $useCase = file_get_contents(app_path('UseCases/Spj/UpdateSpjPackageDetailsUseCase.php'));

        $this->assertStringContainsString('listedParticipantCount', $view);
        $this->assertStringContainsString('Peserta terdaftar:', $view);
        $this->assertStringContainsString('Total porsi:', $view);
        $this->assertStringContainsString('Jumlah porsi boleh berbeda dari jumlah peserta.', $view);
        $this->assertStringContainsString('participantCount === listedParticipantCount', $view);

        $this->assertStringContainsString("->filter(fn (array $row): bool => filled($row['name'] ?? null))", $useCase);
        $this->assertStringContainsString('->count();', $useCase);
        $this->assertStringContainsString('Jumlah peserta harus sama dengan jumlah nama peserta terdaftar', $useCase);
        $this->assertStringNotContainsString('Jumlah peserta harus sama dengan total porsi', $useCase);
    }

    public function test_consumption_people_and_portions_keep_integer_input_rules(): void
    {
        $view = file_get_contents(resource_path('views/spj/partials/package/categories/konsumsi.blade.php'));
        $useCase = file_get_contents(app_path('UseCases/Spj/UpdateSpjPackageDetailsUseCase.php'));

        $this->assertStringContainsString('name="participant_count"', $view);
        $this->assertStringContainsString('participants[${index}][portions]', $view);
        $this->assertStringContainsString('step="1"', $view);
        $this->assertStringContainsString("'participant_count' => ['required_if:spj_category,KONSUMSI', 'nullable', 'integer'", $useCase);
        $this->assertStringContainsString("'participants.*.portions' => ['nullable', 'integer', 'min:1']", $useCase);
    }
}
