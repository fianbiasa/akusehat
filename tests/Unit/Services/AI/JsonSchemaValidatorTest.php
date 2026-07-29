<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

class JsonSchemaValidatorTest extends TestCase
{
    private JsonSchemaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new JsonSchemaValidator;
    }

    public function test_valid_data_produces_no_errors()
    {
        $schema = ['type' => 'object', 'required' => ['summary'], 'properties' => ['summary' => ['type' => 'string']]];

        $this->assertSame([], $this->validator->validate(['summary' => 'ok'], $schema));
    }

    public function test_a_missing_required_property_is_an_error()
    {
        $schema = ['type' => 'object', 'required' => ['summary', 'trend'], 'properties' => ['summary' => ['type' => 'string']]];

        $errors = $this->validator->validate(['summary' => 'ok'], $schema);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('trend', $errors[0]);
    }

    public function test_a_type_mismatch_is_an_error()
    {
        $schema = ['type' => 'object', 'properties' => ['calories' => ['type' => 'number']]];

        $errors = $this->validator->validate(['calories' => 'a lot'], $schema);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('calories', $errors[0]);
    }

    public function test_nested_array_of_objects_is_validated_recursively()
    {
        $schema = [
            'type' => 'object',
            'required' => ['meal_plan'],
            'properties' => [
                'meal_plan' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'required' => ['meal_type', 'calories'],
                    'properties' => ['meal_type' => ['type' => 'string'], 'calories' => ['type' => 'number']],
                ]],
            ],
        ];

        $errors = $this->validator->validate([
            'meal_plan' => [
                ['meal_type' => 'breakfast', 'calories' => 400],
                ['meal_type' => 'lunch'], // missing calories
            ],
        ], $schema);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('meal_plan[1]', $errors[0]);
        $this->assertStringContainsString('calories', $errors[0]);
    }

    public function test_an_empty_schema_type_check_is_skipped()
    {
        $this->assertSame([], $this->validator->validate('anything at all', []));
    }

    public function test_distinguishes_json_objects_from_json_arrays()
    {
        $schema = ['type' => 'object'];

        $this->assertSame([], $this->validator->validate(['key' => 'value'], $schema));
        $this->assertNotEmpty($this->validator->validate(['a', 'b', 'c'], $schema));
    }
}
