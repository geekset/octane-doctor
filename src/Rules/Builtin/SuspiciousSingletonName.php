<?php

namespace OctaneDoctor\Rules\Builtin;

use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;
use OctaneDoctor\Rules\Rule;
use OctaneDoctor\Rules\RuleExplanation;
use OctaneDoctor\Scanning\ScanContext;
use ReflectionClass;
use ReflectionException;

/**
 * Heuristic detector for singleton bindings whose class name implies
 * per-request state (current user, tenant, locale, filter, request
 * context). Under Octane these classes keep whatever state they
 * captured on the first request for the lifetime of the worker,
 * which is the most common source of cross-tenant data leaks in
 * legacy apps that adopted singleton-by-convention naming.
 *
 * Name based heuristic. Lower severity than request-in-singleton
 * because the signal is weaker; the goal is to point a developer at
 * suspicious bindings to review, not to flag a definite bug.
 */
class SuspiciousSingletonName implements Rule
{
    /**
     * Case-insensitive substrings that suggest request-scoped state.
     *
     * @var array<int, string>
     */
    protected const SUSPICIOUS_FRAGMENTS = [
        'CurrentUser',
        'ActiveUser',
        'AuthenticatedUser',
        'UserContext',
        'TenantContext',
        'CurrentTenant',
        'ActiveTenant',
        'LocaleContext',
        'CurrentLocale',
        'RequestContext',
        'RequestState',
        'RouteContext',
        'CurrentRoute',
        'SessionContext',
        'FilterState',
        'ScopeState',
    ];

    /**
     * Vendor namespace prefixes whose bindings are trusted to manage
     * their own Octane safety.
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
        return 'suspicious-singleton-name';
    }

    public function title(): string
    {
        return 'Singleton binding name suggests request-scoped state';
    }

    public function severity(): Severity
    {
        return Severity::Medium;
    }

    public function category(): Category
    {
        return Category::SingletonSafety;
    }

    public function explanation(): RuleExplanation
    {
        return new RuleExplanation(
            whyItMatters: 'Class names that include CurrentUser, TenantContext, or RequestState almost always hold the value for "the request right now". Binding such a class as a singleton means the worker keeps the value it captured on the first request for the rest of its life.',
            remediation: 'If the class genuinely represents per-request state, switch the binding to scoped() so a fresh instance is built each request. If the class is misnamed and is actually long-lived, rename it so the next reader is not misled.',
            examples: [
                '$this->app->singleton(CurrentUser::class) // flagged',
                '$this->app->scoped(CurrentUser::class)    // safe',
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

            $matched = $this->matchedFragment($abstract);

            $concreteClass = $this->resolveConcreteClass($abstract, $binding['concrete'] ?? null);

            if ($matched === null && $concreteClass !== null) {
                $matched = $this->matchedFragment($concreteClass);
            }

            if ($matched === null) {
                continue;
            }

            yield new Finding(
                ruleId: $this->id(),
                title: $this->title(),
                severity: $this->severity(),
                category: $this->category(),
                summary: "Singleton {$abstract} is named like request-scoped state (matched '{$matched}').",
                whyItMatters: 'Class names that include CurrentUser, TenantContext, or RequestState almost always hold the value for "the request right now". Binding such a class as a singleton means it keeps the value the worker captured first and never refreshes it.',
                remediation: 'If the class genuinely represents per-request state, switch the binding to scoped() so a fresh instance is built each request. If the class is misnamed and is actually long-lived, rename it so the next reader is not misled.',
                filePath: $this->resolveFilePath($concreteClass),
                line: null,
                symbol: $concreteClass ?? $abstract,
            );
        }
    }

    protected function resolveConcreteClass(string $abstract, mixed $concrete): ?string
    {
        if (is_string($concrete) && $concrete !== '' && class_exists($concrete)) {
            return $concrete;
        }

        if (class_exists($abstract)) {
            return $abstract;
        }

        return null;
    }

    protected function matchedFragment(string $candidate): ?string
    {
        foreach (self::SUSPICIOUS_FRAGMENTS as $fragment) {
            if (stripos($candidate, $fragment) !== false) {
                return $fragment;
            }
        }

        return null;
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

    protected function resolveFilePath(?string $concreteClass): ?string
    {
        if ($concreteClass === null) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($concreteClass);
        } catch (ReflectionException) {
            return null;
        }

        $file = $reflection->getFileName();

        return $file !== false ? $file : null;
    }
}
