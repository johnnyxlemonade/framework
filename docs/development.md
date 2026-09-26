# Development Tooling

The framework repository uses PHPUnit, PHPStan and PHP CS Fixer as its standard quality checks.

```bash
composer qa
composer check
composer stan
composer stan:ci
composer cs:check
composer test
```

`composer qa` runs coding-standard checks, PHPStan, property tests and PHPUnit. `composer check`
adds Composer validation, platform checks and syntax linting.

## PHP 8.3 Style Baseline

Framework source targets PHP 8.3 while supporting PHP 8.3, 8.4 and 8.5 runtimes. Do not introduce
PHP 8.4/8.5 syntax such as property hooks or asymmetric property visibility.

For a genuinely immutable framework DTO or value object, prefer `final readonly class` with typed
promoted properties. Keep PHPDoc where native PHP cannot express the contract, including precise
array shapes, `list<T>`, `class-string<T>`, callable signatures and refinement types. Typed private
scalar class constants are allowed where their type is unambiguous. `#[Override]` is not yet adopted
systematically, so do not add it opportunistically.

## Property and Mutation Testing

Property tests use Eris and can run independently:

```bash
composer test:property
```

Manual local Infection suites cover routing, container invariants and Slugger. They require Xdebug
coverage to be enabled and are deliberately not part of `qa` or `check`.

```bash
composer test:mutation:routing
composer test:mutation:container
composer test:mutation:slugger
```

## API Documentation

Doctum API documentation is generated locally and its output is not versioned:

```bash
composer docs:api
```

The command uses the repository Doctum configuration and writes generated output under the ignored
build path. It is a manual documentation tool and is not part of `qa`, `check` or CI.
