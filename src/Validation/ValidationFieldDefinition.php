<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation;

final class ValidationFieldDefinition
{
    /**
     * @param list<ValidationRuleDefinition> $rules
     */
    public function __construct(
        private readonly string $name,
        private readonly string $label,
        private readonly array $rules,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * @return list<ValidationRuleDefinition>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    public function withRule(ValidationRuleDefinition $rule): self
    {
        return new self($this->name, $this->label, [...$this->rules, $rule]);
    }

    public function withLastRuleMessage(string $message): self
    {
        if ($this->rules === []) {
            throw new \LogicException('Cannot set a message before adding a validation rule.');
        }

        $rules = $this->rules;
        $last = array_key_last($rules);
        if ($last === null) {
            throw new \LogicException('Cannot set a message before adding a validation rule.');
        }

        $rules[$last] = $rules[$last]->withMessage($message);

        return new self($this->name, $this->label, array_values($rules));
    }
}
