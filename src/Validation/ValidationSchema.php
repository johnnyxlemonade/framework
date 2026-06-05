<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation;

use InvalidArgumentException;

final class ValidationSchema
{
    /**
     * @var array<string, ValidationFieldDefinition>
     */
    private array $fields = [];

    public static function create(): self
    {
        return new self();
    }

    public function field(string $name, ?string $label = null): ValidationFieldBuilder
    {
        $name = $this->normalizeFieldName($name);
        $label = $this->normalizeLabel($label, $name);
        $field = $this->fields[$name] ?? new ValidationFieldDefinition($name, $label, []);

        if ($field->label() !== $label) {
            $field = new ValidationFieldDefinition($name, $label, $field->rules());
        }

        $this->fields[$name] = $field;

        return new ValidationFieldBuilder($this, $name);
    }

    /**
     * @return array<string, ValidationFieldDefinition>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function addRule(string $field, ValidationRuleDefinition $rule): void
    {
        $field = $this->normalizeFieldName($field);
        $definition = $this->fields[$field] ?? new ValidationFieldDefinition($field, $field, []);
        $this->fields[$field] = $definition->withRule($rule);
    }

    public function message(string $field, string $rule, string $message): self
    {
        $field = $this->normalizeFieldName($field);
        $definition = $this->fields[$field] ?? null;
        if ($definition === null) {
            throw new InvalidArgumentException(sprintf('Validation field "%s" is not defined.', $field));
        }

        $rules = [];
        $changed = false;
        foreach ($definition->rules() as $ruleDefinition) {
            if (!$changed && $ruleDefinition->name() === $rule) {
                $rules[] = $ruleDefinition->withMessage($message);
                $changed = true;
                continue;
            }

            $rules[] = $ruleDefinition;
        }

        if (!$changed) {
            throw new InvalidArgumentException(sprintf('Validation rule "%s" is not defined for field "%s".', $rule, $field));
        }

        $this->fields[$field] = new ValidationFieldDefinition($definition->name(), $definition->label(), $rules);

        return $this;
    }

    private function normalizeFieldName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Validation field name cannot be empty.');
        }

        return $name;
    }

    private function normalizeLabel(?string $label, string $field): string
    {
        if ($label === null || trim($label) === '') {
            return $field;
        }

        return $label;
    }
}
