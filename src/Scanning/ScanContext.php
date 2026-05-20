<?php

namespace OctaneDoctor\Scanning;

use Illuminate\Contracts\Foundation\Application;

final readonly class ScanContext
{
    /**
     * @param  array<int, string>  $paths
     */
    public function __construct(
        public Application $app,
        public array $paths,
        public ?string $basePath = null,
    ) {}

    /**
     * Return true when the given absolute file path sits inside one
     * of the configured scan paths. Container-walking rules use this
     * to avoid emitting findings from vendor classes when the user
     * has scoped the scan to application code.
     *
     * An empty paths list disables scoping so callers that build a
     * ScanContext without configured paths (most unit tests) keep
     * receiving every finding their rule produces.
     */
    public function isPathInScope(?string $absolutePath): bool
    {
        if ($absolutePath === null) {
            return false;
        }

        if ($this->paths === []) {
            return true;
        }

        $normalized = $this->normalize($absolutePath);

        foreach ($this->paths as $path) {
            $prefix = $this->normalize($path).'/';

            if (str_starts_with($normalized, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
