<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation;

use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Validation\FormValidation;
use Lemonade\Framework\Validation\Rule\RuleRegistry;
use Lemonade\Framework\Validation\Rule\ValidationRuleFailureDetailsInterface;
use Lemonade\Framework\Validation\Rule\ValidationRuleInterface;
use Lemonade\Framework\Validation\ValidationRuleResolver;
use Lemonade\Framework\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

final class FormValidationTest extends TestCase
{
    public function testSetDataAndGetValidationData(): void
    {
        $validation = $this->createValidator();
        $validation->set_data(['name' => 'John']);

        self::assertSame(['name' => 'John'], $validation->getValidationData());
    }

    public function testSetRulesSingleFieldAndRun(): void
    {
        $validation = $this->createValidator();
        $validation->set_rules('email', 'Email', 'required|valid_email')
            ->set_data(['email' => 'john@example.com']);

        self::assertTrue($validation->run());
    }

    public function testSetRulesArrayDefinitionAndIgnoresInvalidRows(): void
    {
        $validation = $this->createValidator();
        $rows = [
            ['field' => 'name', 'label' => 'Name', 'rules' => 'required'],
            ['field' => [], 'label' => 'Invalid row', 'rules' => 'required'],
            ['field' => 'email', 'rules' => 'required|valid_email'],
        ];

        $validation->set_rules($rows)
            ->set_data(['name' => 'John', 'email' => 'john@example.com']);

        self::assertTrue($validation->run());
    }

    public function testRunFalseAndErrorsMethods(): void
    {
        $validation = $this->createValidator();
        $validation->set_rules('email', 'Email', 'required|valid_email')
            ->set_data(['email' => 'bad-mail']);

        self::assertFalse($validation->run());
        self::assertArrayHasKey('email', $validation->error_array());
        self::assertStringContainsString('Email', $validation->error('email'));
        self::assertSame('[[Email must be a valid email.]]', $validation->error('email', '[[', ']]'));
        self::assertStringContainsString('<p>Email must be a valid email.</p>', $validation->error_string());
    }

    public function testSetErrorDelimitersAffectsErrorAndErrorString(): void
    {
        $validation = $this->createValidator();
        $validation->set_error_delimiters('<div>', '</div>')
            ->set_rules('email', 'Email', 'valid_email')
            ->set_data(['email' => 'bad']);

        self::assertFalse($validation->run());
        self::assertSame('<div>Email must be a valid email.</div>', $validation->error('email'));
        self::assertStringContainsString('<div>Email must be a valid email.</div>', $validation->error_string());
    }

    public function testMessagePriorityPerFieldOverGlobalAndDefaultWithParams(): void
    {
        $validation = $this->createValidator();
        $validation->set_message('min_length', 'GLOBAL {field} {param}')
            ->set_rules('name', 'Name', 'min_length[3]', ['min_length' => 'FIELD {field} {param}'])
            ->set_data(['name' => 'ab']);

        self::assertFalse($validation->run());
        self::assertStringContainsString('FIELD Name 3', $validation->error('name'));
    }

    public function testMessagePriorityFailureDetailsAndTranslatedAndFallback(): void
    {
        $translator = new TestTranslator([
            'validation.translated_fail' => 'Translated message {field}',
            'validation.custom_key' => 'Rule key message {field}',
        ]);
        $registry = new RuleRegistry();
        $registry->addRule('custom_details', new RuleWithFailureMessage('Failure details message {field}'));
        $registry->addRule('translated_fail', new AlwaysFailRule());
        $registry->addRule('custom_translate_key', new RuleWithFailureTranslationKey('custom_key'));
        $validator = new FormValidation($translator, $this->createRuleResolver($registry));

        $validator->set_rules('a', 'A', 'custom_details')
            ->set_data(['a' => 'x']);
        self::assertFalse($validator->run());
        self::assertStringContainsString('Failure details message A', $validator->error('a'));

        $validator->reset_validation()
            ->set_rules('b', 'B', 'translated_fail')
            ->set_data(['b' => 'x']);
        self::assertFalse($validator->run());
        self::assertStringContainsString('Translated message B', $validator->error('b'));

        $validator->reset_validation()
            ->set_rules('c', 'C', 'custom_translate_key')
            ->set_data(['c' => 'x']);
        self::assertFalse($validator->run());
        self::assertStringContainsString('Rule key message C', $validator->error('c'));
    }

