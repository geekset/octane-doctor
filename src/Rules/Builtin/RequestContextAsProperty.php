<?php

namespace OctaneDoctor\Rules\Builtin;

use OctaneDoctor\Ast\FileWalker;
use OctaneDoctor\Ast\ParsedFile;
use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;
use OctaneDoctor\Rules\Rule;
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
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;

/**
 * Flags classes that hold a request-scoped framework object as an
 * instance property: Request, the auth guard, the current Route, or
 * the Session. Under Octane the instance survives the request that
 * stored it, so the property keeps pointing at stale state for every
 * subsequent request the same worker handles.
 *
 * Detects both regular typed properties and constructor-promoted
 * properties. The per-dispatch and per-request safe-list mirrors the
 * risky-helpers-in-constructor rule so events, jobs, mailables,
 * notifications, form requests, and controllers are skipped.
 */
class RequestContextAsProperty implements Rule
{
    /**
     * Type names whose instances are request scoped. Both the short
     * basename and the fully qualified FQCN are listed because user
     * code uses both forms depending on imports.
     *
     * @var array<int, string>
     */
    protected const REQUEST_SCOPED_TYPES = [
        'Illuminate\\Http\\Request',
        'Illuminate\\Auth\\AuthManager',
        'Illuminate\\Contracts\\Auth\\Guard',
        'Illuminate\\Contracts\\Auth\\StatefulGuard',
        'Illuminate\\Contracts\\Auth\\Factory',
        'Illuminate\\Routing\\Route',
        'Illuminate\\Routing\\Router',
        'Illuminate\\Session\\Store',
        'Illuminate\\Contracts\\Session\\Session',
    ];

    /**
     * Traits that signal the class is constructed per dispatch or
     * per request rather than stored as a singleton.
     *
     * @var array<int, string>
     */
    protected const PER_DISPATCH_TRAITS = [
        'Illuminate\\Foundation\\Events\\Dispatchable',
        'Illuminate\\Bus\\Queueable',
    ];

    /**
     * Base classes whose instances are constructed per dispatch or
     * per request.
     *
     * @var array<int, string>
     */
    protected const PER_DISPATCH_PARENTS = [
        'Illuminate\\Mail\\Mailable',
        'Illuminate\\Notifications\\Notification',
        'Illuminate\\Foundation\\Http\\FormRequest',
        'Illuminate\\Routing\\Controller',
    ];

    public function __construct(
        protected FileWalker $walker,
    ) {}

    public function id(): string
    {
        return 'request-context-as-property';
    }

    public function title(): string
    {
        return 'Request-scoped object stored as a class property';
    }

    public function severity(): Severity
    {
        return Severity::High;
    }

    public function category(): Category
    {
        return Category::RequestState;
    }

    public function explanation(): RuleExplanation
    {
        return new RuleExplanation(
            whyItMatters: 'Under Octane the same object instance is reused across requests. A property holding the current Request, auth guard, route, or session freezes to the request that constructed the class and stays stale for every later request the worker handles.',
            remediation: 'Receive the request scoped object as a method parameter, resolve it on demand through the container, or move the binding to scoped() so a fresh instance is built per request.',
            examples: [
                'class ReportService { protected Request $request; } // flagged',
                'class ReportService { public function generate(Request $request) {} } // safe',
            ],
        );
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
        $requestScopedTypes = self::REQUEST_SCOPED_TYPES;
        $perDispatchTraits = self::PER_DISPATCH_TRAITS;
        $perDispatchParents = self::PER_DISPATCH_PARENTS;

        $visitor = new class($requestScopedTypes, $perDispatchTraits, $perDispatchParents) extends NodeVisitorAbstract
        {
            /** @var array<int, array{className: string, propertyName: string, typeName: string, line: int}> */
            public array $hits = [];

            protected ?string $currentClass = null;

            protected bool $currentClassSkipped = false;

            protected bool $insideConstructor = false;

            /** @var array<int, array{className: string, propertyName: string, typeName: string, line: int}> */
            protected array $pendingHits = [];

            /**
             * @param  array<int, string>  $requestScopedTypes
             * @param  array<int, string>  $perDispatchTraits
             * @param  array<int, string>  $perDispatchParents
             */
            public function __construct(
                protected array $requestScopedTypes,
                protected array $perDispatchTraits,
                protected array $perDispatchParents,
            ) {}

            public function enterNode(Node $node): null
            {
                if ($node instanceof Class_) {
                    $this->currentClass = $node->namespacedName?->toString() ?? $node->name?->toString();
                    $this->pendingHits = [];
                    $this->currentClassSkipped = $this->parentIsPerDispatch($node);
                }

                if ($node instanceof TraitUse && $this->currentClass !== null) {
                    foreach ($node->traits as $trait) {
                        if (in_array($trait->toString(), $this->perDispatchTraits, true)) {
                            $this->currentClassSkipped = true;
                        }
                    }
                }

                if ($node instanceof ClassMethod && $node->name->toLowerString() === '__construct') {
                    $this->insideConstructor = true;

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
                if ($node instanceof ClassMethod && $node->name->toLowerString() === '__construct') {
                    $this->insideConstructor = false;
                }

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

            protected function parentIsPerDispatch(Class_ $node): bool
            {
                $parent = $node->extends?->toString();

                return $parent !== null && in_array($parent, $this->perDispatchParents, true);
            }

            protected function checkRegularProperty(Property $node): void
            {
                $typeName = $this->resolveTypeName($node->type);

                if ($typeName === null || ! $this->matchesRequestScoped($typeName)) {
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

                if ($typeName === null || ! $this->matchesRequestScoped($typeName)) {
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

                        if ($resolved !== null && $this->matchesRequestScoped($resolved)) {
                            return $resolved;
                        }
                    }
                }

                return null;
            }

            protected function matchesRequestScoped(string $typeName): bool
            {
                return in_array($typeName, $this->requestScopedTypes, true);
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
                summary: "Class {$hit['className']} stores {$hit['typeName']} on property \${$hit['propertyName']}.",
                whyItMatters: 'Under Octane the same object instance is reused across requests. A property holding the current Request, auth guard, route, or session freezes to the request that constructed the class and stays stale for every later request.',
                remediation: 'Receive the request-scoped object as a method parameter, resolve it on demand through the container, or move the binding to scoped() so a fresh instance is built per request.',
                filePath: $parsed->path(),
                line: $hit['line'],
                symbol: "{$hit['className']}::\${$hit['propertyName']}",
            );
        }
    }
}
