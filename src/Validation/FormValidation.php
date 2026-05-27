<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation;

use Closure;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Validation\Rule\RuleRegistry;
use Lemonade\Framework\Validation\Rule\ValidationRuleFailureDetailsInterface;

final class FormValidation
{
    /**
     * @var array<string, array{
     *     field:string,
     *     label:string,
     *     rules:array<int, string|Closure>,
     *     errors:array<string, string>,
     *     error:string
     * }>
     */
    private array $fieldData = [];

    /**
     * @var array<string, string>
     */
    private array $errorArray = [];
    /**
     * @var array<string, array<string, bool>>
     */
    private array $failedRules = [];

    /**
     * @var array<string, string>
     */
    private array $errorMessages = [];
    private string $errorPrefix = '<p>';
    private string $errorSuffix = '</p>';

    /**
     * @var array<string, mixed>
     */
    private array $validationData = [];

    /**
     * @var array<string, mixed>
     */
    private array $validatedData = [];
    private ?string $locale = null;

    /**
     * @var array<string, string>
     */
    private array $defaultMessages = [
        'required' => '{field} is required.',
        'required_if' => '{field} is required.',
        'required_with' => '{field} is required.',
        'required_without' => '{field} is required.',
        'isset' => '{field} is required.',
        'skip_if' => '{field} is invalid.',
        'skip_unless' => '{field} is invalid.',
        'valid_email' => '{field} must be a valid email.',
        'valid_emails' => '{field} contains an invalid email.',
        'min_length' => '{field} must be at least {param} characters.',
        'max_length' => '{field} can be at most {param} characters.',
        'exact_length' => '{field} must be exactly {param} characters.',
        'numeric' => '{field} must be numeric.',
        'integer' => '{field} must be an integer.',
        'decimal' => '{field} must be a decimal number.',
        'alpha' => '{field} can contain only letters.',
        'alpha_numeric' => '{field} can contain only letters and numbers.',
        'alpha_numeric_spaces' => '{field} can contain only letters, numbers and spaces.',
        'alpha_dash' => '{field} can contain only letters, numbers, underscore and dash.',
        'alpha_numeric_dash' => '{field} can contain only letters, numbers, underscore and dash.',
        'regex_match' => '{field} has invalid format.',
        'matches' => '{field} must match {param}.',
        'differs' => '{field} must differ from {param}.',
        'in_list' => '{field} must be one of: {param}.',
        'is_natural' => '{field} must be a natural number.',
        'is_natural_no_zero' => '{field} must be a natural number greater than zero.',
        'valid_base64' => '{field} must be a valid base64 string.',
        'valid_uuid' => '{field} must be a valid UUID.',
        'is_unique' => '{field} must be unique.',
        'is_unique_except' => '{field} must be unique.',
        'greater_than' => '{field} must be greater than {param}.',
        'greater_than_equal_to' => '{field} must be greater than or equal to {param}.',
        'less_than' => '{field} must be less than {param}.',
        'less_than_equal_to' => '{field} must be less than or equal to {param}.',
        'valid_url' => '{field} must be a valid URL.',
        'valid_youtube_url' => '{field} must be a valid YouTube URL.',
        'valid_slideslive_url' => '{field} must be a valid SlidesLive URL.',
        'valid_ip' => '{field} must be a valid IP address.',
        'valid_ico' => '{field} must be a valid ICO.',
        'valid_ico_active' => '{field} must be an active ICO.',
        'valid_dic' => '{field} must be a valid VAT ID.',
        'valid_dic_active' => '{field} must be an active VAT ID.',
        'valid_password' => '{field} has an invalid password format.',
        'valid_row_id' => '{field} has an invalid ID format.',
        'valid_row_column' => '{field} has an invalid column format.',
        'valid_hex_color' => '{field} must be a valid hex color.',
        'valid_two_dates' => '{field} must contain two valid dates.',
        'valid_date' => '{field} must be a valid date.',
        'valid_hour' => '{field} must be a valid hour.',
        'valid_decimal_natural' => '{field} must be a valid decimal number.',
        'valid_money' => '{field} must be a valid money amount.',
        'valid_latitude' => '{field} must be a valid latitude.',
        'valid_longitude' => '{field} must be a valid longitude.',
        'valid_phone' => '{field} must be a valid phone number.',
        'valid_phone_heavy' => '{field} must be a valid local phone number.',
        'valid_phonenumber' => '{field} must be a valid phone number.',
        'valid_street_address' => '{field} must be a valid street address.',
        'valid_street_address_full' => '{field} must be a valid full street address.',
        'valid_postcode' => '{field} must be a valid postal code.',
        'valid_route' => '{field} must be a valid route.',
        'valid_email_heavy' => '{field} must be a valid and existing email.',
        'valid_text_no_link' => '{field} cannot contain links.',
        'valid_bank_account' => '{field} must be a valid bank account number.',
        'recaptcha' => '{field} verification failed.',
        'no_html' => '{field} cannot contain HTML or scripts.',
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ?RuleRegistry $ruleRegistry = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function set_data(array $data): self
    {
        $this->validationData = $data;
        return $this;
    }

    /**
     * @param string|array<int, array{field:mixed, label?:mixed, rules?:mixed, errors?:mixed}> $field
     * @param string|array<int, string|Closure> $rules
     * @param array<string, string> $errors
     */
    public function set_rules(string|array $field, string $label = '', string|array $rules = [], array $errors = []): self
    {
        if (is_array($field)) {
            foreach ($field as $row) {
                if (!isset($row['field'], $row['rules'])) {
                    continue;
                }

                if (!is_scalar($row['field'])) {
                    continue;
                }

                $fieldName = (string) $row['field'];
                $labelValue = $row['label'] ?? $row['field'];
                $label = is_scalar($labelValue) ? (string) $labelValue : $fieldName;
                $rulesValue = $row['rules'];
                $errorsValue = $row['errors'] ?? [];

                $this->set_rules(
                    $fieldName,
                    $label,
                    $this->normalizeRuleInput($rulesValue),
                    $this->normalizeErrorMap($errorsValue),
                );
            }
            return $this;
        }

        $normalizedRules = $this->normalizeRules($rules);
        $this->fieldData[$field] = [
            'field' => $field,
            'label' => $label !== '' ? $label : $field,
            'rules' => $normalizedRules,
            'errors' => $errors,
            'error' => '',
        ];

        return $this;
    }

    /**
     * @param string|array<string, string> $rule
     */
    public function set_message(string|array $rule, string $message = ''): self
    {
        $data = is_array($rule) ? $rule : [$rule => $message];
        $this->errorMessages = array_merge($this->errorMessages, $data);
        return $this;
    }

    public function set_error_delimiters(string $prefix = '<p>', string $suffix = '</p>'): self
    {
        $this->errorPrefix = $prefix;
        $this->errorSuffix = $suffix;
        return $this;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale !== null && trim($locale) !== '' ? trim($locale) : null;

        return $this;
    }

    public function encode_php_tags(string $str): string
    {
        return str_replace(['<?', '?>'], ['&lt;?', '?&gt;'], $str);
    }

    public function addGoogleRecaptcha(
        string $message = 'Please confirm reCAPTCHA.',
        ?string $secret = null,
    ): void {
        $rules = 'required';
        if ($secret !== null && trim($secret) !== '') {
            $rules .= '|recaptcha[' . trim($secret) . ']';
        }

        $this->set_rules(
            field: 'g-recaptcha-response',
            label: 'Captcha',
            rules: $rules,
            errors: [
                'required' => $message,
                'recaptcha' => $message,
            ],
        );
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public function run(?array $data = null): bool
    {
        $payload = $data ?? $this->validationData;
        $this->errorArray = [];
        $this->failedRules = [];
        $this->validatedData = [];

        foreach ($this->fieldData as $field => &$meta) {
            $value = $payload[$field] ?? null;
            $meta['error'] = '';

            [$prepRules, $validationRules] = $this->splitRules($meta['rules']);
            $value = $this->applyPrepRules($value, $prepRules);
            $payload[$field] = $value;

            foreach ($this->prepareRules($validationRules) as $ruleRaw) {
                [$rule, $param] = $this->parseRule($ruleRaw);
                if ($this->isSkipRuleMatch($rule, $param, $payload)) {
                    break;
                }
                if ($this->shouldSkip($rule, $value)) {
                    continue;
                }

                $ok = $this->applyRule($rule, $value, $param, $payload);
                if ($ok === true) {
                    continue;
                }

                $ruleName = $this->ruleName($rule);
                $this->failedRules[$field][$ruleName] = true;
                $message = $meta['errors'][$ruleName]
                    ?? $this->errorMessages[$ruleName]
                    ?? $this->ruleFailureMessage($ruleName)
                    ?? $this->translatedRuleMessage($ruleName)
                    ?? $this->defaultMessages[$ruleName]
                    ?? '{field} is invalid.';

                $meta['error'] = $this->formatMessage($message, $meta['label'], $param);
                $this->errorArray[$field] = $meta['error'];
                break;
            }

            $this->validatedData[$field] = $value;
        }

        return $this->errorArray === [];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, array{label?:mixed, rules?:string|array<int, string|Closure>, errors?:array<string, string>}> $schema
     */
    public function validate(array $data, array $schema): ValidationResult
    {
        $this->reset_validation();
        foreach ($schema as $field => $definition) {
            $labelValue = $definition['label'] ?? $field;
            $label = is_scalar($labelValue) ? (string) $labelValue : $field;
            $this->set_rules(
                $field,
                $label,
                $this->normalizeRuleInput($definition['rules'] ?? []),
                $this->normalizeErrorMap($definition['errors'] ?? []),
            );
        }
        $valid = $this->set_data($data)->run();
        return new ValidationResult($valid, $this->error_array(), $this->validated());
    }

    public function error(string $field, string $prefix = '', string $suffix = ''): string
    {
        $err = $this->fieldData[$field]['error'] ?? '';
        if ($err === '') {
            return '';
        }
        return ($prefix !== '' ? $prefix : $this->errorPrefix) . $err . ($suffix !== '' ? $suffix : $this->errorSuffix);
    }

    /**
     * @return array<string, string>
     */
    public function error_array(): array
    {
        return $this->errorArray;
    }

    public function error_string(string $prefix = '', string $suffix = ''): string
    {
        if ($this->errorArray === []) {
            return '';
        }
        $pre = $prefix !== '' ? $prefix : $this->errorPrefix;
        $suf = $suffix !== '' ? $suffix : $this->errorSuffix;
        $out = '';
        foreach ($this->errorArray as $message) {
            $out .= $pre . $message . $suf . "\n";
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->validatedData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getValidationData(): array
    {
        return $this->validationData;
    }

    /**
     * @param array<string, mixed> $validation
     */
    public function failedOnlyOnRule(array $validation, string $field, string $rule): bool
    {
        $failed = $validation['failed_rules'] ?? null;
        if (!is_array($failed) || !isset($failed[$field]) || !is_array($failed[$field])) {
            return false;
        }

        $failedRules = array_values(array_filter($failed[$field], static fn($v): bool => $v !== ''));

        return count($failedRules) === 1 && $failedRules[0] === $rule;
    }

    /**
     * @param array<string, mixed> $validation
     */
    public function getValueIfFailedOnlyOnRule(array $validation, string $field, string $rule): mixed
    {
        if (!$this->failedOnlyOnRule($validation, $field, $rule)) {
            return null;
        }

        $input = $validation['input'] ?? null;
        if (!is_array($input)) {
            return null;
        }

        return $input[$field] ?? null;
    }

    /**
     * @param array<string, mixed> $extraData
     * @return array<string, mixed>
     */
    public function formatDataAfterValidation(bool $isValid, array $extraData = []): array
    {
        $result = [
            'valid' => $isValid ? $this->validatedData : [],
            'input' => $isValid ? [] : $this->validationData,
            'errors' => $this->errorArray,
            'failed_rules' => $this->failedRuleNames(),
        ];

        return array_replace($result, $extraData);
    }

    public function reset_validation(): self
    {
        $this->fieldData = [];
        $this->errorArray = [];
        $this->failedRules = [];
        $this->validatedData = [];
        $this->validationData = [];
        return $this;
    }

    /**
     * @param array<int, string|Closure> $rules
     * @return array{0: array<int, string>, 1: array<int, string|Closure>}
     */
    private function splitRules(array $rules): array
    {
        $prep = [];
        $validation = [];

        foreach ($rules as $ruleRaw) {
            if ($ruleRaw instanceof Closure) {
                $validation[] = $ruleRaw;
                continue;
            }

            if ($ruleRaw === '') {
                continue;
            }

            [$rule, $param] = $this->parseRule($ruleRaw);
            if (is_string($rule) && $param === null && $this->isPrepRule($rule)) {
                $prep[] = $rule;
                continue;
            }

            $validation[] = $ruleRaw;
        }

        return [$prep, $validation];
    }

    /**
     * @param array<int, string> $rules
     */
    private function applyPrepRules(mixed $value, array $rules): mixed
    {
        $out = $value;

        foreach ($rules as $rule) {
            if ($rule === 'encode_php_tags') {
                $out = is_scalar($out) ? $this->encode_php_tags((string) $out) : '';
                continue;
            }

            if (function_exists($rule)) {
                $out = $rule($out);
            }
        }

        return $out;
    }

    private function isPrepRule(string $rule): bool
    {
        if ($rule === 'encode_php_tags') {
            return true;
        }

        $registry = $this->ruleRegistry ?? new RuleRegistry();
        if ($registry->get($rule) !== null) {
            return false;
        }

        return function_exists($rule);
    }

    /**
     * @param string|array<int, string|Closure> $rules
     * @return array<int, string|Closure>
     */
    private function normalizeRules(string|array $rules): array
    {
        if (is_array($rules)) {
            return $rules;
        }
        if ($rules === '') {
            return [];
        }
        $parts = preg_split('/\|(?![^\[]*\])/', $rules);
        return is_array($parts) ? $parts : [];
    }

    /**
     * @param array<int, string|Closure> $rules
     * @return array<int, string|Closure>
     */
    private function prepareRules(array $rules): array
    {
        $callbacks = [];
        $plain = [];
        foreach ($rules as $r) {
            if ($r instanceof Closure) {
                $callbacks[] = $r;
                continue;
            }
            if (str_starts_with($r, 'callback_')) {
                $callbacks[] = $r;
                continue;
            }
            if ($r === 'required') {
                array_unshift($plain, $r);
                continue;
            }
            $plain[] = $r;
        }
        return array_merge($callbacks, $plain);
    }

    /**
     * @return array{0: string|Closure, 1: string|null}
     */
    private function parseRule(string|Closure $rule): array
    {
        if ($rule instanceof Closure) {
            return [$rule, null];
        }
        if (preg_match('/(.*?)\[(.*)\]/', $rule, $m) === 1) {
            return [$m[1], $m[2]];
        }
        return [$rule, null];
    }

    private function ruleName(string|Closure $rule): string
    {
        if ($rule instanceof Closure) {
            return 'closure';
        }
        return str_starts_with($rule, 'callback_') ? substr($rule, 9) : $rule;
    }

    private function shouldSkip(string|Closure $rule, mixed $value): bool
    {
        if ($rule instanceof Closure) {
            return false;
        }
        $name = $this->ruleName($rule);
        if (!in_array($name, ['required', 'required_if', 'required_with', 'required_without', 'isset', 'matches', 'differs', 'recaptcha'], true)
            && ($value === null || (is_string($value) && trim($value) === ''))) {
            return true;
        }
        return false;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function isSkipRuleMatch(string|Closure $rule, ?string $param, array $data): bool
    {
        if ($rule instanceof Closure) {
            return false;
        }

        $name = $this->ruleName($rule);
        if (!in_array($name, ['skip_if', 'skip_unless'], true)) {
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

        if ($name === 'skip_if') {
            return $actual === $expected;
        }

        return $actual !== $expected;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyRule(string|Closure $rule, mixed $value, ?string $param, array $data): bool
    {
        if ($rule instanceof Closure) {
            return (bool) $rule($value, $data);
        }

        $name = $this->ruleName($rule);
        if (str_starts_with($rule, 'callback_')) {
            return false;
        }
        $registry = $this->ruleRegistry ?? new RuleRegistry();
        $ruleClass = $registry->get($name);
        if ($ruleClass === null) {
            return false;
        }

        return $ruleClass->validate($value, $param, $data);
    }

    private function ruleFailureMessage(string $ruleName): ?string
    {
        $registry = $this->ruleRegistry ?? new RuleRegistry();
        $rule = $registry->get($ruleName);

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

    private function formatMessage(string $template, string $label, ?string $param): string
    {
        return str_replace(['{field}', '{param}'], [$label, (string) $param], $template);
    }

    private function translatedRuleMessage(string $ruleName): ?string
    {
        $line = $this->translator->get('validation.' . $ruleName, [], $this->locale);

        if ($line === 'validation.' . $ruleName) {
            return null;
        }

        return $line;
    }

    /**
     * @return array<string, list<string>>
     */
    private function failedRuleNames(): array
    {
        $out = [];
        foreach ($this->failedRules as $field => $rules) {
            $names = [];
            foreach ($rules as $name => $failed) {
                if ($failed) {
                    $names[] = $name;
                }
            }
            $out[$field] = $names;
        }

        return $out;
    }

    /**
     * @return string|array<int, string|Closure>
     */
    private function normalizeRuleInput(mixed $rules): string|array
    {
        if (is_string($rules)) {
            return $rules;
        }

        if (!is_array($rules)) {
            return [];
        }

        $normalized = [];
        foreach ($rules as $rule) {
            if (is_string($rule) || $rule instanceof Closure) {
                $normalized[] = $rule;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeErrorMap(mixed $errors): array
    {
        if (!is_array($errors)) {
            return [];
        }

        $normalized = [];
        foreach ($errors as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