    public function testValidatedAndResetValidation(): void
    {
        $validation = $this->createValidator();
        $validation->set_rules('name', 'Name', 'trim|required')
            ->set_data(['name' => '  John  ']);

        self::assertTrue($validation->run());
        self::assertSame('John', $validation->validated()['name']);

        $validation->reset_validation();
        self::assertSame([], $validation->validated());
        self::assertSame([], $validation->error_array());
        self::assertSame([], $validation->getValidationData());
    }

    public function testValidateReturnsValidationResult(): void
    {
        $validation = $this->createValidator();
        $result = $validation->validate(
            ['email' => 'bad'],
            [
                'email' => ['label' => 'Email', 'rules' => 'required|valid_email'],
            ],
        );

        self::assertInstanceOf(ValidationResult::class, $result);
        self::assertFalse($result->isValid());
        self::assertNotNull($result->error('email'));
    }

    public function testFormatDataAfterValidationForValidAndInvalid(): void
    {
        $validation = $this->createValidator();
        $validation->set_rules('name', 'Name', 'required')
            ->set_data(['name' => 'John']);
        self::assertTrue($validation->run());
        $ok = $validation->formatDataAfterValidation(true);
        self::assertSame(['name' => 'John'], $ok['valid']);
        self::assertSame([], $ok['input']);

        $validation->reset_validation()
            ->set_rules('name', 'Name', 'required')
            ->set_data(['name' => '']);
        self::assertFalse($validation->run());
        $fail = $validation->formatDataAfterValidation(false);
        self::assertIsArray($fail['errors']);
        self::assertIsArray($fail['failed_rules']);
        self::assertSame([], $fail['valid']);
        self::assertSame(['name' => ''], $fail['input']);
        self::assertArrayHasKey('name', $fail['errors']);
        self::assertArrayHasKey('name', $fail['failed_rules']);
    }

    public function testFailedOnlyOnRuleAndGetValueIfFailedOnlyOnRule(): void
    {
        $validation = $this->createValidator();
        $validation->set_rules('name', 'Name', 'required')
            ->set_data(['name' => '']);
        self::assertFalse($validation->run());
        $data = $validation->formatDataAfterValidation(false);

        self::assertTrue($validation->failedOnlyOnRule($data, 'name', 'required'));
        self::assertSame('', $validation->getValueIfFailedOnlyOnRule($data, 'name', 'required'));
        self::assertNull($validation->getValueIfFailedOnlyOnRule($data, 'name', 'valid_email'));
    }

    public function testEncodePhpTagsAndPrepRules(): void
    {
        $validation = $this->createValidator();
        self::assertSame('&lt;?php ?&gt;', $validation->encode_php_tags('<?php ?>'));

        $validation->set_rules('content', 'Content', 'encode_php_tags|required')
            ->set_data(['content' => '<?php ?>']);
        self::assertTrue($validation->run());
        self::assertSame('&lt;?php ?&gt;', $validation->validated()['content']);

        $validation->reset_validation()
            ->set_rules('name', 'Name', 'trim|required')
            ->set_data(['name' => '  John  ']);
        self::assertTrue($validation->run());
        self::assertSame('John', $validation->validated()['name']);
    }

    public function testClosureRulePassAndFailWithClosureName(): void
    {
        $passing = static function (mixed $value, array $data): bool {
            unset($data);
            return is_string($value) && $value === 'ok';
        };
        $failing = static function (mixed $value, array $data): bool {
            unset($value, $data);
            return false;
        };

        $validation = $this->createValidator();
        $validation->set_rules('v', 'V', [$passing])
            ->set_data(['v' => 'ok']);
        self::assertTrue($validation->run());

        $validation->reset_validation()
            ->set_rules('v', 'V', [$failing])
            ->set_data(['v' => 'ok']);
        self::assertFalse($validation->run());
        $formatted = $validation->formatDataAfterValidation(false);
        self::assertIsArray($formatted['failed_rules']);
        self::assertIsArray($formatted['failed_rules']['v']);
        self::assertSame(['closure'], $formatted['failed_rules']['v']);
    }

