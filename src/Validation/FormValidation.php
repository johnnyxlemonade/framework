<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation;

use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Validation\Rule\ValidationRuleFailureDetailsInterface;
use RuntimeException;

final class FormValidation
{
    private ValidationSchema $currentSchema;
    private ?string $locale = null;

    /** @var array<string, string> */
    private array $defaultMessages = [
        ValidationRuleName::REQUIRED => '{field} is required.',
        ValidationRuleName::REQUIRED_IF => '{field} is required.',
        ValidationRuleName::REQUIRED_WITH => '{field} is required.',
        ValidationRuleName::REQUIRED_WITHOUT => '{field} is required.',
        ValidationRuleName::ISSET => '{field} is required.',
        ValidationRuleName::SKIP_IF => '{field} is invalid.',
        ValidationRuleName::SKIP_UNLESS => '{field} is invalid.',
        ValidationRuleName::EMAIL => '{field} must be a valid email.',
        ValidationRuleName::EMAILS => '{field} contains an invalid email.',
        ValidationRuleName::MIN_LENGTH => '{field} must be at least {param} characters.',
        ValidationRuleName::MAX_LENGTH => '{field} can be at most {param} characters.',
        ValidationRuleName::EXACT_LENGTH => '{field} must be exactly {param} characters.',
        ValidationRuleName::NUMERIC => '{field} must be numeric.',
        ValidationRuleName::INTEGER => '{field} must be an integer.',
        ValidationRuleName::DECIMAL => '{field} must be a decimal number.',
        ValidationRuleName::ALPHA => '{field} can contain only letters.',
        ValidationRuleName::ALPHA_NUMERIC => '{field} can contain only letters and numbers.',
        ValidationRuleName::ALPHA_NUMERIC_SPACES => '{field} can contain only letters, numbers and spaces.',
        ValidationRuleName::ALPHA_DASH => '{field} can contain only letters, numbers, underscore and dash.',
        ValidationRuleName::ALPHA_NUMERIC_DASH => '{field} can contain only letters, numbers, underscore and dash.',
        ValidationRuleName::REGEX => '{field} has invalid format.',
        ValidationRuleName::MATCHES => '{field} must match {param}.',
        ValidationRuleName::DIFFERS => '{field} must differ from {param}.',
        ValidationRuleName::IN_LIST => '{field} must be one of: {param}.',
        ValidationRuleName::IS_NATURAL => '{field} must be a natural number.',
        ValidationRuleName::IS_NATURAL_NO_ZERO => '{field} must be a natural number greater than zero.',
        ValidationRuleName::VALID_BASE64 => '{field} must be a valid base64 string.',
        ValidationRuleName::VALID_UUID => '{field} must be a valid UUID.',
        ValidationRuleName::IS_UNIQUE => '{field} must be unique.',
        ValidationRuleName::IS_UNIQUE_EXCEPT => '{field} must be unique.',
        ValidationRuleName::GREATER_THAN => '{field} must be greater than {param}.',
        ValidationRuleName::GREATER_THAN_EQUAL_TO => '{field} must be greater than or equal to {param}.',
        ValidationRuleName::LESS_THAN => '{field} must be less than {param}.',
        ValidationRuleName::LESS_THAN_EQUAL_TO => '{field} must be less than or equal to {param}.',
        ValidationRuleName::URL => '{field} must be a valid URL.',
        ValidationRuleName::YOUTUBE_URL => '{field} must be a valid YouTube URL.',
        ValidationRuleName::SLIDESLIVE_URL => '{field} must be a valid SlidesLive URL.',
        ValidationRuleName::IP => '{field} must be a valid IP address.',
        ValidationRuleName::ICO => '{field} must be a valid ICO.',
        ValidationRuleName::ICO_ACTIVE => '{field} must be an active ICO.',
        ValidationRuleName::DIC => '{field} must be a valid VAT ID.',
        ValidationRuleName::DIC_ACTIVE => '{field} must be an active VAT ID.',
        ValidationRuleName::PASSWORD => '{field} has an invalid password format.',
        ValidationRuleName::ROW_ID => '{field} has an invalid ID format.',
        ValidationRuleName::ROW_COLUMN => '{field} has an invalid column format.',
        ValidationRuleName::HEX_COLOR => '{field} must be a valid hex color.',
        ValidationRuleName::TWO_DATES => '{field} must contain two valid dates.',
        ValidationRuleName::DATE => '{field} must be a valid date.',
        ValidationRuleName::HOUR => '{field} must be a valid hour.',
        ValidationRuleName::DECIMAL_NATURAL => '{field} must be a valid decimal number.',
        ValidationRuleName::MONEY => '{field} must be a valid money amount.',
        ValidationRuleName::LATITUDE => '{field} must be a valid latitude.',
        ValidationRuleName::LONGITUDE => '{field} must be a valid longitude.',
        ValidationRuleName::PHONE => '{field} must be a valid phone number.',
        ValidationRuleName::PHONE_HEAVY => '{field} must be a valid local phone number.',
        ValidationRuleName::PHONE_NUMBER => '{field} must be a valid phone number.',
        ValidationRuleName::STREET_ADDRESS => '{field} must be a valid street address.',
        ValidationRuleName::STREET_ADDRESS_FULL => '{field} must be a valid full street address.',
        ValidationRuleName::POSTCODE => '{field} must be a valid postal code.',
        ValidationRuleName::ROUTE => '{field} must be a valid route.',
        ValidationRuleName::EMAIL_HEAVY => '{field} must be a valid and existing email.',
        ValidationRuleName::TEXT_NO_LINK => '{field} cannot contain links.',
        ValidationRuleName::BANK_ACCOUNT => '{field} must be a valid bank account number.',
        ValidationRuleName::RECAPTCHA => '{field} verification failed.',
        ValidationRuleName::NO_HTML => '{field} cannot contain HTML or scripts.',
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ValidationRuleResolver $ruleResolver,
    ) {
        $this->currentSchema = ValidationSchema::create();
    }

