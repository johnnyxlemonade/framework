# Validation

Validation is built around a form validator and a rule registry.

The validator is registered as both `FormValidation::class` and the `validator` string alias.

## Basic usage

```php
$validator = $this->validator();

$result = $validator->validate($this->post(), [
    'email' => ['required', 'email'],
    'name' => ['required'],
]);

if (!$result->isValid()) {
    return $this->json([
        'errors' => $result->errors(),
    ], 422);
}
```

The exact rule set depends on the registered rule registry and application configuration.

## Service usage

In controllers use `$this->validator()` for the framework validator. In services use constructor injection.

```php
public function __construct(private FormValidation $validator) {}
```

Custom validation rules are resolved by `ValidationRuleResolver` through the container. Register the rule name in `RuleRegistry` and give the rule explicit constructor dependencies. DB-backed and HTTP-backed rules must not call global helpers or service locators.

## Localization

The validation module is integrated with localization, so validation messages can be translated through the configured translator.
