<?php

namespace Geekset\OctaneDoctor\Suppression;

use Geekset\OctaneDoctor\Finding;

/**
 * Suppression list driven by the `octane-doctor.ignore` config key.
 * Each entry is matched against both a finding's fingerprint (for
 * one-off, location-specific suppression) and its rule id (to turn a
 * whole rule off without removing it from the registry).
 *
 * Kept distinct from the baseline so the two suppression mechanisms
 * stay observable in CLI/JSON output: a baseline is a snapshot of
 * known issues a team is paying down, while an ignore entry is an
 * explicit, permanent "this case is acceptable for us" decision.
 */
final readonly class IgnoreList
{
    /**
     * @param  array<int, string>  $entries
     */
    public function __construct(
        public array $entries,
    ) {}

    public function contains(Finding $finding): bool
    {
        if ($this->entries === []) {
            return false;
        }

        return in_array($finding->ruleId, $this->entries, true)
            || in_array($finding->fingerprint(), $this->entries, true);
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param  array<int|string, mixed>  $values
     */
    public static function fromConfig(array $values): self
    {
        $entries = array_values(array_filter(
            $values,
            fn ($entry) => is_string($entry) && $entry !== '',
        ));

        return new self($entries);
    }
}
