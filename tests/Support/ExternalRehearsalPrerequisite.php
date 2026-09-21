<?php

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

final class ExternalRehearsalPrerequisite
{
    /** @var array<string, true> */
    private static array $reported = [];

    /** @var array<string, string> */
    private static array $resolvedPaths = [];

    public static function skipIfUnavailable(TestCase $test, string $testClass): void
    {
        $requirements = self::requirements($testClass);

        if ($requirements['paths'] === []) {
            return;
        }

        $missing = array_values(array_filter(
            $requirements['paths'],
            static fn (string $path): bool => ! is_file($path),
        ));

        if ($missing === []) {
            return;
        }

        $message = 'RVR / NOT RUN: external '.$requirements['label'].' rehearsal fixture is unavailable. Missing: '
            .implode(', ', $missing).'.';

        if (! isset(self::$reported[$testClass])) {
            fwrite(STDERR, $message.PHP_EOL);
            self::$reported[$testClass] = true;
        }

        $test->markTestSkipped($message);
    }

    /**
     * @return array{label: string, paths: list<string>}
     */
    private static function requirements(string $testClass): array
    {
        if (str_ends_with($testClass, '\\V2BIsolatedSchemaTest')) {
            $path = self::$resolvedPaths[$testClass]
                ?? getenv('SPJ_V2_B_SOURCE_PATH')
                ?: storage_path('app/v2-b-isolated/tenant-10260756-v2b.sqlite');

            if (is_file($path)) {
                self::$resolvedPaths[$testClass] = $path;
                putenv('SPJ_V2_B_SOURCE_PATH='.$path);
            }

            return ['label' => 'V2-B', 'paths' => [$path]];
        }

        if (str_contains($testClass, '\\V2C') || str_contains($testClass, '\\V2D')) {
            if (str_ends_with($testClass, '\\V2DReadPathSelectorTest')) {
                return ['label' => '', 'paths' => []];
            }

            $clone = storage_path('app/school-databases/10260786/spj.sqlite');
            $configured = getenv('SPJ_V2_C_SOURCE_PATH') ?: config('spj.v2_c_source_path');
            $sourceCandidates = array_values(array_filter([
                is_string($configured) ? $configured : null,
                base_path('../../backupdata/datasmp.db'),
                storage_path('app/datasmp.db'),
            ]));
            $source = array_values(array_filter(
                $sourceCandidates,
                static fn (string $path): bool => is_file($path),
            ));

            return [
                'label' => 'V2-C/V2-D',
                'paths' => array_merge([$clone], $source === [] ? [(string) ($sourceCandidates[0] ?? storage_path('app/datasmp.db'))] : []),
            ];
        }

        return ['label' => '', 'paths' => []];
    }
}
