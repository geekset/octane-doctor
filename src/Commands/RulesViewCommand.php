<?php

namespace OctaneDoctor\Commands;

use Illuminate\Console\Command;
use OctaneDoctor\Commands\Concerns\RendersTermwind;
use OctaneDoctor\Rules\Rule;
use OctaneDoctor\Scanning\RuleRegistry;

use function Termwind\render;

/**
 * Shows the full description, remediation guidance, and examples
 * for a single rule by id. Useful inside a code review or CI
 * annotation when the short finding summary is not enough context.
 */
class RulesViewCommand extends Command
{
    use RendersTermwind;

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

        $this->renderRule($rule);

        return self::SUCCESS;
    }

    protected function renderRule(Rule $rule): void
    {
        $this->useTermwind();

        [$badgeBg, $badgeText] = $this->severityBadgeClasses($rule->severity());

        $severity = strtoupper($rule->severity()->value);
        $ruleId = $this->escape($rule->id());
        $title = $this->escape($rule->title());
        $category = $this->escape($rule->category()->value);
        $riskClass = $this->escape($rule->riskClass()->value);

        render(<<<HTML
            <div class="mx-2 mt-1">
                <span class="px-1 {$badgeBg} {$badgeText} font-bold">{$severity}</span>
                <span class="ml-1 font-bold">{$ruleId}</span>
                <span class="ml-1 text-gray">{$title}</span>
            </div>
        HTML);

        render(<<<HTML
            <div class="mx-2 ml-4 text-gray">Category: {$category} · Risk class: {$riskClass}</div>
        HTML);

        $explanation = $rule->explanation();

        $why = $this->escape($explanation->whyItMatters);
        $fix = $this->escape($explanation->remediation);

        render(<<<HTML
            <div class="mx-2 mt-1 ml-4"><span class="font-bold text-yellow">Why:</span> {$why}</div>
        HTML);

        render(<<<HTML
            <div class="mx-2 ml-4"><span class="font-bold text-green">Fix:</span> {$fix}</div>
        HTML);

        $this->renderExamples($explanation->examples);

        if ($explanation->docsUrl !== null) {
            $docsUrl = $this->escape($explanation->docsUrl);

            render(<<<HTML
                <div class="mx-2 mt-1 ml-4"><span class="font-bold">Docs:</span> <span class="text-blue underline">{$docsUrl}</span></div>
            HTML);
        }
    }

    /**
     * @param  array<int, string>  $examples
     */
    protected function renderExamples(array $examples): void
    {
        if ($examples === []) {
            return;
        }

        render(<<<'HTML'
            <div class="mx-2 mt-1 ml-4 font-bold">Examples:</div>
        HTML);

        foreach ($examples as $example) {
            $escaped = $this->escape($example);

            render(<<<HTML
                <div class="mx-2 ml-6 text-gray">{$escaped}</div>
            HTML);
        }
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
