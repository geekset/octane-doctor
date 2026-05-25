<?php

namespace OctaneDoctor\Baseline;

use OctaneDoctor\Finding;

final readonly class Baseline
{
    /**
     * Hash-keyed mirror of `$fingerprints` so `contains()` runs in
     * constant time. A legacy application that has paid down a few
     * hundred findings through the baseline workflow turns the
     * per-finding suppression check into a hot path for every scan,
     * and a linear `in_array()` over that list is wasted work.
     *
     * @var array<string, true>
     */
    private array $lookup;

    /**
     * @param  array<int, string>  $fingerprints
     */
    public function __construct(
        public array $fingerprints,
        public ?string $generatedAt = null,
    ) {
        $this->lookup = array_fill_keys($fingerprints, true);
    }

    public function contains(Finding $finding): bool
    {
        return isset($this->lookup[$finding->fingerprint()]);
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
