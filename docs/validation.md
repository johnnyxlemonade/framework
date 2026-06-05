# Validation

Validation is built around `Lemonade\Framework\Validation\FormValidation` and typed rule definitions.
Application code defines rules through the fluent `ValidationFieldBuilder`, explicit `ValidationSchema` objects, or low-level `ValidationRule` factories that create immutable `ValidationRuleDefinition` objects.

Rules are resolved at runtime through `RuleRegistry` and `ValidationRuleResolver`. `ValidationRuleName` is only a list of built-in framework rule names for translations, registry lookup and message targeting. It is not a whitelist of all valid rules. Custom rules registered through `RuleRegistry::addRule()` are valid too.

The validator is registered in the container as `FormValidation::class` and as the `validator` alias.

## Basic Controller Usage

Use `$this->validator()` in controllers and define fields with typed fluent methods.

```php
$validator = $this->validator();

$result = $validator
    ->field('email', 'E-mail')
        ->required()
        ->email()
        ->maxLength(150)
    ->field('password', 'Password')
        ->required()
        ->minLength(8)
    ->validate($this->post());

if (!$result->isValid()) {
    return $this->json([
        'errors' => $result->errors(),
    ], 422);
}

$data = $result->validated();
```

`FormValidation::field()` is the convenient path for one-off request validation. Calling `validate()` returns a `ValidationResult` and resets the validator's current schema.

## Registration Form Example

A more realistic registration schema can combine required fields, conditional rules, cross-field rules and custom messages.

```php
$result = $this->validator()
    ->field('email', 'E-mail')
        ->required('E-mail je povinný.')
        ->email('Zadejte platný e-mail.')
        ->maxLength(150)
    ->field('password', 'Heslo')
        ->required()
        ->password(requireSpecialCharacter: true)
        ->minLength(8)
    ->field('password_confirm', 'Potvrzení hesla')
        ->required()
        ->matches('password', 'Hesla se musí shodovat.')
    ->field('account_type', 'Typ účtu')
        ->required()
        ->inList(['person', 'company'])
    ->field('company_id', 'IČO')
        ->requiredIf('account_type', 'company')
        ->ico()
    ->field('phone', 'Telefon')
        ->phoneNumber('country')
    ->field('terms', 'Souhlas s podmínkami')
        ->required('Musíte souhlasit s podmínkami.')
    ->validate($this->post());
```

## Explicit Schema Usage

Use `ValidationSchema::create()` when a schema is created outside a controller, reused by a service, or composed dynamically.

```php
use Lemonade\Framework\Validation\ValidationSchema;

$schema = ValidationSchema::create();

$schema
    ->field('email', 'E-mail')
        ->required()
        ->email()
        ->maxLength(150)
    ->field('name', 'Jméno')
        ->required()
        ->maxLength(100);

$result = $validator->validate($payload, $schema);
```

The schema builder returns a `ValidationFieldBuilder`. You can continue with another `field()` directly, or call `end()` when you need the schema object explicitly.

```php
$schema = ValidationSchema::create()
    ->field('email', 'E-mail')
        ->required()
        ->email()
        ->end();
```

## Low-Level Typed Rule DSL

`ValidationRule` is a stateless factory for built-in `ValidationRuleDefinition` objects. It is not a runtime validator, registry or resolver. It does not validate submitted data and it does not decide which rule names are allowed.

```php
use Lemonade\Framework\Validation\ValidationRule;

$result = $validator
    ->field('email', 'E-mail')
        ->addRule(ValidationRule::required())
        ->addRule(ValidationRule::email())
        ->addRule(ValidationRule::maxLength(150))
    ->validate($payload);
```

Prefer shortcut methods such as `->email()` and `->maxLength(150)` for regular application code. Use `addRule(ValidationRule::...)` when you want to pass rule definitions around explicitly.

## Custom Rules

Custom rules implement `ValidationRuleInterface`, are registered in `RuleRegistry`, and are used through `custom()` or an explicit `ValidationRuleDefinition`.

```php
use Lemonade\Framework\Validation\Rule\ValidationRuleInterface;

final class SlugRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        return is_string($value)
            && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1;
    }
}
```

Register the rule name in `RuleRegistry`.

```php
$registry->addRule('slug', SlugRule::class);
```

Use the custom rule from the fluent builder.

```php
$result = $validator
    ->field('slug', 'URL slug')
        ->required()
        ->custom('slug', message: 'Slug může obsahovat jen malá písmena, čísla a pomlčky.')
    ->validate($payload);
```

`ValidationRuleName::builtIn()` lists only framework-provided names. It is not a whitelist and does not block custom rule names.

## Custom Rules With Dependencies

Rules with database, HTTP or other service dependencies should receive them through the constructor. The resolver obtains rule instances through the container, so rule classes should not use global helpers or service locators.

