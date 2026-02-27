<?php
// src/Core/Validator.php
declare(strict_types=1);
namespace Smallwork\Core;

class Validator
{
    private array $errors = [];
    private bool $isNumericContext = false;

    private function __construct(
        private array $data,
        private array $rules,
    ) {
        $this->validate();
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            $isPresent = array_key_exists($field, $this->data);
            $isRequired = in_array('required', $rules);

            // If field is not required and not present, skip all rules
            if (!$isRequired && !$isPresent) {
                continue;
            }

            $this->isNumericContext = in_array('numeric', $rules);

            foreach ($rules as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $error = $this->checkRule($field, $value, $rule, $params, $isPresent);
                if ($error !== null) {
                    $this->errors[$field][] = $error;
                }
            }
        }
    }

    private function checkRule(string $field, mixed $value, string $rule, array $params, bool $isPresent): ?string
    {
        return match ($rule) {
            'required' => $this->checkRequired($field, $value, $isPresent),
            'string' => is_string($value) || !$isPresent ? null : "$field must be a string",
            'numeric' => (is_numeric($value) || !$isPresent) ? null : "$field must be numeric",
            'email' => (filter_var($value, FILTER_VALIDATE_EMAIL) !== false || !$isPresent) ? null : "$field must be a valid email",
            'array' => (is_array($value) || !$isPresent) ? null : "$field must be an array",
            'min' => $this->checkMin($field, $value, (int) ($params[0] ?? 0)),
            'max' => $this->checkMax($field, $value, (int) ($params[0] ?? 0)),
            'in' => in_array($value, $params, false) ? null : "$field must be one of: " . implode(', ', $params),
            default => null,
        };
    }

    private function checkRequired(string $field, mixed $value, bool $isPresent): ?string
    {
        if (!$isPresent || $value === '' || $value === null) {
            return "$field is required";
        }
        return null;
    }

    private function checkMin(string $field, mixed $value, int $min): ?string
    {
        if ($value === null) return null;
        if ($this->isNumericContext && is_numeric($value)) {
            return (float) $value >= $min ? null : "$field must be at least $min";
        }
        if (is_string($value)) {
            return strlen($value) >= $min ? null : "$field must be at least $min characters";
        }
        return null;
    }

    private function checkMax(string $field, mixed $value, int $max): ?string
    {
        if ($value === null) return null;
        if ($this->isNumericContext && is_numeric($value)) {
            return (float) $value <= $max ? null : "$field must be at most $max";
        }
        if (is_string($value)) {
            return strlen($value) <= $max ? null : "$field must be at most $max characters";
        }
        return null;
    }
}
