<?php

namespace OctaneDoctor\Commands;

use Illuminate\Console\Command;
use OctaneDoctor\Rules\Rule;
use OctaneDoctor\Scanning\RuleRegistry;

/**
 * Shows the full description, remediation guidance, and examples
 * for a single rule by id. Useful inside a code review or CI
 * annotation when the short finding summary is not enough context.
 */
class RulesViewCommand extends Command
{
    public $signature = 'octane-doctor:rules:view
        {rule : The rule id to view}';

    public $description = 'Show the detailed explanation for a single rule.';

    public function handle(RuleRegistry $registry): int
    {
        $rules = $registry->all();
        $ruleId = (string) $this->argument('rule');

        $rule = $this->findRule($rules, $ruleId);

        if ($rule === null) {
            $this->error("No rule registered with id '{$ruleId}'.");
            $this->line('');
            $this->line('Run octane-doctor:rules:list to see every registered rule.');

            return self::FAILURE;
        }

        $explanation = $rule->explanation();

        $this->line("Rule: {$rule->id()}");
        $this->line("Title: {$rule->title()}");
        $this->line("Severity: {$rule->severity()->value}");
        $this->line("Category: {$rule->category()->value}");
        $this->line("Risk class: {$rule->riskClass()->value}");
        $this->line('');
        $this->line('Why it matters:');
        $this->line('  '.$explanation->whyItMatters);
        $this->line('');
        $this->line('Remediation:');
        $this->line('  '.$explanation->remediation);

        if ($explanation->examples !== []) {
            $this->line('');
            $this->line('Examples:');

            foreach ($explanation->examples as $example) {
                $this->line('  '.$example);
            }
        }

        if ($explanation->docsUrl !== null) {
            $this->line('');
            $this->line("Docs: {$explanation->docsUrl}");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, Rule>  $rules
     */
    protected function findRule(array $rules, string $ruleId): ?Rule
    {
        foreach ($rules as $rule) {
            if ($rule->id() === $ruleId) {
                return $rule;
            }
        }

        return null;
    }
}