```php
use Lemonade\Framework\Validation\Rule\ValidationRuleInterface;

final class UniqueSlugRule implements ValidationRuleInterface
{
    public function __construct(
        private readonly ArticleRepository $articles,
    ) {}

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        if (!is_string($value) || trim($value) === '') {
            return true;
        }

        return !$this->articles->existsBySlug($value);
    }
}
```

```php
$registry->addRule('unique_slug', UniqueSlugRule::class);
```

## Conditional Validation

Conditional rules let one field depend on another field's value or presence.

```php
$result = $validator
    ->field('type', 'Typ')
        ->required()
        ->inList(['person', 'company'])
    ->field('company_name', 'Název firmy')
        ->requiredIf('type', 'company')
        ->maxLength(200)
    ->field('vat_id', 'DIČ')
        ->skipUnless('type', 'company')
        ->dic()
    ->validate($payload);
```

Available conditional helpers include:

- `requiredIf(string $field, string|int|float|bool $expected)`
- `requiredWith(string ...$fields)`
- `requiredWithout(string ...$fields)`
- `skipIf(string $field, string|int|float|bool $expected)`
- `skipUnless(string $field, string|int|float|bool $expected)`

## Messages And Localization

Messages are resolved in this order:

1. Message passed directly to the rule method.
2. Failure details returned by the rule through `ValidationRuleFailureDetailsInterface`.
3. Translation key `validation.<rule>`.
4. Framework default message.
5. Fallback `{field} is invalid.`.

Prefer passing messages directly to typed methods for common cases.

```php
$result = $validator
    ->field('email', 'E-mail')
        ->required('E-mail je povinný.')
        ->email('Zadejte platný e-mail.')
    ->validate($payload);
```

For advanced cases you can target a stable rule name explicitly. This is useful for custom rules or shared message configuration.

```php
use Lemonade\Framework\Validation\ValidationRuleName;

$result = $validator
    ->field('email', 'E-mail')
        ->email()
        ->message(ValidationRuleName::EMAIL, 'Zadejte platnou e-mailovou adresu.')
    ->validate($payload);
```

`message()` is low-level API. Prefer the message argument on typed rule methods when possible.

## Result Handling

`ValidationResult` is the object returned by every validation call.

```php
if (!$result->isValid()) {
    $errors = $result->errors();
}

$data = $result->validated();
$emailError = $result->error('email');
```

The result also exposes helpers for cases where application code wants to handle one specific failed rule differently.

```php
use Lemonade\Framework\Validation\ValidationRuleName;

if ($result->failedOnlyOnRule('email', ValidationRuleName::EMAIL_HEAVY)) {
    $originalValue = $result->getValueIfFailedOnlyOnRule('email', ValidationRuleName::EMAIL_HEAVY);
}
```

`toArray()` returns a transport-friendly representation including errors, validated data and original input.

## Service Usage

Use constructor injection in services and handlers.

```php
use Lemonade\Framework\Validation\FormValidation;
use Lemonade\Framework\Validation\ValidationResult;

final class RegisterUserHandler
{
    public function __construct(
        private readonly FormValidation $validator,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(array $payload): ValidationResult
    {
        return $this->validator
            ->field('email', 'E-mail')
                ->required()
                ->email()
            ->validate($payload);
    }
}
```

`FormValidation` is stateful while building a schema. It must not be shared as a singleton for concurrent validation contexts. The framework registers it through the container as a factory-style service, and `validate()` clears the current schema after validation.

## Full Contact Form Example

```php
$result = $this->validator()
    ->field('name', 'Jméno')
        ->required()
        ->maxLength(100)
        ->noHtml()
    ->field('email', 'E-mail')
        ->required()
        ->email()
        ->maxLength(150)
    ->field('phone', 'Telefon')
        ->phoneNumber('country')
    ->field('subject', 'Předmět')
        ->required()
        ->maxLength(150)
        ->noHtml()
    ->field('message', 'Zpráva')
        ->required()
        ->minLength(10)
        ->maxLength(5000)
        ->noHtml()
    ->field('g-recaptcha-response', 'Captcha')
        ->required()
        ->recaptcha()
    ->validate($this->post());

if (!$result->isValid()) {
    return $this->json([
        'errors' => $result->errors(),
        'input' => $result->toArray()['input'],
    ], 422);
}
```

## What Is No Longer Supported

The validation refactor intentionally removed the legacy string-based public API. New application code must not use:

- `set_rules()`
- `run()`
- pipe-string rules, for example `required|valid_email|max_length[150]`
- public legacy array schemas, for example `validate($data, ['email' => ['required']])`
- bracket parameter syntax such as `max_length[150]`

Use typed fluent rules, explicit `ValidationSchema`, `ValidationRule` factories, or custom rules registered in `RuleRegistry` instead.
