<?php

namespace App\Services\AI;

/**
 * A constrained subset of JSON Schema (type/required/properties/items) -
 * enough to validate the response_schema documents this project seeds
 * into ai_prompt_templates, without pulling in a general-purpose JSON
 * Schema package for features (oneOf, pattern, enum, ...) nothing here
 * uses. Same "constrained interpreter" philosophy as RuleEngineConditionEvaluator.
 */
class JsonSchemaValidator
{
    /**
     * @return array<int, string> validation error messages, empty if valid
     */
    public function validate(mixed $data, array $schema, string $path = '$'): array
    {
        $type = $schema['type'] ?? null;

        if ($type !== null && ! $this->matchesType($data, $type)) {
            return ["{$path}: expected type '{$type}', got '".$this->describeType($data)."'"];
        }

        $errors = [];

        if ($type === 'object' && is_array($data)) {
            foreach ($schema['required'] ?? [] as $requiredKey) {
                if (! array_key_exists($requiredKey, $data)) {
                    $errors[] = "{$path}: missing required property '{$requiredKey}'";
                }
            }

            foreach ($schema['properties'] ?? [] as $key => $propertySchema) {
                if (array_key_exists($key, $data)) {
                    $errors = [...$errors, ...$this->validate($data[$key], $propertySchema, "{$path}.{$key}")];
                }
            }
        }

        if ($type === 'array' && is_array($data) && isset($schema['items'])) {
            foreach ($data as $index => $item) {
                $errors = [...$errors, ...$this->validate($item, $schema['items'], "{$path}[{$index}]")];
            }
        }

        return $errors;
    }

    private function matchesType(mixed $data, string $type): bool
    {
        return match ($type) {
            'object' => is_array($data) && (empty($data) || ! array_is_list($data)),
            'array' => is_array($data) && (empty($data) || array_is_list($data)),
            'string' => is_string($data),
            'number' => is_int($data) || is_float($data),
            'integer' => is_int($data),
            'boolean' => is_bool($data),
            'null' => $data === null,
            default => true,
        };
    }

    private function describeType(mixed $data): string
    {
        if (is_array($data)) {
            return empty($data) || array_is_list($data) ? 'array' : 'object';
        }

        return get_debug_type($data);
    }
}
