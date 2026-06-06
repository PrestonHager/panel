<?php

namespace Pterodactyl\Services\Plugins;

use Pterodactyl\Plugins\PluginSettingsField;
use Pterodactyl\Plugins\PluginSettingsSchema;
use Pterodactyl\Plugins\Exceptions\PluginException;

class PluginSettingsValidator
{
    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function validate(PluginSettingsSchema $schema, array $values, string $surface): array
    {
        $validated = [];
        $errors = [];

        foreach ($schema->forSurface($surface) as $field) {
            $key = $field->key;
            $hasValue = array_key_exists($key, $values);
            $value = $hasValue ? $values[$key] : null;

            if ($field->isPassword() && $value === PluginSettingsField::PASSWORD_MASK) {
                continue;
            }

            if (!$hasValue || ($value === null || $value === '')) {
                if ($field->required) {
                    $errors[$key] = sprintf('The %s field is required.', $field->label);
                } elseif ($field->default !== null) {
                    $validated[$key] = $field->default;
                }

                continue;
            }

            try {
                $validated[$key] = $this->validateField($field, $value);
            } catch (PluginException $exception) {
                $errors[$key] = $exception->getMessage();
            }
        }

        if (!empty($errors)) {
            throw new PluginException(collect($errors)->map(fn ($message, $key) => "$key: $message")->implode(' '));
        }

        return $validated;
    }

    private function validateField(PluginSettingsField $field, mixed $value): mixed
    {
        return match ($field->type) {
            'boolean' => $this->validateBoolean($field, $value),
            'integer' => $this->validateInteger($field, $value),
            'number' => $this->validateNumber($field, $value),
            'select' => $this->validateSelect($field, $value),
            'multiselect' => $this->validateMultiselect($field, $value),
            'json' => $this->validateJson($value),
            'password', 'string', 'text' => $this->validateString($field, $value),
            default => throw new PluginException(sprintf('Unsupported field type "%s".', $field->type)),
        };
    }

    private function validateBoolean(PluginSettingsField $field, mixed $value): bool
    {
        if ($value === '1' || $value === 1 || $value === true || $value === 'true') {
            return true;
        }

        if ($value === '0' || $value === 0 || $value === false || $value === 'false') {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    private function validateString(PluginSettingsField $field, mixed $value): string
    {
        if (!is_scalar($value)) {
            throw new PluginException(sprintf('The %s field must be a string.', $field->label));
        }

        $string = (string) $value;
        if ($field->required && trim($string) === '') {
            throw new PluginException(sprintf('The %s field is required.', $field->label));
        }

        return $string;
    }

    private function validateInteger(PluginSettingsField $field, mixed $value): int
    {
        if (!is_numeric($value)) {
            throw new PluginException(sprintf('The %s field must be an integer.', $field->label));
        }

        $integer = (int) $value;
        if (!is_null($field->min) && $integer < $field->min) {
            throw new PluginException(sprintf('The %s field must be at least %d.', $field->label, $field->min));
        }
        if (!is_null($field->max) && $integer > $field->max) {
            throw new PluginException(sprintf('The %s field must be at most %d.', $field->label, $field->max));
        }

        return $integer;
    }

    private function validateNumber(PluginSettingsField $field, mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new PluginException(sprintf('The %s field must be a number.', $field->label));
        }

        return (float) $value;
    }

    private function validateSelect(PluginSettingsField $field, mixed $value): string
    {
        $string = $this->validateString($field, $value);
        if (empty($field->options)) {
            return $string;
        }

        $allowed = array_column($field->options, 'value');
        if (!in_array($string, $allowed, true)) {
            throw new PluginException(sprintf('The %s field contains an invalid option.', $field->label));
        }

        return $string;
    }

    /**
     * @return string[]
     */
    private function validateMultiselect(PluginSettingsField $field, mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }

        if (!is_array($value)) {
            throw new PluginException(sprintf('The %s field must be an array.', $field->label));
        }

        $allowed = array_column($field->options, 'value');
        $result = [];
        foreach ($value as $item) {
            $item = (string) $item;
            if (!empty($allowed) && !in_array($item, $allowed, true)) {
                throw new PluginException(sprintf('The %s field contains an invalid option.', $field->label));
            }
            $result[] = $item;
        }

        return array_values(array_unique($result));
    }

    /**
     * @return array<string, mixed>|array<int, mixed>
     */
    private function validateJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            throw new PluginException('JSON field must be a valid JSON string or object.');
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            throw new PluginException('JSON field must contain valid JSON.');
        }

        return $decoded;
    }
}