    public function testRequiredIsEvaluatedBeforeOtherRulesAndOptionalEmptySkipsNonRequired(): void
    {
        $validation = $this->createValidator();
        $validation->set_rules('email', 'Email', 'valid_email|required')
            ->set_data(['email' => '']);
        self::assertFalse($validation->run());
        self::assertStringContainsString('is required', $validation->error('email'));

        $validation->reset_validation()
            ->set_rules('optional_email', 'Optional Email', 'valid_email|min_length[10]')
            ->set_data(['optional_email' => '']);
        self::assertTrue($validation->run());
    }

    public function testRequiredIfWithWithoutAndSkipRules(): void
    {
        $validation = $this->createValidator();
        $validation->set_rules('target', 'Target', 'required_if[mode,strict]')
            ->set_data(['mode' => 'strict', 'target' => '']);
        self::assertFalse($validation->run());

        $validation->reset_validation()
            ->set_rules('target', 'Target', 'required_with[other]')
            ->set_data(['other' => 'x', 'target' => '']);
        self::assertFalse($validation->run());

        $validation->reset_validation()
            ->set_rules('target', 'Target', 'required_without[other]')
            ->set_data(['other' => '', 'target' => '']);
        self::assertFalse($validation->run());

        $validation->reset_validation()
            ->set_rules('field', 'Field', 'skip_if[mode,skip]|valid_email')
            ->set_data(['mode' => 'skip', 'field' => 'not-an-email']);
        self::assertTrue($validation->run());

        $validation->reset_validation()
            ->set_rules('field', 'Field', 'skip_unless[mode,run]|valid_email')
            ->set_data(['mode' => 'other', 'field' => 'not-an-email']);
        self::assertTrue($validation->run());
    }

    public function testSetLocalePassesLocaleToTranslatorGet(): void
    {
        $translator = new TestTranslator([]);
        $validation = new FormValidation($translator, $this->createRuleResolver(new RuleRegistry()));
        $validation->setLocale('cs')
            ->set_rules('x', 'X', 'required')
            ->set_data(['x' => '']);

        self::assertFalse($validation->run());
        self::assertSame('cs', $translator->lastLocale);
    }

    private function createValidator(): FormValidation
    {
        $translator = new TestTranslator([
            'validation.required' => '{field} is required.',
            'validation.valid_email' => '{field} must be a valid email.',
            'validation.min_length' => '{field} must be at least {param} characters.',
        ]);

        return new FormValidation($translator, $this->createRuleResolver(new RuleRegistry()));
    }

    private function createRuleResolver(RuleRegistry $registry): ValidationRuleResolver
    {
        $container = new Container();
        $container->singleton(ContainerInterface::class, $container);

        return new ValidationRuleResolver($registry, $container);
    }

}

final class TestTranslator implements TranslatorInterface
{
    public ?string $lastLocale = null;

    /**
     * @param array<string, string> $messages
     */
    public function __construct(private array $messages) {}

    public function setLocale(?string $locale): TranslatorInterface
    {
        $this->lastLocale = $locale;

        return $this;
    }

    public function locale(): ?string
    {
        return $this->lastLocale;
    }

    public function get(string $key, array $replacements = [], ?string $locale = null): string
    {
        $this->lastLocale = $locale;
        $line = $this->messages[$key] ?? $key;

        foreach ($replacements as $name => $value) {
            $line = str_replace('{' . $name . '}', (string) $value, $line);
        }

        return $line;
    }

    public function group(string $group, ?string $locale = null): array
    {
        unset($group, $locale);

        return [];
    }

    public function all(?string $locale = null): array
    {
        unset($locale);

        return [];
    }
}

final class AlwaysFailRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($value, $param, $data);

        return false;
    }
}

final class RuleWithFailureMessage implements ValidationRuleInterface, ValidationRuleFailureDetailsInterface
{
    public function __construct(
        private readonly ?string $message = null,
    ) {}

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($value, $param, $data);

        return false;
    }

    public function pullFailureMessage(): ?string
    {
        return $this->message;
    }

    public function pullFailureTranslationKey(): ?string
    {
        return null;
    }
}

final class RuleWithFailureTranslationKey implements ValidationRuleInterface, ValidationRuleFailureDetailsInterface
{
    public function __construct(
        private readonly ?string $translationKey = null,
    ) {}

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($value, $param, $data);

        return false;
    }

    public function pullFailureMessage(): ?string
    {
        return null;
    }

    public function pullFailureTranslationKey(): ?string
    {
        return $this->translationKey;
    }
}
