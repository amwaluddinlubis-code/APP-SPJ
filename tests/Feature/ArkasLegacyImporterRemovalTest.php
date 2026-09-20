<?php

namespace Tests\Feature;

use App\Services\ArkasMirrorManifest;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ArkasLegacyImporterRemovalTest extends TestCase
{
    public function test_generic_mapping_routes_and_runtime_files_are_removed(): void
    {
        $routeNames = collect(Route::getRoutes()->getRoutes())
            ->map(static fn ($route): ?string => $route->getName())
            ->filter()
            ->values();

        self::assertFalse($routeNames->contains('arkas.importer'));
        self::assertFalse($routeNames->contains('arkas.importer.mapping.store'));
        self::assertFalse($routeNames->contains('arkas.importer.preview'));
        self::assertFalse($routeNames->contains('arkas.importer.sync'));
        self::assertTrue($routeNames->contains('arkas.raw-mirror'));
        self::assertFileDoesNotExist(app_path('Http/Controllers/ArkasImporterController.php'));
        self::assertFileDoesNotExist(app_path('Services/ArkasGenericImportService.php'));
        self::assertFileDoesNotExist(app_path('Services/ArkasImportConfigurationService.php'));
        self::assertFileDoesNotExist(app_path('Services/ArkasImportGuard.php'));
        self::assertFileDoesNotExist(app_path('Services/ArkasImportRowSynchronizer.php'));
        self::assertFileDoesNotExist(app_path('Services/ArkasStagingService.php'));
        self::assertFileDoesNotExist(resource_path('views/arkas/importer.blade.php'));
    }

    public function test_manifest_is_the_only_import_allowlist_and_unknown_tables_are_ignored(): void
    {
        $manifest = new ArkasMirrorManifest;

        self::assertSame([], $manifest->importableTables(['unknown_dynamic_table']));
        self::assertNotEmpty($manifest->entries());
        self::assertFileExists(app_path('Services/ArkasRawMirrorService.php'));
        self::assertFileExists(app_path('Services/ArkasPersistentReferenceAuthority.php'));
    }
}
