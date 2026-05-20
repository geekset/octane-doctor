<?php

namespace OctaneDoctor\Rules\Builtin;

use Illuminate\Contracts\Container\Container;
use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;
use OctaneDoctor\Rules\Rule;
use OctaneDoctor\Rules\RuleExplanation;
use OctaneDoctor\Scanning\ScanContext;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

/**
 * Flags singleton-bound services whose constructor accepts a request
 * scoped framework object (Request, Guard, Route, Session). Under
 * Octane the singleton instance is built once and reused across
 * requests, so a captured Request keeps pointing at the request that
 * triggered the binding.
 *
 * Walks the application's container bindings instead of parsing
 * source so it catches both classes you registered yourself and any
 * third-party packages that registered singletons depending on
 * request scoped objects.
 */
class RequestInSingleton implements Rule
{
    /**
     * Fully qualified type names whose instances are request scoped.
     *
     * Deliberately narrow. AuthManager and the auth Factory contract
     * are the long-lived auth managers, not the per-request guard.
     * Octane flushes auth state in prepareApplicationForNextOperation,
     * so a singleton accepting AuthManager is not the same risk as one
     * accepting the resolved Guard, and flagging it produces noisy
     * HIGH findings on packages like spatie/laravel-activitylog.
     * The Router is the manager that owns the matched Route, so it is
     * skipped for the same reason.
     *
     * @var array<int, string>
     */
    protected const REQUEST_SCOPED_TYPES = [
        'Illuminate\\Http\\Request',
        'Illuminate\\Contracts\\Auth\\Guard',
        'Illuminate\\Contracts\\Auth\\StatefulGuard',
        'Illuminate\\Routing\\Route',
        'Illuminate\\Session\\Store',
        'Illuminate\\Contracts\\Session\\Session',
    ];

    /**
     * Vendor namespace prefixes whose singleton bindings we trust to
     * manage their own Octane safety. Without this, every Laravel
     * core binding generates noise.
     *
     * @var array<int, string>
     */
    protected const TRUSTED_ABSTRACT_PREFIXES = [
        'Illuminate\\',
        'Symfony\\',
        'Psr\\',
        'Laravel\\',
        'Carbon\\',
    ];

    public function id(): string
    {
        return 'request-in-singleton';
    }

    public function title(): string
    {
        return 'Singleton constructor injects a request-scoped dependency';
    }

    public function severity(): Severity
    {
        return Severity::High;
    }

    public function category(): Category
    {
        return Category::SingletonSafety;
    }

    public function explanation(): RuleExplanation
    {
        return new RuleExplanation(
            whyItMatters: 'A singleton is built once per worker and reused across requests under Octane. A constructor that accepts the current Request, auth Guard, Route, or Session freezes that instance to the request that triggered the first resolution. Every later request the worker handles sees the same stale dependency.',
            remediation: 'Either move the binding to scoped() so it is rebuilt per request, or stop injecting the request scoped dependency through the constructor and resolve it inside the method that uses it.',
            examples: [
                '$this->app->singleton(Foo::class) // Foo::__construct(Request $r) flagged',
                '$this->app->scoped(Foo::class) // safe under Octane',
            ],
        );
    }

    public function run(ScanContext $context): iterable
    {
        $container = $context->app;

        foreach ($container->getBindings() as $abstract => $binding) {
            if (! is_string($abstract) || ! ($binding['shared'] ?? false)) {
                continue;
            }

            if ($this->isTrusted($abstract)) {
                continue;
            }

            $concreteClass = $this->resolveConcreteClass($abstract, $binding['concrete'] ?? null, $container);

            if ($concreteClass === null) {
                continue;
            }

            yield from $this->inspectClass($abstract, $concreteClass);
        }
    }

    /**
     * @return iterable<Finding>
     */
    protected function inspectClass(string $abstract, string $concreteClass): iterable
    {
        try {
            $reflection = new ReflectionClass($concreteClass);
        } catch (ReflectionException) {
            return;
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return;
        }

        foreach ($constructor->getParameters() as $param) {
            $matchedType = $this->matchRequestScoped($param);

            if ($matchedType === null) {
                continue;
            }

            yield new Finding(
                ruleId: $this->id(),
                title: $this->title(),
                severity: $this->severity(),
                category: $this->category(),
                summary: "Singleton {$abstract} resolves {$concreteClass} whose constructor accepts {$matchedType}.",
                whyItMatters: 'A singleton is built once per worker and reused across requests under Octane. A constructor that accepts the current Request, auth Guard, Route, or Session freezes that instance to the request that triggered the first resolution.',
                remediation: 'Either move the binding to scoped() so it is rebuilt per request, or stop injecting the request-scoped dependency through the constructor and resolve it inside the method that uses it.',
                filePath: $reflection->getFileName() !== false ? $reflection->getFileName() : null,
                line: $constructor->getStartLine() > 0 ? $constructor->getStartLine() : null,
                symbol: "{$concreteClass}::__construct",
            );
        }
    }

    protected function resolveConcreteClass(string $abstract, mixed $concrete, Container $container): ?string
    {
        if (is_string($concrete) && $concrete !== '' && class_exists($concrete)) {
            return $concrete;
        }

        if (class_exists($abstract)) {
            return $abstract;
        }

        return null;
    }

    protected function matchRequestScoped(ReflectionParameter $param): ?string
    {
        $type = $param->getType();

        if ($type === null) {
            return null;
        }

        foreach ($this->flattenTypes($type) as $name) {
            if (in_array($name, self::REQUEST_SCOPED_TYPES, true)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function flattenTypes(mixed $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type->getName()];
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            $names = [];

            foreach ($type->getTypes() as $inner) {
                foreach ($this->flattenTypes($inner) as $name) {
                    $names[] = $name;
                }
            }

            return $names;
        }

        return [];
    }

    protected function isTrusted(string $abstract): bool
    {
        foreach (self::TRUSTED_ABSTRACT_PREFIXES as $prefix) {
            if (str_starts_with($abstract, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
