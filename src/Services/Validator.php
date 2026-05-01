<?php

namespace FurEver\Services;

final class Validator
{
    /** @var array<string,string[]> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field, string $message = 'is required'): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->errors[$field][] = $message;
        }
        return $this;
    }

    public function email(string $field, string $message = 'must be a valid email'): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = $message;
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        if (is_string($value) && strlen($value) < $min) {
            $this->errors[$field][] = $message ?? "must be at least $min characters";
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $message = null): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field][] = $message ?? 'is not a valid choice';
        }
        return $this;
    }

    public function matches(string $field, string $other, string $message = 'does not match'): self
    {
        $a = $this->data[$field] ?? null;
        $b = $this->data[$other] ?? null;
        if ($a !== $b) {
            $this->errors[$field][] = $message;
        }
        return $this;
    }

    public function date(string $field, string $message = 'is not a valid date'): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && !strtotime((string) $value)) {
            $this->errors[$field][] = $message;
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /** @return array<string,string[]> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstErrorString(): string
    {
        $msgs = [];
        foreach ($this->errors as $field => $messages) {
            foreach ($messages as $m) {
                $msgs[] = ucfirst($field) . ' ' . $m;
            }
        }
        return implode('. ', $msgs);
    }
}
