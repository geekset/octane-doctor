<?php

namespace OctaneDoctor\Rules\Builtin;

use OctaneDoctor\Ast\FileWalker;
use OctaneDoctor\Ast\ParsedFile;
use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;
use OctaneDoctor\Rules\AstVisitingRule;
use OctaneDoctor\Rules\RuleExplanation;
use OctaneDoctor\Scanning\ScanContext;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\UnionType;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;

/**
 * Flags classes that hold the container or the Application instance
 * as an instance property. The container itself is long-lived, so
 * caching it on a long-lived service rarely causes harm directly,
 * but it almost always signals that the class will pull resolved
 * dependencies out of it at unpredictable times. Under Octane those
 * captured resolutions can outlive the request that produced them
 * and leak scoped state across requests.
 */
class ContainerAsProperty implements AstVisitingRule
{
    /**
     * Type names whose instances represent the container/application.
     *
     * @var array<int, string>
     */
    protected const CONTAINER_TYPES = [
        'Illuminate\\Contracts\\Container\\Container',
        'Illuminate\\Container\\Container',
        'Illuminate\\Contracts\\Foundation\\Application',
        'Illuminate\\Foundation\\Application',
    ];

    /**
     * Traits that signal the class is constructed per dispatch.
     *
     * @var array<int, string>
     */
    protected const PER_DISPATCH_TRAITS = [
        'Illuminate\\Foundation\\Events\\Dispatchable',
        'Illuminate\\Bus\\Queueable',
    ];

    /**
     * Base classes that are known to be safe for this rule. Two groups:
     *
     * - Mailable, Notification, FormRequest, Controller are
     *   constructed per dispatch or per request, so a captured
     *   container reference cannot outlive the dispatch.
     * - ServiceProvider stores the Application on the base class by
     *   convention and uses it during register/boot only. Laravel
     *   itself owns the lifecycle, and Octane keeps the app honest
     *   about scoped bindings, so flagging this would generate a
     *   finding for every service provider in every app.
     *
     * @var array<int, string>
     */
    protected const SAFE_PARENTS = [
        'Illuminate\\Mail\\Mailable',
        'Illuminate\\Notifications\\Notification',
        'Illuminate\\Foundation\\Http\\FormRequest',
        'Illuminate\\Routing\\Controller',
        'Illuminate\\Support\\ServiceProvider',
    ];

    public function __construct(
        protected FileWalker $walker,
    ) {}

    public function id(): string
    {
        return 'container-as-property';
    }

    public function title(): string
    {
        return 'Container or Application stored as a class property';
    }

    public function severity(): Severity
    {
        return Severity::Medium;
    }

    public function category(): Category
    {
        return Category::ContainerLifecycle;
    }

    public function explanation(): RuleExplanation
    {
        return new RuleExplanation(
            whyItMatters: 'Under Octane the same service instance is reused across requests, and keeping the container or Application as a property usually leads to caching resolved dependencies on the same instance. Anything resolved that way captures the request that triggered the resolution and stays stale for every later request.',
            remediation: 'Receive the specific dependency you actually need through constructor injection or a method parameter. If you genuinely need late binding resolution, call app() or App::make() at the moment of use rather than once at construction time.',
            examples: [
                'class Foo { public function __construct(protected Container $container) {} } // flagged',
                'class Foo { public function __construct(protected Cache $cache) {} } // safe',
            ],
        );
    }

    public function run(ScanContext $context): iterable
    {
        foreach ($this->walker->walk($context->paths) as $parsed) {
            $visitor = $this->buildVisitor($parsed);
            $traverser = new NodeTraverser(new NameResolver, $visitor);
            $traverser->traverse($parsed->ast);

            yield from $this->findingsFor($parsed, $visitor);
        }
    }

