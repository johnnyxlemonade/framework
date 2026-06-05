<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation;

use InvalidArgumentException;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Validation\FormValidation;
use Lemonade\Framework\Validation\Rule\RuleRegistry;
use Lemonade\Framework\Validation\Rule\ValidationRuleFailureDetailsInterface;
use Lemonade\Framework\Validation\Rule\ValidationRuleInterface;
use Lemonade\Framework\Validation\ValidationResult;
use Lemonade\Framework\Validation\ValidationRule;
use Lemonade\Framework\Validation\ValidationRuleDefinition;
use Lemonade\Framework\Validation\ValidationRuleName;
use Lemonade\Framework\Validation\ValidationRuleResolver;
use Lemonade\Framework\Validation\ValidationSchema;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FormValidationTest extends TestCase
{
    public function testFluentTypedApiValidatesFields(): void
    {
        $validator = $this->createValidator();

        $result = $validator
            ->field('email', 'Email')
                ->required(message: 'Email is required.')
                ->email(message: 'Email is invalid.')
                ->maxLength(100)
            ->field('name', 'Name')
                ->required()
                ->maxLength(120)
            ->validate([
                'email' => 'john@example.com',
                'name' => 'John',
            ]);

        self::assertInstanceOf(ValidationResult::class, $result);
        self::assertTrue($result->isValid());
        self::assertSame('john@example.com', $result->validated()['email']);
    }

    public function testSchemaApiValidatesExplicitSchema(): void
    {
        $validator = $this->createValidator();
        $schema = ValidationSchema::create()
            ->field('email', 'Email')
                ->required(message: 'Email is required.')
                ->email(message: 'Email is invalid.')
                ->maxLength(100)
                ->end()
            ->field('password', 'Password')
                ->required()
                ->minLength(8)
                ->end();

        $result = $validator->validate([
            'email' => 'bad',
            'password' => 'secret12',
        ], $schema);

        self::assertFalse($result->isValid());
        self::assertSame('Email is invalid.', $result->error('email'));
    }

    public function testLowLevelTypedRuleApiValidatesFields(): void
    {
        $validator = $this->createValidator();

        $result = $validator
            ->field('email', 'Email')
                ->addRule(ValidationRule::required())
                ->addRule(ValidationRule::email())
                ->addRule(ValidationRule::maxLength(100))
            ->validate(['email' => 'john@example.com']);

        self::assertTrue($result->isValid());
        self::assertSame('john@example.com', $result->validated()['email']);
    }

    public function testMessageCanTargetStableRuleName(): void
    {
        $validator = $this->createValidator();
        $schema = ValidationSchema::create()
            ->field('name', 'Name')
                ->minLength(3)
                ->message(ValidationRuleName::MIN_LENGTH, 'Name is too short.')
                ->end();

        $result = $validator->validate(['name' => 'ab'], $schema);

        self::assertFalse($result->isValid());
        self::assertSame('Name is too short.', $result->error('name'));
    }

    public function testValidationRuleNameListsOnlyBuiltInRules(): void
    {
        self::assertContains(ValidationRuleName::EMAIL, ValidationRuleName::builtIn());
        self::assertTrue(ValidationRuleName::isBuiltIn(ValidationRuleName::EMAIL));
        self::assertFalse(ValidationRuleName::isBuiltIn('slug'));
    }

    public function testRuleDefinitionNormalizesValues(): void
    {
        $rule = ValidationRuleDefinition::create(' max_length ', ' 100 ', ' Too long ');

        self::assertSame('max_length', $rule->name());
        self::assertSame('100', $rule->param());
        self::assertSame('Too long', $rule->message());

        $custom = ValidationRuleDefinition::create(' slug ', '   ', '');
        self::assertSame('slug', $custom->name());
        self::assertNull($custom->param());
        self::assertNull($custom->message());
    }

    public function testRuleDefinitionRejectsEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationRuleDefinition::create('   ');
    }

    public function testRuleDefinitionWithMessageRejectsEmptyMessage(): void
    {
        $rule = ValidationRuleDefinition::create(ValidationRuleName::EMAIL);

        $this->expectException(InvalidArgumentException::class);
        $rule->withMessage('   ');
    }

    public function testSchemaNormalizesFieldNameAndEmptyLabel(): void
    {
        $schema = ValidationSchema::create()
            ->field(' email ', '')
                ->required()
                ->end();

        self::assertArrayHasKey('email', $schema->fields());
        self::assertSame('email', $schema->fields()['email']->label());
    }

    public function testSchemaRejectsEmptyFieldName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationSchema::create()->field('   ');
    }

    public function testSchemaAddRuleRejectsEmptyFieldName(): void
    {
        $schema = ValidationSchema::create();

        $this->expectException(InvalidArgumentException::class);
        $schema->addRule('', ValidationRuleDefinition::create(ValidationRuleName::REQUIRED));
    }

    public function testSchemaMessageRejectsEmptyFieldName(): void
    {
        $schema = ValidationSchema::create();

        $this->expectException(InvalidArgumentException::class);
        $schema->message('', ValidationRuleName::REQUIRED, 'Required.');
    }

    public function testCustomRegisteredRule(): void
    {
        $registry = new RuleRegistry();
        $registry->addRule('slug', SlugRule::class);
        $validator = new FormValidation(new TestTranslator([]), $this->createRuleResolver($registry));

        $result = $validator
            ->field('slug', 'URL slug')
                ->required()
                ->custom('slug', message: 'Slug is invalid.')
            ->validate(['slug' => 'Invalid Slug']);

        self::assertFalse($result->isValid());
        self::assertSame('Slug is invalid.', $result->error('slug'));
    }

    public function testValidationRuleRejectsInvalidParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationRule::maxLength(0);
    }

    public function testValidationRuleRejectsEmptyInList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationRule::inList([]);
    }

    public function testValidationRuleRejectsEmptyRequiredIfField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationRule::requiredIf('', 'x');
    }

    public function testValidationRuleTwoDatesNormalizesOrRejectsInvalidParameters(): void
    {
        self::assertNull(ValidationRule::twoDates()->param());
        self::assertSame('start#end', ValidationRule::twoDates(' start ', ' end ')->param());

        $this->expectException(InvalidArgumentException::class);
        ValidationRule::twoDates('start', '   ');
    }

    public function testUnknownRuleFailsFast(): void
    {
        $validator = $this->createValidator();

        $this->expectException(RuntimeException::class);
        $validator
            ->field('value', 'Value')
                ->custom('missing_rule')
            ->validate(['value' => 'x']);
    }

    public function testErrorAccessorsAndFormattingData(): void
    {
        $validator = $this->createValidator();
        $result = $validator
            ->field('email', 'Email')
                ->required()
                ->email()
            ->validate(['email' => 'bad-mail']);

        self::assertFalse($result->isValid());
        self::assertSame('Email must be a valid email.', $result->error('email'));
        self::assertSame(['email' => 'Email must be a valid email.'], $result->errors());

        self::assertSame(['email' => ['valid_email']], $result->failedRules());
        self::assertTrue($result->failedOnlyOnRule('email', ValidationRuleName::EMAIL));
        self::assertSame('bad-mail', $result->getValueIfFailedOnlyOnRule('email', ValidationRuleName::EMAIL));
        self::assertSame(['email' => 'bad-mail'], $result->toArray()['input']);
    }

    public function testFailureDetailsAndTranslationsArePreserved(): void
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

        $details = $validator
            ->field('a', 'A')
                ->custom('custom_details')
            ->validate(['a' => 'x']);
        self::assertSame('Failure details message A', $details->error('a'));

        $translated = $validator
            ->field('b', 'B')
                ->custom('translated_fail')
            ->validate(['b' => 'x']);
        self::assertSame('Translated message B', $translated->error('b'));

        $key = $validator
            ->field('c', 'C')
                ->custom('custom_translate_key')
            ->validate(['c' => 'x']);
        self::assertSame('Rule key message C', $key->error('c'));
    }

    public function testRequiredRulesRunBeforeOptionalRules(): void
    {
        $validator = $this->createValidator();

        $missing = $validator
            ->field('email', 'Email')
                ->email()
                ->required()
            ->validate(['email' => '']);

        self::assertFalse($missing->isValid());
        self::assertSame('Email is required.', $missing->error('email'));

        $optional = $validator
            ->field('optional_email', 'Optional Email')
                ->email()
                ->minLength(10)
            ->validate(['optional_email' => '']);

        self::assertTrue($optional->isValid());
    }

    public function testRequiredIfWithWithoutAndSkipRules(): void
    {
        $validator = $this->createValidator();

        $requiredIf = $validator
            ->field('target', 'Target')
                ->requiredIf('mode', 'strict')
            ->validate(['mode' => 'strict', 'target' => '']);
        self::assertFalse($requiredIf->isValid());

        $requiredWith = $validator
            ->field('target', 'Target')
                ->requiredWithMessage('Target is required with other.', 'other')
            ->validate(['other' => 'x', 'target' => '']);
        self::assertFalse($requiredWith->isValid());
        self::assertSame('Target is required with other.', $requiredWith->error('target'));

        $requiredWithout = $validator
            ->field('target', 'Target')
                ->requiredWithout('other')
            ->validate(['other' => '', 'target' => '']);
        self::assertFalse($requiredWithout->isValid());

        $skipIf = $validator
            ->field('field', 'Field')
                ->skipIf('mode', 'skip')
                ->email()
            ->validate(['mode' => 'skip', 'field' => 'not-an-email']);
        self::assertTrue($skipIf->isValid());

        $skipUnless = $validator
            ->field('field', 'Field')
                ->skipUnless('mode', 'run')
                ->email()
            ->validate(['mode' => 'other', 'field' => 'not-an-email']);
        self::assertTrue($skipUnless->isValid());
    }

    public function testSetLocalePassesLocaleToTranslatorGet(): void
    {
        $translator = new TestTranslator([]);
        $validator = new FormValidation($translator, $this->createRuleResolver(new RuleRegistry()));
        $validator->setLocale('cs')
            ->field('x', 'X')
                ->required()
            ->validate(['x' => '']);

        self::assertSame('cs', $translator->lastLocale);
    }

    public function testResetValidationClearsCurrentBuilderState(): void
    {
        $validator = $this->createValidator();
        $validator->field('name', 'Name')->required();
        $validator->reset();

        $result = $validator->validate(['name' => '']);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->errors());
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

    /** @param array<string, string> $messages */
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

final class SlugRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        return is_string($value) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1;
    }
}
