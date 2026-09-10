<?php

namespace Tests\Feature;

use App\Models\Employee;
use Tests\TestCase;

class UnifiedEmployeeMasterTest extends TestCase
{
    public function test_employee_model_exposes_combined_provenance(): void
    {
        $employee = new Employee;
        $employee->last_seen_arkas_at = now();
        $employee->last_seen_dapodik_at = now();

        $this->assertSame('ARKAS + Dapodik', $employee->source_label);

        $manual = new Employee;
        $this->assertSame('Manual', $manual->source_label);
    }

    public function test_both_sync_flows_use_identity_resolution_and_duplicate_fusion(): void
    {
        $arkas = file_get_contents(app_path('Services/ArkasReferenceSynchronizationService.php'));
        $dapodik = file_get_contents(app_path('Services/DapodikSynchronizationService.php'));

        $this->assertStringContainsString('$identity->findMatch(', $arkas);
        $this->assertStringContainsString('$identity->fuseDuplicates(false);', $arkas);
        $this->assertStringContainsString("'last_seen_arkas_at'", $arkas);
        $this->assertStringContainsString("'payload' => \$this->mergeSourcePayload(\$employee->payload, 'arkas', \$record)", $arkas);

        $this->assertStringContainsString('$identity->findMatch(', $dapodik);
        $this->assertStringContainsString('$identity->fuseDuplicates(false);', $dapodik);
        $this->assertStringContainsString("'last_seen_dapodik_at'", $dapodik);
        $this->assertStringContainsString("'payload' => \$this->mergeSourcePayload(\$employee->payload, 'dapodik', \$row)", $dapodik);
    }

    public function test_synced_employee_rows_are_crud_managed_by_the_employee_module(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/EmployeeController.php'));
        $index = file_get_contents(resource_path('views/employees/index.blade.php'));
        $show = file_get_contents(resource_path('views/employees/show.blade.php'));

        $this->assertStringNotContainsString("source_type !== 'MANUAL'", $controller);
        $this->assertStringContainsString('$employee->delete();', $controller);
        $this->assertStringContainsString("'source' => ['nullable', 'in:ARKAS,DAPODIK,MANUAL']", $controller);
        $this->assertStringContainsString('$employee->source_label', $index);
        $this->assertStringContainsString("route('employees.edit'", $index);
        $this->assertStringContainsString("route('employees.destroy'", $show);
    }
}
