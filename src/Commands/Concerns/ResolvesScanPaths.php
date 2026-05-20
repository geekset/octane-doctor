<?php

namespace OctaneDoctor\Commands\Concerns;

/**
 * Shared path-resolution helper for the scan and baseline commands.
 *
 * Configured paths that no longer exist on disk are reported as
 * warnings rather than failing the run, because legacy apps often
 * keep config entries pointing at folders that were renamed or
 * removed. Silently dropping them used to hide that fact, which
 * let users believe the scan covered everything when in practice
 * nothing was scanned.
 */
trait ResolvesScanPaths
{
    /**
     * @return array{resolved: array<int, string>, missing: array<int, string>}
     */
    protected function resolvePathInfo(): array
    {
        $configured = config('octane-doctor.paths', []);

        if (! is_array($configured)) {
            return ['resolved' => [], 'missing' => []];
        }

        $resolved = [];
        $missing = [];

        foreach ($configured as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            if (is_dir($path)) {
                $resolved[] = $path;
            } else {
                $missing[] = $path;
            }
        }

        return [
            'resolved' => $resolved,
            'missing' => $missing,
        ];
    }
}
