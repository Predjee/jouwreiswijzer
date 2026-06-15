<?php

declare(strict_types=1);

namespace App\Service;

final class FormMailPlaceholderRenderer
{
    /**
     * Unknown placeholders are deliberately left unchanged.
     *
     * @param array<string, mixed> $values
     */
    public function render(?string $text, array $values): string
    {
        if (null === $text || '' === $text) {
            return '';
        }

        return (string) \preg_replace_callback(
            '/\{([A-Za-z0-9_.-]+)\}/',
            fn (array $matches): string => \array_key_exists($matches[1], $values)
                ? $this->normalizeValue($values[$matches[1]])
                : $matches[0],
            $text,
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    public function renderSubject(?string $subject, array $values): string
    {
        return (string) \preg_replace('/[\r\n]+/', ' ', $this->render($subject, $values));
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     */
    public function renderSerializedFields(?string $text, array $fields): string
    {
        $values = [];

        foreach ($fields as $field) {
            $key = $field['key'] ?? null;

            if (\is_string($key) && '' !== $key) {
                $values[$key] = $field['value'] ?? null;
            }
        }

        return $this->render($text, $values);
    }

    private function normalizeValue(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        if (\is_bool($value)) {
            return $value ? 'Ja' : 'Nee';
        }

        if (\is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        if (\is_array($value)) {
            return \implode(', ', \array_map($this->normalizeValue(...), $value));
        }

        return '';
    }
}
