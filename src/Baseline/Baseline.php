<?php

namespace Geekset\OctaneDoctor\Baseline;

use Geekset\OctaneDoctor\Finding;

final readonly class Baseline
{
    /**
     * @param  array<int, string>  $fingerprints
     */
    public function __construct(
        public array $fingerprints,
        public ?string $generatedAt = null,
    ) {}

    public function contains(Finding $finding): bool
    {
        return in_array($finding->fingerprint(), $this->fingerprints, true);
    }

    public function count(): int
    {
        return count($this->fingerprints);
    }

    public static function empty(): self
    {
        return new self([]);
    }
}
