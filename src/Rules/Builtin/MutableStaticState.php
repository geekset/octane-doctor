<?php

namespace Geekset\OctaneDoctor\Rules\Builtin;

use Geekset\OctaneDoctor\Ast\FileWalker;
use Geekset\OctaneDoctor\Ast\ParsedFile;
use Geekset\OctaneDoctor\Enums\Category;
use Geekset\OctaneDoctor\Enums\Severity;
use Geekset\OctaneDoctor\Finding;
use Geekset\OctaneDoctor\Rules\Rule;
use Geekset\OctaneDoctor\Scanning\ScanContext;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;

/**
 * Flags mutable static class properties. Under Octane these live for
 * the lifetime of the worker, so any write leaks state across requests
 * unless the property is explicitly safe (a const or read-only cache
 * primed at boot). This rule reports the declaration site; a richer
 * check that traces writes is a deferred follow-up.
 */
class MutableStaticState implements Rule
{
    /**
     * Static property overrides on framework base classes that are
     * documented to be set once at class-definition time. These behave
     * like configuration constants under Octane, not request state,
     * and would otherwise dominate the noise on a typical Laravel app.
     *
     * @var array<string, array<int, string>>
     */
    protected const SAFE_PARENT_OVERRIDES = [
        'Illuminate\\Http\\Resources\\Json\\JsonResource' => ['wrap'],
        'Illuminate\\Http\\Resources\\Json\\ResourceCollection' => ['wrap'],
        'Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection' => ['wrap'],
        'Illuminate\\Database\\Eloquent\\Model' => [
            'snakeAttributes',
            'unguarded',
            'modelsShouldPreventLazyLoading',
            'modelsShouldPreventSilentlyDiscardingAttributes',
            'modelsShouldPreventAccessingMissingAttributes',
        ],
    ];

    public function __construct(
        protected FileWalker $walker,
    ) {}

    public function id(): string
    {
        return 'mutable-static-state';
    }

    public function title(): string
    {
        return 'Mutable static state';
    }

    public function severity(): Severity
    {
        return Severity::Medium;
    }

    public function category(): Category
    {
        return Category::StaticState;
    }

    public function run(ScanContext $context): iterable
    {
        foreach ($this->walker->walk($context->paths) as $parsed) {
            yield from $this->inspect($parsed);
        }
    }

    /**
     * @return iterable<Finding>
     */
    protected function inspect(ParsedFile $parsed): iterable
    {
        $safeOverrides = self::SAFE_PARENT_OVERRIDES;

        $visitor = new class($safeOverrides) extends NodeVisitorAbstract
        {
            /** @var array<int, array{className: string, propertyName: string, line: int}> */
            public array $hits = [];

            protected ?string $currentClass = null;

            protected ?string $currentParent = null;

            /**
             * @param  array<string, array<int, string>>  $safeOverrides
             */
            public function __construct(
                protected array $safeOverrides,
            ) {}

            public function enterNode(Node $node): null
            {
                if ($node instanceof Class_) {
                    $this->currentClass = $node->namespacedName?->toString() ?? $node->name?->toString();
                    $this->currentParent = $node->extends?->toString();
                }

                if ($node instanceof Trait_) {
                    $this->currentClass = $node->namespacedName?->toString() ?? $node->name?->toString();
                    $this->currentParent = null;
                }

                if ($node instanceof Property && $node->isStatic()) {
                    foreach ($node->props as $prop) {
                        $propertyName = $prop->name->toString();

                        if ($this->isSafeOverride($propertyName)) {
                            continue;
                        }

                        $this->hits[] = [
                            'className' => $this->currentClass ?? '<anonymous>',
                            'propertyName' => $propertyName,
                            'line' => $node->getStartLine(),
                        ];
                    }
                }

                return null;
            }

            public function leaveNode(Node $node): null
            {
                if ($node instanceof Class_ || $node instanceof Trait_) {
                    $this->currentClass = null;
                    $this->currentParent = null;
                }

                return null;
            }

            protected function isSafeOverride(string $propertyName): bool
            {
                if ($this->currentParent === null) {
                    return false;
                }

                $safeProperties = $this->safeOverrides[$this->currentParent] ?? [];

                return in_array($propertyName, $safeProperties, true);
            }
        };

        $traverser = new NodeTraverser(new NameResolver, $visitor);
        $traverser->traverse($parsed->ast);

        foreach ($visitor->hits as $hit) {
            yield new Finding(
                ruleId: $this->id(),
                title: $this->title(),
                severity: $this->severity(),
                category: $this->category(),
                summary: "Class {$hit['className']} declares mutable static property \${$hit['propertyName']}.",
                whyItMatters: 'Static class properties persist across requests under Octane workers. Any mutation written during one request stays visible to every subsequent request handled by the same worker.',
                remediation: 'Move the state onto an instance, behind a scoped() container binding, or into a per-request cache. If the value is constant, use a class constant instead.',
                filePath: $parsed->path(),
                line: $hit['line'],
                symbol: "{$hit['className']}::\${$hit['propertyName']}",
            );
        }
    }
}
