<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation;

final class ValidationFieldBuilder
{
    public function __construct(
        private readonly ValidationSchema $schema,
        private readonly string $field,
        private readonly ?FormValidation $validator = null,
    ) {}

    public function field(string $name, ?string $label = null): self
    {
        return $this->schema->field($name, $label)->attachValidator($this->validator);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function validate(array $data): ValidationResult
    {
        if ($this->validator === null) {
            throw new \LogicException('ValidationFieldBuilder can validate only when created by FormValidation::field().');
        }

        return $this->validator->validate($data, $this->schema);
    }

    public function end(): ValidationSchema
    {
        return $this->schema;
    }

    /**
     * Low-level per-rule message override.
     *
     * Prefer passing the message directly to typed rule methods, for example
     * email('Enter a valid e-mail.') or maxLength(100, 'Maximum 100 characters.').
     * Use this method for advanced cases that target ValidationRuleName::* or
     * custom rule names registered through RuleRegistry::addRule().
     */
    public function message(string $rule, string $message): self
    {
        $this->schema->message($this->field, $rule, $message);

        return $this;
    }

    public function addRule(ValidationRuleDefinition $rule): self
    {
        $this->schema->addRule($this->field, $rule);

        return $this;
    }

    public function custom(string $name, ?string $param = null, ?string $message = null): self
    {
        return $this->addRule(ValidationRuleDefinition::create($name, $param, $message));
    }

    public function required(?string $message = null): self
    {
        return $this->addRule(ValidationRule::required($message));
    }

    public function isset(?string $message = null): self
    {
        return $this->addRule(ValidationRule::isset($message));
    }

    public function email(?string $message = null): self
    {
        return $this->addRule(ValidationRule::email($message));
    }

    public function emails(?string $message = null): self
    {
        return $this->addRule(ValidationRule::emails($message));
    }

    public function minLength(int $length, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::minLength($length, $message));
    }

