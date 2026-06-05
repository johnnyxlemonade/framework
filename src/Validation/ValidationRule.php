<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation;

use InvalidArgumentException;

final class ValidationRule
{
    private function __construct() {}

    // Required / conditional rules.

    public static function required(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::REQUIRED, null, $message);
    }

    public static function isset(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::ISSET, null, $message);
    }

    public static function requiredIf(string $field, string|int|float|bool $expected, ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::REQUIRED_IF, self::dependencyParam($field, $expected), $message);
    }

    public static function requiredWith(string ...$fields): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::REQUIRED_WITH, self::fieldsParam(ValidationRuleName::REQUIRED_WITH, array_values($fields)), null);
    }

    public static function requiredWithout(string ...$fields): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::REQUIRED_WITHOUT, self::fieldsParam(ValidationRuleName::REQUIRED_WITHOUT, array_values($fields)), null);
    }

    /** @param list<string> $fields */
    public static function requiredWithFields(array $fields, ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::REQUIRED_WITH, self::fieldsParam(ValidationRuleName::REQUIRED_WITH, $fields), $message);
    }

    /** @param list<string> $fields */
    public static function requiredWithoutFields(array $fields, ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::REQUIRED_WITHOUT, self::fieldsParam(ValidationRuleName::REQUIRED_WITHOUT, $fields), $message);
    }

    public static function matches(string $field, ?string $message = null): ValidationRuleDefinition
    {
        return self::requiredParam(ValidationRuleName::MATCHES, $field, $message);
    }

    public static function differs(string $field, ?string $message = null): ValidationRuleDefinition
    {
        return self::requiredParam(ValidationRuleName::DIFFERS, $field, $message);
    }

    // Skip rules.

    public static function skipIf(string $field, string|int|float|bool $expected): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::SKIP_IF, self::dependencyParam($field, $expected));
    }

    public static function skipUnless(string $field, string|int|float|bool $expected): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::SKIP_UNLESS, self::dependencyParam($field, $expected));
    }

    // String / email / length rules.

    public static function email(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::EMAIL, null, $message);
    }

    public static function emails(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::EMAILS, null, $message);
    }

    public static function emailHeavy(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::EMAIL_HEAVY, null, $message);
    }

    public static function minLength(int $length, ?string $message = null): ValidationRuleDefinition
    {
        return self::positiveInt(ValidationRuleName::MIN_LENGTH, $length, $message);
    }

    public static function maxLength(int $length, ?string $message = null): ValidationRuleDefinition
    {
        return self::positiveInt(ValidationRuleName::MAX_LENGTH, $length, $message);
    }

    public static function exactLength(int $length, ?string $message = null): ValidationRuleDefinition
    {
        return self::positiveInt(ValidationRuleName::EXACT_LENGTH, $length, $message);
    }

    public static function alpha(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::ALPHA, null, $message);
    }

    public static function alphaNumeric(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::ALPHA_NUMERIC, null, $message);
    }

    public static function alphaNumericSpaces(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::ALPHA_NUMERIC_SPACES, null, $message);
    }

    public static function alphaDash(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::ALPHA_DASH, null, $message);
    }

    public static function alphaNumericDash(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::ALPHA_NUMERIC_DASH, null, $message);
    }

    public static function regex(string $pattern, ?string $message = null): ValidationRuleDefinition
    {
        return self::requiredParam(ValidationRuleName::REGEX, $pattern, $message);
    }

    /**
     * Values are serialized with commas because ValidationRuleDefinition
     * currently stores a scalar string parameter. Values containing commas are
     * not a safe case; structured rule parameters are a separate future refactor.
     *
     * @param array<int, string|int|float|bool> $values
     */
    public static function inList(array $values, ?string $message = null): ValidationRuleDefinition
    {
        if ($values === []) {
            throw new InvalidArgumentException('Validation inList() values cannot be empty.');
        }

        return self::create(ValidationRuleName::IN_LIST, implode(',', array_map(self::scalarToString(...), $values)), $message);
    }

    // Numeric rules.

    public static function numeric(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::NUMERIC, null, $message);
    }

    public static function integer(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::INTEGER, null, $message);
    }

    public static function decimal(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::DECIMAL, null, $message);
    }

    public static function isNatural(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::IS_NATURAL, null, $message);
    }

    public static function isNaturalNoZero(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::IS_NATURAL_NO_ZERO, null, $message);
    }

    public static function decimalNatural(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::DECIMAL_NATURAL, null, $message);
    }

    public static function money(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::MONEY, null, $message);
    }

    // Comparison rules.

    public static function greaterThan(int|float $value, ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::GREATER_THAN, (string) $value, $message);
    }

    public static function greaterThanOrEqualTo(int|float $value, ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::GREATER_THAN_EQUAL_TO, (string) $value, $message);
    }

    public static function lessThan(int|float $value, ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::LESS_THAN, (string) $value, $message);
    }

    public static function lessThanOrEqualTo(int|float $value, ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::LESS_THAN_EQUAL_TO, (string) $value, $message);
    }

    // Format / URL / IP rules.

    public static function validBase64(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::VALID_BASE64, null, $message);
    }

    public static function validUuid(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::VALID_UUID, null, $message);
    }

    public static function url(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::URL, null, $message);
    }

    public static function youtubeUrl(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::YOUTUBE_URL, null, $message);
    }

    public static function slidesliveUrl(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::SLIDESLIVE_URL, null, $message);
    }

    public static function ip(?string $version = null, ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::IP, $version, $message);
    }

    public static function hexColor(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::HEX_COLOR, null, $message);
    }

    public static function route(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::ROUTE, null, $message);
    }

    // CZ / business rules.

    public static function ico(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::ICO, null, $message);
    }

    public static function activeIco(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::ICO_ACTIVE, null, $message);
    }

    public static function dic(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::DIC, null, $message);
    }

    public static function activeDic(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::DIC_ACTIVE, null, $message);
    }

    public static function bankAccount(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::BANK_ACCOUNT, null, $message);
    }

    // Date / time rules.

    public static function twoDates(?string $from = null, ?string $to = null, ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::TWO_DATES, self::twoDatesParam($from, $to), $message);
    }

    public static function date(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::DATE, null, $message);
    }

    public static function hour(string $type = '24H', ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::HOUR, $type, $message);
    }

    // Geo / phone / address rules.

    public static function latitude(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::LATITUDE, null, $message);
    }

    public static function longitude(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::LONGITUDE, null, $message);
    }

    public static function phone(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::PHONE, null, $message);
    }

    public static function phoneHeavy(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::PHONE_HEAVY, null, $message);
    }

    public static function phoneNumber(?string $countryField = null, ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::PHONE_NUMBER, $countryField, $message);
    }

    public static function streetAddress(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::STREET_ADDRESS, null, $message);
    }

    public static function streetAddressFull(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::STREET_ADDRESS_FULL, null, $message);
    }

    public static function postcode(?string $countryField = null, ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::POSTCODE, $countryField, $message);
    }

    // Database / integration rules.

    public static function isUnique(string $tableAndField, ?string $message = null): ValidationRuleDefinition
    {
        return self::requiredParam(ValidationRuleName::IS_UNIQUE, $tableAndField, $message);
    }

    public static function isUniqueExcept(string $tableFieldAndIdField, ?string $message = null): ValidationRuleDefinition
    {
        return self::requiredParam(ValidationRuleName::IS_UNIQUE_EXCEPT, $tableFieldAndIdField, $message);
    }

    public static function rowId(?string $tableAndField = null, ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::ROW_ID, $tableAndField, $message);
    }

    public static function rowColumn(?string $tableAndField = null, ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::ROW_COLUMN, $tableAndField, $message);
    }

    // Security / captcha / HTML rules.

    public static function password(bool $requireSpecialCharacter = false, ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::PASSWORD, $requireSpecialCharacter ? 'special' : null, $message);
    }

    public static function textNoLink(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::TEXT_NO_LINK, null, $message);
    }

    public static function recaptcha(?string $secret = null, ?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::RECAPTCHA, $secret, $message);
    }

    public static function noHtml(?string $message = null): ValidationRuleDefinition
    {
        return self::create(ValidationRuleName::NO_HTML, null, $message);
    }

    private static function create(string $name, ?string $param = null, ?string $message = null): ValidationRuleDefinition
    {
        return ValidationRuleDefinition::create($name, $param, $message);
    }

    private static function positiveInt(string $name, int $length, ?string $message): ValidationRuleDefinition
    {
        if ($length < 1) {
            throw new InvalidArgumentException(sprintf('Validation rule %s() expects a positive length.', $name));
        }

        return self::create($name, (string) $length, $message);
    }

    private static function requiredParam(string $name, string $param, ?string $message): ValidationRuleDefinition
    {
        if (trim($param) === '') {
            throw new InvalidArgumentException(sprintf('Validation rule %s() expects a non-empty parameter.', $name));
        }

        return self::create($name, $param, $message);
    }

    /** @param list<string> $fields */
    private static function fieldsParam(string $name, array $fields): string
    {
        $normalized = array_values(array_filter(array_map('trim', $fields), static fn(string $field): bool => $field !== ''));
        if ($normalized === []) {
            throw new InvalidArgumentException(sprintf('Validation rule %s() expects at least one field.', $name));
        }

        return implode(',', $normalized);
    }

    private static function twoDatesParam(?string $from, ?string $to): ?string
    {
        if ($from === null && $to === null) {
            return null;
        }

        if ($from === null || $to === null) {
            throw new InvalidArgumentException('Validation rule valid_two_dates() expects both date fields or no parameter.');
        }

        $from = trim($from);
        $to = trim($to);
        if ($from === '' || $to === '') {
            throw new InvalidArgumentException('Validation rule valid_two_dates() expects non-empty date fields.');
        }

        return $from . '#' . $to;
    }

    private static function dependencyParam(string $field, string|int|float|bool $expected): string
    {
        $field = trim($field);
        if ($field === '') {
            throw new InvalidArgumentException('Dependent validation field cannot be empty.');
        }

        return $field . ',' . self::scalarToString($expected);
    }

    private static function scalarToString(string|int|float|bool $value): string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
