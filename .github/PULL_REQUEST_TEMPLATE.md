## Summary

<!-- One paragraph: what does this PR change? -->

## Why

<!-- Problem this solves. Link the issue if there is one. -->

## What changed

<!-- Bullet list of the concrete changes. New files, new rules, behaviour shifts. -->

## Testing

<!-- Commands run and their outcome. Include the relevant Pest filter for new tests. -->

```bash
composer test
composer analyse
composer lint
```

## Checklist

- [ ] Pest tests cover the change (positive + negative cases)
- [ ] PHPStan passes at level 5
- [ ] Pint passes
- [ ] No new false positives surfaced against a sandbox app
- [ ] Documentation updated (`README.md`, `CONTRIBUTING.md`, or rule docblock) where the change is visible to users
- [ ] Breaking change? If yes, describe migration steps above

## Out of scope

<!-- What this PR deliberately does not address. Helps future readers. -->
