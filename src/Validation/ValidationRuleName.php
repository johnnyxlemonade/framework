<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation;

final class ValidationRuleName
{
    // Required / conditional rules.
    public const REQUIRED = 'required';
    public const REQUIRED_IF = 'required_if';
    public const REQUIRED_WITH = 'required_with';
    public const REQUIRED_WITHOUT = 'required_without';
    public const ISSET = 'isset';
    public const MATCHES = 'matches';
    public const DIFFERS = 'differs';

    // Skip rules.
    public const SKIP_IF = 'skip_if';
    public const SKIP_UNLESS = 'skip_unless';

    // String / email / length rules.
    public const EMAIL = 'valid_email';
    public const EMAILS = 'valid_emails';
    public const EMAIL_HEAVY = 'valid_email_heavy';
    public const MIN_LENGTH = 'min_length';
    public const MAX_LENGTH = 'max_length';
    public const EXACT_LENGTH = 'exact_length';
    public const ALPHA = 'alpha';
    public const ALPHA_NUMERIC = 'alpha_numeric';
    public const ALPHA_NUMERIC_SPACES = 'alpha_numeric_spaces';
    public const ALPHA_DASH = 'alpha_dash';
    public const ALPHA_NUMERIC_DASH = 'alpha_numeric_dash';
    public const REGEX = 'regex_match';
    public const IN_LIST = 'in_list';

    // Numeric rules.
    public const NUMERIC = 'numeric';
    public const INTEGER = 'integer';
    public const DECIMAL = 'decimal';
    public const IS_NATURAL = 'is_natural';
    public const IS_NATURAL_NO_ZERO = 'is_natural_no_zero';
    public const DECIMAL_NATURAL = 'valid_decimal_natural';
    public const MONEY = 'valid_money';

    // Comparison rules.
    public const GREATER_THAN = 'greater_than';
    public const GREATER_THAN_EQUAL_TO = 'greater_than_equal_to';
    public const LESS_THAN = 'less_than';
    public const LESS_THAN_EQUAL_TO = 'less_than_equal_to';

    // Format / URL / IP rules.
    public const VALID_BASE64 = 'valid_base64';
    public const VALID_UUID = 'valid_uuid';
    public const URL = 'valid_url';
    public const YOUTUBE_URL = 'valid_youtube_url';
    public const SLIDESLIVE_URL = 'valid_slideslive_url';
    public const IP = 'valid_ip';
    public const HEX_COLOR = 'valid_hex_color';
    public const ROUTE = 'valid_route';

    // CZ / business rules.
    public const ICO = 'valid_ico';
    public const ICO_ACTIVE = 'valid_ico_active';
    public const DIC = 'valid_dic';
    public const DIC_ACTIVE = 'valid_dic_active';
    public const BANK_ACCOUNT = 'valid_bank_account';

    // Date / time rules.
    public const TWO_DATES = 'valid_two_dates';
    public const DATE = 'valid_date';
    public const HOUR = 'valid_hour';

    // Geo / phone / address rules.
    public const LATITUDE = 'valid_latitude';
    public const LONGITUDE = 'valid_longitude';
    public const PHONE = 'valid_phone';
    public const PHONE_HEAVY = 'valid_phone_heavy';
    public const PHONE_NUMBER = 'valid_phonenumber';
    public const STREET_ADDRESS = 'valid_street_address';
    public const STREET_ADDRESS_FULL = 'valid_street_address_full';
    public const POSTCODE = 'valid_postcode';

    // Database / integration rules.
    public const IS_UNIQUE = 'is_unique';
    public const IS_UNIQUE_EXCEPT = 'is_unique_except';
    public const ROW_ID = 'valid_row_id';
    public const ROW_COLUMN = 'valid_row_column';

    // Security / captcha / HTML rules.
    public const PASSWORD = 'valid_password';
    public const TEXT_NO_LINK = 'valid_text_no_link';
    public const RECAPTCHA = 'recaptcha';
    public const NO_HTML = 'no_html';

    private function __construct() {}

    /**
     * Built-in framework validation rule names.
     *
     * Custom rules registered through RuleRegistry::addRule()
     * are valid too, even though they are not listed here.
     *
     * @return list<string>
     */
    public static function builtIn(): array
    {
        return [
            self::REQUIRED,
            self::REQUIRED_IF,
            self::REQUIRED_WITH,
            self::REQUIRED_WITHOUT,
            self::ISSET,
            self::MATCHES,
            self::DIFFERS,
            self::SKIP_IF,
            self::SKIP_UNLESS,
            self::EMAIL,
            self::EMAILS,
            self::EMAIL_HEAVY,
            self::MIN_LENGTH,
            self::MAX_LENGTH,
            self::EXACT_LENGTH,
            self::ALPHA,
            self::ALPHA_NUMERIC,
            self::ALPHA_NUMERIC_SPACES,
            self::ALPHA_DASH,
            self::ALPHA_NUMERIC_DASH,
            self::REGEX,
            self::IN_LIST,
            self::NUMERIC,
            self::INTEGER,
            self::DECIMAL,
            self::IS_NATURAL,
            self::IS_NATURAL_NO_ZERO,
            self::DECIMAL_NATURAL,
            self::MONEY,
            self::GREATER_THAN,
            self::GREATER_THAN_EQUAL_TO,
            self::LESS_THAN,
            self::LESS_THAN_EQUAL_TO,
            self::VALID_BASE64,
            self::VALID_UUID,
            self::URL,
            self::YOUTUBE_URL,
            self::SLIDESLIVE_URL,
            self::IP,
            self::HEX_COLOR,
            self::ROUTE,
            self::ICO,
            self::ICO_ACTIVE,
            self::DIC,
            self::DIC_ACTIVE,
            self::BANK_ACCOUNT,
            self::TWO_DATES,
            self::DATE,
            self::HOUR,
            self::LATITUDE,
            self::LONGITUDE,
            self::PHONE,
            self::PHONE_HEAVY,
            self::PHONE_NUMBER,
            self::STREET_ADDRESS,
            self::STREET_ADDRESS_FULL,
            self::POSTCODE,
            self::IS_UNIQUE,
            self::IS_UNIQUE_EXCEPT,
            self::ROW_ID,
            self::ROW_COLUMN,
            self::PASSWORD,
            self::TEXT_NO_LINK,
            self::RECAPTCHA,
            self::NO_HTML,
        ];
    }

    public static function isBuiltIn(string $name): bool
    {
        return in_array($name, self::builtIn(), true);
    }
}