    public function maxLength(int $length, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::maxLength($length, $message));
    }

    public function exactLength(int $length, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::exactLength($length, $message));
    }

    public function numeric(?string $message = null): self
    {
        return $this->addRule(ValidationRule::numeric($message));
    }

    public function integer(?string $message = null): self
    {
        return $this->addRule(ValidationRule::integer($message));
    }

    public function decimal(?string $message = null): self
    {
        return $this->addRule(ValidationRule::decimal($message));
    }

    public function alpha(?string $message = null): self
    {
        return $this->addRule(ValidationRule::alpha($message));
    }

    public function alphaNumeric(?string $message = null): self
    {
        return $this->addRule(ValidationRule::alphaNumeric($message));
    }

    public function alphaNumericSpaces(?string $message = null): self
    {
        return $this->addRule(ValidationRule::alphaNumericSpaces($message));
    }

    public function alphaDash(?string $message = null): self
    {
        return $this->addRule(ValidationRule::alphaDash($message));
    }

    public function alphaNumericDash(?string $message = null): self
    {
        return $this->addRule(ValidationRule::alphaNumericDash($message));
    }

    public function regex(string $pattern, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::regex($pattern, $message));
    }

    public function matches(string $field, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::matches($field, $message));
    }

    public function differs(string $field, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::differs($field, $message));
    }

    /** @param array<int, string|int|float|bool> $values */
    public function inList(array $values, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::inList($values, $message));
    }

    public function isNatural(?string $message = null): self
    {
        return $this->addRule(ValidationRule::isNatural($message));
    }

    public function isNaturalNoZero(?string $message = null): self
    {
        return $this->addRule(ValidationRule::isNaturalNoZero($message));
    }

    public function validBase64(?string $message = null): self
    {
        return $this->addRule(ValidationRule::validBase64($message));
    }

    public function validUuid(?string $message = null): self
    {
        return $this->addRule(ValidationRule::validUuid($message));
    }

    public function isUnique(string $tableAndField, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::isUnique($tableAndField, $message));
    }

    public function isUniqueExcept(string $tableFieldAndIdField, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::isUniqueExcept($tableFieldAndIdField, $message));
    }

    public function greaterThan(int|float $value, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::greaterThan($value, $message));
    }

    public function greaterThanOrEqualTo(int|float $value, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::greaterThanOrEqualTo($value, $message));
    }

    public function lessThan(int|float $value, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::lessThan($value, $message));
    }

    public function lessThanOrEqualTo(int|float $value, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::lessThanOrEqualTo($value, $message));
    }

    public function url(?string $message = null): self
    {
        return $this->addRule(ValidationRule::url($message));
    }

    public function youtubeUrl(?string $message = null): self
    {
        return $this->addRule(ValidationRule::youtubeUrl($message));
    }

    public function slidesliveUrl(?string $message = null): self
    {
        return $this->addRule(ValidationRule::slidesliveUrl($message));
    }

    public function ip(?string $version = null, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::ip($version, $message));
    }

    public function ico(?string $message = null): self
    {
        return $this->addRule(ValidationRule::ico($message));
    }

    public function activeIco(?string $message = null): self
    {
        return $this->addRule(ValidationRule::activeIco($message));
    }

    public function dic(?string $message = null): self
    {
        return $this->addRule(ValidationRule::dic($message));
    }

    public function activeDic(?string $message = null): self
    {
        return $this->addRule(ValidationRule::activeDic($message));
    }

    public function password(bool $requireSpecialCharacter = false, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::password($requireSpecialCharacter, $message));
    }

    public function rowId(?string $tableAndField = null, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::rowId($tableAndField, $message));
    }

    public function rowColumn(?string $tableAndField = null, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::rowColumn($tableAndField, $message));
    }

    public function hexColor(?string $message = null): self
    {
        return $this->addRule(ValidationRule::hexColor($message));
    }

    public function twoDates(?string $from = null, ?string $to = null, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::twoDates($from, $to, $message));
    }

    public function date(?string $message = null): self
    {
        return $this->addRule(ValidationRule::date($message));
    }

    public function hour(string $type = '24H', ?string $message = null): self
    {
        return $this->addRule(ValidationRule::hour($type, $message));
    }

    public function decimalNatural(?string $message = null): self
    {
        return $this->addRule(ValidationRule::decimalNatural($message));
    }

    public function money(?string $message = null): self
    {
        return $this->addRule(ValidationRule::money($message));
    }

    public function latitude(?string $message = null): self
    {
        return $this->addRule(ValidationRule::latitude($message));
    }

    public function longitude(?string $message = null): self
    {
        return $this->addRule(ValidationRule::longitude($message));
    }

    public function phone(?string $message = null): self
    {
        return $this->addRule(ValidationRule::phone($message));
    }

    public function phoneHeavy(?string $message = null): self
    {
        return $this->addRule(ValidationRule::phoneHeavy($message));
    }

    public function phoneNumber(?string $countryField = null, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::phoneNumber($countryField, $message));
    }

    public function streetAddress(?string $message = null): self
    {
        return $this->addRule(ValidationRule::streetAddress($message));
    }

    public function streetAddressFull(?string $message = null): self
    {
        return $this->addRule(ValidationRule::streetAddressFull($message));
    }

    public function postcode(?string $countryField = null, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::postcode($countryField, $message));
    }

    public function route(?string $message = null): self
    {
        return $this->addRule(ValidationRule::route($message));
    }

    public function emailHeavy(?string $message = null): self
    {
        return $this->addRule(ValidationRule::emailHeavy($message));
    }

    public function textNoLink(?string $message = null): self
    {
        return $this->addRule(ValidationRule::textNoLink($message));
    }

    public function bankAccount(?string $message = null): self
    {
        return $this->addRule(ValidationRule::bankAccount($message));
    }

    public function recaptcha(?string $secret = null, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::recaptcha($secret, $message));
    }

    public function noHtml(?string $message = null): self
    {
        return $this->addRule(ValidationRule::noHtml($message));
    }

    public function requiredIf(string $field, string|int|float|bool $expected, ?string $message = null): self
    {
        return $this->addRule(ValidationRule::requiredIf($field, $expected, $message));
    }

    public function requiredWith(string ...$fields): self
    {
        return $this->addRule(ValidationRule::requiredWith(...$fields));
    }

    public function requiredWithout(string ...$fields): self
    {
        return $this->addRule(ValidationRule::requiredWithout(...$fields));
    }

    /**
     * Message-specific variant is separate because PHP variadic parameters
     * cannot be followed by a named message argument.
     */
    public function requiredWithMessage(string $message, string ...$fields): self
    {
        return $this->addRule(ValidationRule::requiredWithFields(array_values($fields), $message));
    }

    /**
     * Message-specific variant is separate because PHP variadic parameters
     * cannot be followed by a named message argument.
     */
    public function requiredWithoutMessage(string $message, string ...$fields): self
    {
        return $this->addRule(ValidationRule::requiredWithoutFields(array_values($fields), $message));
    }

    public function skipIf(string $field, string|int|float|bool $expected): self
    {
        return $this->addRule(ValidationRule::skipIf($field, $expected));
    }

    public function skipUnless(string $field, string|int|float|bool $expected): self
    {
        return $this->addRule(ValidationRule::skipUnless($field, $expected));
    }

    public function attachValidator(?FormValidation $validator): self
    {
        return new self($this->schema, $this->field, $validator);
    }
}
