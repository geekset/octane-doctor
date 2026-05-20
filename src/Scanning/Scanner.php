<?php

namespace OctaneDoctor\Scanning;

use OctaneDoctor\Ast\FileWalker;
use OctaneDoctor\Finding;
use OctaneDoctor\Rules\AstVisitingRule;
use OctaneDoctor\Rules\Rule;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

/**
 * Owns the scan lifecycle: invokes each rule against the shared
 * ScanContext, collects findings into a single list, sorts them, and
 * captures the run duration. Rules themselves stay framework-version
 * aware via capability adapters; the Scanner stays dumb on purpose so
 * it can be unit-tested without booting Laravel.
 *
 * AST rules go through a single shared NodeTraverser pass per parsed
 * file. Without this, every AST rule re-reads every file and runs its
 * own traverser, so the work grows linearly with the rule count even
 * when the file set is unchanged. The single-pass path keeps the rule
 * count out of the dominant term on real applications.
 */
class Scanner
{
    protected FileWalker $walker;

    /**
     * @param  array<int, Rule>  $rules
     */
    public function __construct(
        protected array $rules,
        ?FileWalker $walker = null,
    ) {
        $this->walker = $walker ?? new FileWalker;
    }

    public function scan(ScanContext $context): ScanResult
    {
        $startedAt = microtime(true);

        $findings = [];

        $astRules = [];
        $otherRules = [];

        foreach ($this->rules as $rule) {
            if ($rule instanceof AstVisitingRule) {
                $astRules[] = $rule;
            } else {
                $otherRules[] = $rule;
            }
        }

        foreach ($otherRules as $rule) {
            foreach ($rule->run($context) as $finding) {
                $findings[] = $this->normalize($finding, $context);
            }
        }

        if ($astRules !== [] && $context->paths !== []) {
            foreach ($this->walker->walk($context->paths) as $parsed) {
                $traverser = new NodeTraverser(new NameResolver);

                $visitors = [];

                foreach ($astRules as $rule) {
                    $visitor = $rule->buildVisitor($parsed);
                    $visitors[] = [$rule, $visitor];
                    $traverser->addVisitor($visitor);
                }

                $traverser->traverse($parsed->ast);

                foreach ($visitors as [$rule, $visitor]) {
                    foreach ($rule->findingsFor($parsed, $visitor) as $finding) {
                        $findings[] = $this->normalize($finding, $context);
                    }
                }
            }
        }

        /*
         * Deterministic ordering: severity desc so the most actionable
         * findings surface first, then rule id / file / line so output
         * is stable across runs and golden-file tests stay reliable.
         */
        usort(
            $findings,
            fn (Finding $a, Finding $b) => $b->severity->weight() <=> $a->severity->weight()
                ?: strcmp($a->ruleId, $b->ruleId)
                ?: strcmp($a->filePath ?? '', $b->filePath ?? '')
                ?: ($a->line ?? 0) <=> ($b->line ?? 0)
        );

        $durationMs = (microtime(true) - $startedAt) * 1000;

        return new ScanResult($findings, $context->paths, $durationMs);
    }

    protected function normalize(Finding $finding, ScanContext $context): Finding
    {
        if ($context->basePath === null) {
            return $finding;
        }

        return $finding->relativizeFilePath($context->basePath);
    }
}