    public function buildVisitor(ParsedFile $parsed): NodeVisitor
    {
        $containerTypes = self::CONTAINER_TYPES;
        $perDispatchTraits = self::PER_DISPATCH_TRAITS;
        $safeParents = self::SAFE_PARENTS;

        return new class($containerTypes, $perDispatchTraits, $safeParents) extends NodeVisitorAbstract
        {
            /** @var array<int, array{className: string, propertyName: string, typeName: string, line: int}> */
            public array $hits = [];

            protected ?string $currentClass = null;

            protected bool $currentClassSkipped = false;

            /** @var array<int, array{className: string, propertyName: string, typeName: string, line: int}> */
            protected array $pendingHits = [];

            /**
             * @param  array<int, string>  $containerTypes
             * @param  array<int, string>  $perDispatchTraits
             * @param  array<int, string>  $safeParents
             */
            public function __construct(
                protected array $containerTypes,
                protected array $perDispatchTraits,
                protected array $safeParents,
            ) {}

            public function enterNode(Node $node): null
            {
                if ($node instanceof Class_) {
                    $this->currentClass = $node->namespacedName?->toString() ?? $node->name?->toString();
                    $this->pendingHits = [];
                    $this->currentClassSkipped = $this->parentIsSafe($node);
                }

                if ($node instanceof TraitUse && $this->currentClass !== null) {
                    foreach ($node->traits as $trait) {
                        if (in_array($trait->toString(), $this->perDispatchTraits, true)) {
                            $this->currentClassSkipped = true;
                        }
                    }
                }

                if ($node instanceof ClassMethod && $node->name->toLowerString() === '__construct') {
                    foreach ($node->params as $param) {
                        $this->checkPromotedProperty($param);
                    }
                }

                if ($node instanceof Property && $this->currentClass !== null) {
                    $this->checkRegularProperty($node);
                }

                return null;
            }

            public function leaveNode(Node $node): null
            {
                if ($node instanceof Class_) {
                    if (! $this->currentClassSkipped) {
                        foreach ($this->pendingHits as $hit) {
                            $this->hits[] = $hit;
                        }
                    }

                    $this->currentClass = null;
                    $this->currentClassSkipped = false;
                    $this->pendingHits = [];
                }

                return null;
            }

            protected function parentIsSafe(Class_ $node): bool
            {
                $parent = $node->extends?->toString();

                return $parent !== null && in_array($parent, $this->safeParents, true);
            }

            protected function checkRegularProperty(Property $node): void
            {
                $typeName = $this->resolveTypeName($node->type);

                if ($typeName === null || ! in_array($typeName, $this->containerTypes, true)) {
                    return;
                }

                foreach ($node->props as $prop) {
                    $this->pendingHits[] = [
                        'className' => $this->currentClass ?? '<anonymous>',
                        'propertyName' => $prop->name->toString(),
                        'typeName' => $typeName,
                        'line' => $node->getStartLine(),
                    ];
                }
            }

            protected function checkPromotedProperty(Param $param): void
            {
                if ($param->flags === 0) {
                    return;
                }

                $typeName = $this->resolveTypeName($param->type);

                if ($typeName === null || ! in_array($typeName, $this->containerTypes, true)) {
                    return;
                }

                $varName = $param->var instanceof Node\Expr\Variable && is_string($param->var->name)
                    ? $param->var->name
                    : '<unknown>';

                $this->pendingHits[] = [
                    'className' => $this->currentClass ?? '<anonymous>',
                    'propertyName' => $varName,
                    'typeName' => $typeName,
                    'line' => $param->getStartLine(),
                ];
            }

            protected function resolveTypeName(Identifier|Name|ComplexType|null $type): ?string
            {
                if ($type instanceof NullableType) {
                    return $this->resolveTypeName($type->type);
                }

                if ($type instanceof Name) {
                    return $type->toString();
                }

                if ($type instanceof UnionType || $type instanceof IntersectionType) {
                    foreach ($type->types as $inner) {
                        $resolved = $this->resolveTypeName($inner);

                        if ($resolved !== null && in_array($resolved, $this->containerTypes, true)) {
                            return $resolved;
                        }
                    }
                }

                return null;
            }
        };
    }

    public function findingsFor(ParsedFile $parsed, NodeVisitor $visitor): iterable
    {
        /** @var array<int, array{className: string, propertyName: string, typeName: string, line: int}> $hits */
        $hits = property_exists($visitor, 'hits') ? $visitor->hits : [];

        foreach ($hits as $hit) {
            yield new Finding(
                ruleId: $this->id(),
                title: $this->title(),
                severity: $this->severity(),
                category: $this->category(),
                summary: "Class {$hit['className']} stores {$hit['typeName']} on property \${$hit['propertyName']}.",
                whyItMatters: 'Under Octane the same service instance is reused across requests, and keeping the container or Application as a property usually leads to caching resolved dependencies on the same instance. Anything resolved that way captures the request that triggered the resolution and stays stale for every later request.',
                remediation: 'Receive the specific dependency you actually need through constructor injection or a method parameter. If you genuinely need late-binding resolution, call app() or App::make() at the moment of use rather than once at construction time.',
                filePath: $parsed->path(),
                line: $hit['line'],
                symbol: "{$hit['className']}::\${$hit['propertyName']}",
            );
        }
    }
}
