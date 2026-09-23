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
