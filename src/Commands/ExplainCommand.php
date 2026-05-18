<?php

namespace OctaneDoctor\Commands;

use Illuminate\Console\Command;
use OctaneDoctor\Rules\Rule;
use OctaneDoctor\Scanning\RuleRegistry;

/**
 * Prints the detailed explanation, remediation, and examples for one
 * rule by id. Useful inside a code review or a CI annotation when the
 * short summary on a finding is not enough context.
 */
class ExplainCommand extends Command
{
    public $signature = 'octane-doctor:explain
        {rule? : The rule id to explain. Omit to list every available rule.}';

    public $description = 'Explain a rule in more detail (or list every registered rule).';

    public function handle(RuleRegistry $registry): int
    {
        $rules = $registry->all();
        $ruleId = $this->argument('rule');

        if (! is_string($ruleId) || $ruleId === '') {
            $this->renderList($rules);

            return self::SUCCESS;
        }

        $rule = $this->findRule($rules, $ruleId);

        if ($rule === null) {
            $this->error("No rule registered with id '{$ruleId}'.");
            $this->line('');
            $this->line('Registered rule ids:');

            foreach ($rules as $candidate) {
                $this->line('  '.$candidate->id());
            }

            return self::FAILURE;
        }

        $this->renderRule($rule);

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

    /**
     * @param  array<int, Rule>  $rules
     */
    protected function renderList(array $rules): void
    {
        if ($rules === []) {
            $this->info('No rules are currently registered.');

            return;
        }

        $this->line('Registered rules:');
        $this->line('');

        foreach ($rules as $rule) {
            $this->line(sprintf(
                '  %-30s %s severity / %s',
                $rule->id(),
                $rule->severity()->value,
                $rule->category()->value,
            ));
            $this->line('    '.$rule->title());
        }

        $this->line('');
        $this->line('Run octane-doctor:explain <rule-id> for the full description.');
    }

    protected function renderRule(Rule $rule): void
    {
        $explanation = $rule->explanation();

        $this->line("Rule: {$rule->id()}");
        $this->line("Title: {$rule->title()}");
        $this->line("Severity: {$rule->severity()->value}");
        $this->line("Category: {$rule->category()->value}");
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
    }
}
