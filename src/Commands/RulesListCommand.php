<?php

namespace OctaneDoctor\Commands;

use Illuminate\Console\Command;
use OctaneDoctor\Rules\Rule;
use OctaneDoctor\Scanning\RuleRegistry;

/**
 * Lists every registered rule (built-in plus custom) so a developer
 * can see what the scanner is checking and find the id to pass to
 * `octane-doctor:rules:view` or `octane-doctor:scan --rule`. The
 * output is intentionally terse: id, severity, category, and title.
 * Run rules:view for the full description and remediation guidance.
 */
class RulesListCommand extends Command
{
    public $signature = 'octane-doctor:rules:list
        {--format= : Output format (table, json)}';

    public $description = 'List every registered Octane Doctor rule.';

    public function handle(RuleRegistry $registry): int
    {
        $rules = $registry->all();

        if ($rules === []) {
            $this->info('No rules are currently registered.');

            return self::SUCCESS;
        }

        if ($this->option('format') === 'json') {
            $this->renderJson($rules);

            return self::SUCCESS;
        }

        $this->renderTable($rules);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, Rule>  $rules
     */
    protected function renderTable(array $rules): void
    {
        $rows = array_map(
            fn (Rule $rule) => [
                $rule->id(),
                $rule->severity()->value,
                $rule->category()->value,
                $rule->title(),
            ],
            $rules,
        );

        $this->table(['Rule id', 'Severity', 'Category', 'Title'], $rows);

        $this->line('Run octane-doctor:rules:view <rule-id> for the full description.');
    }

    /**
     * @param  array<int, Rule>  $rules
     */
    protected function renderJson(array $rules): void
    {
        $payload = [
            'schema_version' => '1',
            'rules' => array_map(
                fn (Rule $rule) => [
                    'id' => $rule->id(),
                    'severity' => $rule->severity()->value,
                    'category' => $rule->category()->value,
                    'title' => $rule->title(),
                ],
                $rules,
            ),
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