    public function field(string $name, ?string $label = null): ValidationFieldBuilder
    {
        return $this->currentSchema->field($name, $label)->attachValidator($this);
    }

    public function schema(): ValidationSchema
    {
        return ValidationSchema::create();
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale !== null && trim($locale) !== '' ? trim($locale) : null;

        return $this;
    }

    public function reset(): self
    {
        $this->currentSchema = ValidationSchema::create();

        return $this;
    }

    /**
     * Convenience helper for the default Google reCAPTCHA field. The primary API is field()->recaptcha().
     */
    public function addGoogleRecaptcha(
        string $message = 'Please confirm reCAPTCHA.',
        ?string $secret = null,
    ): void {
        $this->field('g-recaptcha-response', 'Captcha')
            ->required($message)
            ->recaptcha($secret, $message);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function validate(array $data, ?ValidationSchema $schema = null): ValidationResult
    {
        $schema ??= $this->currentSchema;
        $this->currentSchema = ValidationSchema::create();

        return $this->execute($data, $schema);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function execute(array $payload, ValidationSchema $schema): ValidationResult
    {
        $errors = [];
        $failedRules = [];
        $validated = [];

        foreach ($schema->fields() as $field) {
            $fieldName = $field->name();
            $value = $payload[$fieldName] ?? null;
            $fieldMessages = $this->fieldMessages($field);

            foreach ($this->prepareRules($field->rules()) as $rule) {
                $param = $rule->param();

                if ($this->isSkipRuleMatch($rule, $param, $payload)) {
                    break;
                }

                if (in_array($rule->name(), [ValidationRuleName::SKIP_IF, ValidationRuleName::SKIP_UNLESS], true)) {
                    continue;
                }

                if ($this->shouldSkip($rule, $value)) {
                    continue;
                }

                if ($this->applyRule($rule, $value, $param, $payload)) {
                    continue;
                }

                $ruleName = $rule->name();
                $failedRules[$fieldName][] = $ruleName;
                $message = $fieldMessages[$ruleName]
                    ?? $this->ruleFailureMessage($ruleName)
                    ?? $this->translatedRuleMessage($ruleName)
                    ?? $this->defaultMessages[$ruleName]
                    ?? '{field} is invalid.';

                $errors[$fieldName] = $this->formatMessage($message, $field->label(), $param);
                break;
            }

            $validated[$fieldName] = $value;
        }

        return new ValidationResult($errors === [], $errors, $validated, $failedRules, $payload);
    }

    /** @return array<string, string> */
    private function fieldMessages(ValidationFieldDefinition $field): array
    {
        $messages = [];

        foreach ($field->rules() as $rule) {
            $message = $rule->message();
            if ($message !== null) {
                $messages[$rule->name()] = $message;
            }
        }

        return $messages;
    }

    /**
     * @param list<ValidationRuleDefinition> $rules
     * @return list<ValidationRuleDefinition>
     */
    private function prepareRules(array $rules): array
    {
        $required = [];
        $plain = [];

        foreach ($rules as $rule) {
            if (in_array($rule->name(), [
                ValidationRuleName::REQUIRED,
                ValidationRuleName::REQUIRED_IF,
                ValidationRuleName::REQUIRED_WITH,
                ValidationRuleName::REQUIRED_WITHOUT,
            ], true)) {
                $required[] = $rule;
                continue;
            }

            $plain[] = $rule;
        }

        return [...$required, ...$plain];
    }

    private function shouldSkip(ValidationRuleDefinition $rule, mixed $value): bool
    {
        if (in_array($rule->name(), [
            ValidationRuleName::REQUIRED,
            ValidationRuleName::REQUIRED_IF,
            ValidationRuleName::REQUIRED_WITH,
            ValidationRuleName::REQUIRED_WITHOUT,
            ValidationRuleName::ISSET,
            ValidationRuleName::MATCHES,
            ValidationRuleName::DIFFERS,
            ValidationRuleName::RECAPTCHA,
        ], true)) {
            return false;
        }

        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function isSkipRuleMatch(ValidationRuleDefinition $rule, ?string $param, array $data): bool
    {
        $name = $rule->name();
        if (!in_array($name, [ValidationRuleName::SKIP_IF, ValidationRuleName::SKIP_UNLESS], true)) {
            return false;
        }

        [$otherField, $expected] = array_pad(explode(',', (string) $param, 2), 2, '');
        $otherField = trim($otherField);
        $expected = trim($expected);

        if ($otherField === '') {
            return false;
        }

        $actualValue = $data[$otherField] ?? '';
        $actual = is_scalar($actualValue) ? (string) $actualValue : '';

        return $name === ValidationRuleName::SKIP_IF ? $actual === $expected : $actual !== $expected;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyRule(ValidationRuleDefinition $rule, mixed $value, ?string $param, array $data): bool
    {
        $ruleObject = $this->ruleResolver->resolve($rule->name());
        if ($ruleObject === null) {
            throw new RuntimeException(sprintf('Validation rule "%s" is not registered.', $rule->name()));
        }

        return $ruleObject->validate($value, $param, $data);
    }

    private function ruleFailureMessage(string $ruleName): ?string
    {
        $rule = $this->ruleResolver->resolve($ruleName);

        if (!$rule instanceof ValidationRuleFailureDetailsInterface) {
            return null;
        }

        $translateKey = $rule->pullFailureTranslationKey();

        if (is_string($translateKey) && $translateKey !== '') {
            $line = $this->translatedRuleFailureMessage($ruleName, $translateKey);

            if ($line !== null) {
                return $line;
            }
        }

        $message = $rule->pullFailureMessage();

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return null;
    }

    private function translatedRuleFailureMessage(string $ruleName, string $translateKey): ?string
    {
        $candidates = str_starts_with($translateKey, 'validation.')
            ? [$translateKey]
            : [
                'validation.' . $ruleName . '.' . $translateKey,
                'validation.' . $translateKey,
            ];

        foreach ($candidates as $key) {
            $line = $this->translator->get($key, [], $this->locale);

            if ($line !== $key) {
                return $line;
            }
        }

        return null;
    }

    private function translatedRuleMessage(string $ruleName): ?string
    {
        $line = $this->translator->get('validation.' . $ruleName, [], $this->locale);

        if ($line === 'validation.' . $ruleName) {
            return null;
        }

        return $line;
    }

    private function formatMessage(string $template, string $label, ?string $param): string
    {
        return str_replace(['{field}', '{param}'], [$label, (string) $param], $template);
    }
}
