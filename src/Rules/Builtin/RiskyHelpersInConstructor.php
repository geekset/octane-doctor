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
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;

/**
 * Flags calls to request(), auth(), session(), and the matching
 * facades inside a class constructor. The constructor of a long-lived
 * service runs once when the container resolves the binding; any
 * request, user, or session pulled there freezes to the request that
 * happened to trigger that resolution and stays stale for every later
 * request the same worker handles.
 *
 * The rule does not try to prove the class is singleton-bound. The
 * pattern is risky even for transient services because callers tend
 * to cache the resolved instance, so we report at the declaration
 * site and let the developer decide.
 */
class RiskyHelpersInConstructor implements Rule
{
    /**
     * Global helper functions that pull request-scoped state.
     *
     * @var array<int, string>
     */
    protected const RISKY_FUNCTIONS = [
        'request',
        'auth',
        'session',
    ];

    /**
     * Traits that indicate the class is constructed per dispatch or
     * per request rather than once at container resolution. Capturing
     * request-scoped state in such a constructor is the documented
     * Laravel idiom (e.g. an Event recording the causer), not an
     * Octane leak.
     *
     * @var array<int, string>
     */
    protected const PER_DISPATCH_TRAITS = [
        'Illuminate\\Foundation\\Events\\Dispatchable',
        'Illuminate\\Bus\\Queueable',
    ];

    /**
     * Base classes whose subclasses are constructed per dispatch or
     * per request. Direct parent only; deeper hierarchies fall through
     * and are still inspected.
     *
     * @var array<int, string>
     */
    protected const PER_DISPATCH_PARENTS = [
        'Illuminate\\Mail\\Mailable',
        'Illuminate\\Notifications\\Notification',
        'Illuminate\\Foundation\\Http\\FormRequest',
    ];

    /**
     * Facade-style static calls that read request-scoped state. Keyed
     * by the class basename so it matches imported aliases as well as
     * global facade aliases.
     *
     * @var array<string, array<int, string>>
     */
    protected const RISKY_STATIC_CALLS = [
        'Request' => [
            'all', 'input', 'get', 'post', 'query', 'header', 'headers',
            'cookie', 'session', 'user', 'route', 'bearerToken', 'ip',
        ],
        'Auth' => ['user', 'id', 'check', 'guest', 'guard', 'viaRemember'],
        'Session' => ['get', 'all', 'has', 'pull', 'token'],
        'Illuminate\\Support\\Facades\\Request' => [
            'all', 'input', 'get', 'post', 'query', 'header', 'headers',
            'cookie', 'session', 'user', 'route', 'bearerToken', 'ip',
        ],
        'Illuminate\\Support\\Facades\\Auth' => ['user', 'id', 'check', 'guest', 'guard', 'viaRemember'],
        'Illuminate\\Support\\Facades\\Session' => ['get', 'all', 'has', 'pull', 'token'],
    ];

    public function __construct(
        protected FileWalker $walker,
    ) {}

    public function id(): string
    {
        return 'risky-helpers-in-constructor';
    }

    public function title(): string
    {
        return 'Request-scoped helper called in constructor';
    }

    public function severity(): Severity
    {
        return Severity::Medium;
    }

    public function category(): Category
    {
        return Category::RequestState;
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
        $functions = self::RISKY_FUNCTIONS;
        $staticCalls = self::RISKY_STATIC_CALLS;
        $perDispatchTraits = self::PER_DISPATCH_TRAITS;
        $perDispatchParents = self::PER_DISPATCH_PARENTS;

        $visitor = new class($functions, $staticCalls, $perDispatchTraits, $perDispatchParents) extends NodeVisitorAbstract
        {
            /** @var array<int, array{className: string, call: string, line: int}> */
            public array $hits = [];

            protected ?string $currentClass = null;

            protected bool $insideConstructor = false;

            protected bool $currentClassSkipped = false;

            /** @var array<int, array{className: string, call: string, line: int}> */
            protected array $pendingHits = [];

            /**
             * @param  array<int, string>  $riskyFunctions
             * @param  array<string, array<int, string>>  $riskyStaticCalls
             * @param  array<int, string>  $perDispatchTraits
             * @param  array<int, string>  $perDispatchParents
             */
            public function __construct(
                protected array $riskyFunctions,
                protected array $riskyStaticCalls,
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
                }

                if (! $this->insideConstructor) {
                    return null;
                }

                if ($node instanceof FuncCall && $node->name instanceof Node\Name) {
                    $functionName = $node->name->toLowerString();

                    if (in_array($functionName, $this->riskyFunctions, true)) {
                        $this->pendingHits[] = [
                            'className' => $this->currentClass ?? '<anonymous>',
                            'call' => $functionName.'()',
                            'line' => $node->getStartLine(),
                        ];
                    }
                }

                if ($node instanceof StaticCall && $node->class instanceof Node\Name && $node->name instanceof Node\Identifier) {
                    $class = $node->class->toString();
                    $method = $node->name->toString();
                    $allowed = $this->riskyStaticCalls[$class] ?? null;

                    if ($allowed !== null && in_array($method, $allowed, true)) {
                        $this->pendingHits[] = [
                            'className' => $this->currentClass ?? '<anonymous>',
                            'call' => "{$class}::{$method}()",
                            'line' => $node->getStartLine(),
                        ];
                    }
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
        };

        $traverser = new NodeTraverser(new NameResolver, $visitor);
        $traverser->traverse($parsed->ast);

        foreach ($visitor->hits as $hit) {
            yield new Finding(
                ruleId: $this->id(),
                title: $this->title(),
                severity: $this->severity(),
                category: $this->category(),
                summary: "Class {$hit['className']} calls {$hit['call']} from its constructor.",
                whyItMatters: 'A constructor runs when the container resolves the service, not on every request. Values pulled from request(), auth(), or session() are captured at that moment and become stale for every subsequent request the same Octane worker handles.',
                remediation: 'Move the call to a method that runs per request, accept the request-scoped value as a method parameter, or resolve it through a scoped() container binding.',
                filePath: $parsed->path(),
                line: $hit['line'],
                symbol: "{$hit['className']}::__construct",
            );
        }
    }
}
