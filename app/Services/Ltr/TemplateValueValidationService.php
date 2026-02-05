<?php

namespace App\Services\Ltr;

use App\Models\Ltr\FieldTemplate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TemplateValueValidationService
{
    public function validateReviewValue(FieldTemplate $template, mixed $value, mixed $normalizedValue = null): void
    {
        $errors = [];
        $type = (string) ($template->type ?: 'string');

        if (!$this->matchesType($type, $value)) {
            $errors[] = sprintf('Value must match template type "%s".', $type);
        }

        if (empty($errors)) {
            $formatError = $this->validateExpectedFormat($template, $value);

            if ($formatError !== null) {
                $errors[] = $formatError;
            }
        }

        if (empty($errors)) {
            $ruleError = $this->validateNormalizationRules($template, $value, $normalizedValue);

            if ($ruleError !== null) {
                $errors[] = $ruleError;
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'final_value' => $errors,
            ]);
        }
    }

    private function matchesType(string $type, mixed $value): bool
    {
        $stringValue = is_scalar($value) ? trim((string) $value) : '';

        return match ($type) {
            'string' => is_string($value) || is_numeric($value),
            'number' => is_scalar($value) && $stringValue !== '' && is_numeric($stringValue),
            'date' => is_scalar($value) && $stringValue !== '' && strtotime($stringValue) !== false,
            'enum' => is_scalar($value) && $stringValue !== '',
            default => true,
        };
    }

    private function validateExpectedFormat(FieldTemplate $template, mixed $value): ?string
    {
        $expectedFormat = trim((string) ($template->expected_format ?? ''));

        if ($expectedFormat === '') {
            return null;
        }

        $stringValue = trim((string) $value);

        if ($template->type === 'date') {
            return $this->matchesDateFormat($stringValue, $expectedFormat)
                ? null
                : sprintf('Value must match date format "%s".', $expectedFormat);
        }

        if ($template->type === 'enum') {
            $expectedValues = $this->enumValuesFromExpectedFormat($expectedFormat);

            if (!empty($expectedValues) && !in_array($stringValue, $expectedValues, true)) {
                return 'Value must be one of: '.implode(', ', $expectedValues).'.';
            }

            return null;
        }

        if ($this->isRegex($expectedFormat)) {
            return preg_match($expectedFormat, $stringValue) === 1
                ? null
                : sprintf('Value must match expected format "%s".', $expectedFormat);
        }

        return match (strtolower($expectedFormat)) {
            'integer' => preg_match('/^-?\d+$/', $stringValue) === 1 ? null : 'Value must be a valid integer.',
            'decimal', 'numeric', 'number' => is_numeric($stringValue) ? null : 'Value must be numeric.',
            'email' => filter_var($stringValue, FILTER_VALIDATE_EMAIL) ? null : 'Value must be a valid email address.',
            'uuid' => Str::isUuid($stringValue) ? null : 'Value must be a valid UUID.',
            default => null,
        };
    }

    private function validateNormalizationRules(FieldTemplate $template, mixed $value, mixed $normalizedValue): ?string
    {
        $rules = $template->normalization_rules;

        if (!is_array($rules) || empty($rules)) {
            return null;
        }

        $stringValue = trim((string) $value);

        $enumValues = $this->enumValuesFromRules($rules);

        if (!empty($enumValues) && !in_array($stringValue, $enumValues, true)) {
            return 'Value must be one of: '.implode(', ', $enumValues).'.';
        }

        if (isset($rules['pattern']) && is_string($rules['pattern']) && $this->isRegex($rules['pattern'])) {
            if (preg_match($rules['pattern'], $stringValue) !== 1) {
                return sprintf('Value must match normalization pattern "%s".', $rules['pattern']);
            }
        }

        if ($template->type === 'date' && isset($rules['date_format']) && is_string($rules['date_format'])) {
            if (!$this->matchesDateFormat($stringValue, $rules['date_format'])) {
                return sprintf('Value must match date format "%s".', $rules['date_format']);
            }
        }

        if ($template->type === 'number' && isset($rules['min']) && is_numeric($rules['min']) && is_numeric($stringValue)) {
            if ((float) $stringValue < (float) $rules['min']) {
                return sprintf('Value must be greater than or equal to %s.', $rules['min']);
            }
        }

        if ($template->type === 'number' && isset($rules['max']) && is_numeric($rules['max']) && is_numeric($stringValue)) {
            if ((float) $stringValue > (float) $rules['max']) {
                return sprintf('Value must be less than or equal to %s.', $rules['max']);
            }
        }

        if (is_array($normalizedValue) && isset($rules['normalized_required']) && $rules['normalized_required'] === true && empty($normalizedValue)) {
            return 'Normalized value is required by normalization rules.';
        }

        return null;
    }

    private function enumValuesFromRules(array $rules): array
    {
        foreach (['allowed_values', 'enum', 'values', 'options'] as $key) {
            if (isset($rules[$key]) && is_array($rules[$key])) {
                return array_values(array_map(static fn ($value) => (string) $value, $rules[$key]));
            }
        }

        return [];
    }

    private function enumValuesFromExpectedFormat(string $expectedFormat): array
    {
        if (str_starts_with($expectedFormat, 'enum:')) {
            $raw = substr($expectedFormat, 5);

            return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn ($value) => $value !== ''));
        }

        if (str_contains($expectedFormat, '|')) {
            return array_values(array_filter(array_map('trim', explode('|', $expectedFormat)), static fn ($value) => $value !== ''));
        }

        return [];
    }

    private function matchesDateFormat(string $value, string $format): bool
    {
        $date = \DateTime::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    private function isRegex(string $value): bool
    {
        return @preg_match($value, '') !== false;
    }
}
