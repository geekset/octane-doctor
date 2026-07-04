<?php

namespace OctaneDoctor\Commands;

use Illuminate\Console\Command;
use OctaneDoctor\Commands\Concerns\RendersTermwind;
use OctaneDoctor\Rules\Rule;
use OctaneDoctor\Scanning\RuleRegistry;

use function Termwind\render;

/**
 * Lists every registered rule (built-in plus custom) so a developer
 * can see what the scanner is checking and find the id to pass to
 * `octane-doctor:rules:view` or `octane-doctor:scan --rule`. The
 * output is intentionally terse: id, severity, category, risk class,
 * and title. Run rules:view for the full description and remediation
 * guidance.
 */
class RulesListCommand extends Command
{
    use RendersTermwind;

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

        $this->renderList($rules);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, Rule>  $rules
     */
    protected function renderList(array $rules): void
    {
        $this->useTermwind();

        foreach ($rules as $rule) {
            $this->renderRule($rule);
        }

        render(<<<'HTML'
            <div class="mx-2 mt-1 text-gray">Run <span class="font-bold">octane-doctor:rules:view &lt;rule-id&gt;</span> for the full description.</div>
        HTML);
    }

    protected function renderRule(Rule $rule): void
    {
        [$badgeBg, $badgeText] = $this->severityBadgeClasses($rule->severity());

        $severity = strtoupper($rule->severity()->value);
        $ruleId = $this->escape($rule->id());
        $category = $this->escape($rule->category()->value);
        $riskClass = $this->escape($rule->riskClass()->value);
        $title = $this->escape($rule->title());

        render(<<<HTML
            <div class="mx-2 mt-1">
                <span class="px-1 {$badgeBg} {$badgeText} font-bold">{$severity}</span>
                <span class="ml-1 font-bold">{$ruleId}</span>
                <span class="ml-1 text-gray">{$category} · {$riskClass}</span>
            </div>
        HTML);

        render(<<<HTML
            <div class="mx-2 ml-4 text-gray">{$title}</div>
        HTML);
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
                    'risk_class' => $rule->riskClass()->value,
                    'title' => $rule->title(),
                ],
                $rules,
            ),
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
