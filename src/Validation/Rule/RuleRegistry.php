<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class RuleRegistry
{
    /**
     * @var array<string, ValidationRuleInterface>
     */
    private array $instances = [];

    /**
     * @var array<string, class-string<ValidationRuleInterface>>
     */
    private array $map = [
        'required' => RequiredRule::class,
        'required_if' => RequiredIfRule::class,
        'required_with' => RequiredWithRule::class,
        'required_without' => RequiredWithoutRule::class,
        'isset' => IssetRule::class,
        'valid_email' => ValidEmailRule::class,
        'valid_emails' => ValidEmailsRule::class,
        'min_length' => MinLengthRule::class,
        'max_length' => MaxLengthRule::class,
        'exact_length' => ExactLengthRule::class,
        'numeric' => NumericRule::class,
        'integer' => IntegerRule::class,
        'decimal' => DecimalRule::class,
        'alpha' => AlphaRule::class,
        'alpha_numeric' => AlphaNumericRule::class,
        'alpha_numeric_spaces' => AlphaNumericSpacesRule::class,
        'alpha_dash' => AlphaDashRule::class,
        'alpha_numeric_dash' => AlphaNumericDashRule::class,
        'regex_match' => RegexMatchRule::class,
        'matches' => MatchesRule::class,
        'differs' => DiffersRule::class,
        'in_list' => InListRule::class,
        'is_natural' => IsNaturalRule::class,
        'is_natural_no_zero' => IsNaturalNoZeroRule::class,
        'valid_base64' => ValidBase64Rule::class,
        'valid_uuid' => ValidUuidRule::class,
        'is_unique' => IsUniqueRule::class,
        'is_unique_except' => IsUniqueExceptRule::class,
        'valid_youtube_url' => ValidYoutubeUrlRule::class,
        'valid_slideslive_url' => ValidSlidesliveUrlRule::class,
        'greater_than' => GreaterThanRule::class,
        'greater_than_equal_to' => GreaterThanEqualToRule::class,
        'less_than' => LessThanRule::class,
        'less_than_equal_to' => LessThanEqualToRule::class,
        'valid_url' => ValidUrlRule::class,
        'valid_ip' => ValidIpRule::class,
        'valid_ico' => ValidIcoRule::class,
        'valid_ico_active' => ValidIcoActiveRule::class,
        'valid_dic' => ValidDicRule::class,
        'valid_dic_active' => ValidDicActiveRule::class,
        'valid_password' => ValidPasswordRule::class,
        'valid_row_id' => ValidRowIdRule::class,
        'valid_row_column' => ValidRowColumnRule::class,
        'valid_hex_color' => ValidHexColorRule::class,
        'valid_two_dates' => ValidTwoDatesRule::class,
        'valid_date' => ValidDateRule::class,
        'valid_hour' => ValidHourRule::class,
        'valid_decimal_natural' => ValidDecimalNaturalRule::class,
        'valid_money' => ValidMoneyRule::class,
        'valid_latitude' => ValidLatitudeRule::class,
        'valid_longitude' => ValidLongitudeRule::class,
        'valid_phone' => ValidPhoneRule::class,
        'valid_phone_heavy' => ValidPhoneHeavyRule::class,
        'valid_phonenumber' => ValidPhonenumberRule::class,
        'valid_street_address' => ValidStreetAddressRule::class,
        'valid_street_address_full' => ValidStreetAddressFullRule::class,
        'valid_postcode' => ValidPostcodeRule::class,
        'valid_route' => ValidRouteRule::class,
        'valid_text_no_link' => ValidTextNoLinkRule::class,
        'valid_email_heavy' => ValidEmailHeavyRule::class,
        'valid_bank_account' => ValidBankAccountRule::class,
        'recaptcha' => RecaptchaRule::class,
        'no_html' => NoHtmlRule::class,
    ];

    public function has(string $name): bool
    {
        return isset($this->map[$name]);
    }

    /**
     * @param class-string<ValidationRuleInterface>|ValidationRuleInterface $rule
     */
    public function addRule(string $name, string|ValidationRuleInterface $rule): self
    {
        $normalized = trim($name);
        if ($normalized === '') {
            throw new \InvalidArgumentException('Rule name cannot be empty.');
        }

        if (is_string($rule)) {
            if (!class_exists($rule)) {
                throw new \InvalidArgumentException(sprintf('Rule class "%s" does not exist.', $rule));
            }

            if (!is_subclass_of($rule, ValidationRuleInterface::class)) {
                throw new \InvalidArgumentException(sprintf(
                    'Rule class "%s" must implement %s.',
                    $rule,
                    ValidationRuleInterface::class,
                ));
            }

            $this->map[$normalized] = $rule;
            unset($this->instances[$normalized]);

            return $this;
        }

        $this->instances[$normalized] = $rule;
        $this->map[$normalized] = $rule::class;

        return $this;
    }

    public function get(string $name): ?ValidationRuleInterface
    {
        if (!$this->has($name)) {
            return null;
        }

        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $class = $this->map[$name];
        $rule = new $class();

        return $this->instances[$name] = $rule;
    }
}
