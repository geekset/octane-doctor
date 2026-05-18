<?php

namespace OctaneDoctor\Baseline;

use OctaneDoctor\Finding;

/**
 * Persists scan findings as a fingerprint snapshot on disk. The
 * baseline lets a team adopt the scanner gradually: existing findings
 * are recorded once and the next run only fails on new ones, which is
 * the only realistic way to roll out the package on a legacy app.
 */
class BaselineRepository
{
    public function load(string $path): Baseline
    {
        if (! is_file($path)) {
            return Baseline::empty();
        }

        $contents = @file_get_contents($path);

        if ($contents === false || $contents === '') {
            return Baseline::empty();
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return Baseline::empty();
        }

        $fingerprints = $decoded['fingerprints'] ?? [];

        if (! is_array($fingerprints)) {
            return Baseline::empty();
        }

        $fingerprints = array_values(array_filter($fingerprints, fn ($entry) => is_string($entry)));

        $generatedAt = is_string($decoded['generated_at'] ?? null)
            ? $decoded['generated_at']
            : null;

        return new Baseline($fingerprints, $generatedAt);
    }

    /**
     * @param  array<int, Finding>  $findings
     */
    public function save(string $path, array $findings): Baseline
    {
        $fingerprints = array_values(array_unique(
            array_map(fn (Finding $finding) => $finding->fingerprint(), $findings)
        ));

        $generatedAt = date('c');

        $payload = [
            'schema_version' => '1',
            'generated_at' => $generatedAt,
            'fingerprint_count' => count($fingerprints),
            'fingerprints' => $fingerprints,
        ];

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        return new Baseline($fingerprints, $generatedAt);
    }
}
