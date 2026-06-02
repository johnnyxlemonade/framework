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

## Removed `service()` helper

Resolving the validator through `service('validator')` has been removed. Use controller services or constructor DI.

```php
// Controller:
$validator = $this->validator();

// Service:
public function __construct(private FormValidation $validator) {}
```

## Localization

The validation module is integrated with localization, so validation messages can be translated through the configured translator.
