<?php

namespace OctaneDoctor\Commands\Concerns;

use OctaneDoctor\Enums\Severity;
use Termwind\Termwind;

use function Termwind\render;

/**
 * Shared Termwind helpers so every octane-doctor command renders with
 * the same visual language: severity badges, escaping, and the
 * missing-path warning block.
 */
trait RendersTermwind
{
    protected function useTermwind(): void
    {
        Termwind::renderUsing($this->output);
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @return array{string, string} */
    protected function severityBadgeClasses(Severity $severity): array
    {
        return match ($severity) {
            Severity::High => ['bg-red-600', 'text-white'],
            Severity::Medium => ['bg-yellow-500', 'text-black'],
            Severity::Low => ['bg-blue-500', 'text-white'],
            Severity::Info => ['bg-gray-500', 'text-white'],
        };
    }

    /**
     * @param  array<int, string>  $missingPaths
     */
    protected function renderMissingPathWarnings(array $missingPaths): void
    {
        foreach ($missingPaths as $path) {
            $escaped = $this->escape($path);

            render(<<<HTML
                <div class="mx-2 mt-1">
                    <span class="px-1 bg-yellow-500 text-black font-bold">WARNING</span>
                    <span class="ml-1">configured scan path does not exist: {$escaped}</span>
                </div>
            HTML);
        }
    }
}
