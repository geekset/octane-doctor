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
}
