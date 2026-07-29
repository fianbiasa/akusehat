<?php

namespace Tests\Unit\Services\RuleEngine;

use App\Services\RuleEngine\RuleEngineConditionEvaluator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RuleEngineConditionEvaluatorTest extends TestCase
{
    private RuleEngineConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new RuleEngineConditionEvaluator;
    }

    public function test_an_empty_condition_always_matches()
    {
        $this->assertTrue($this->evaluator->evaluate([], ['bmi' => 20]));
    }

    #[DataProvider('comparisonOperatorProvider')]
    public function test_comparison_operators(string $operator, mixed $expected, mixed $actual, bool $shouldMatch)
    {
        $result = $this->evaluator->evaluate(['value' => [$operator => $expected]], ['value' => $actual]);

        $this->assertSame($shouldMatch, $result);
    }

    public static function comparisonOperatorProvider(): array
    {
        return [
            '>= matches equal' => ['>=', 25, 25, true],
            '>= matches greater' => ['>=', 25, 27, true],
            '>= fails lesser' => ['>=', 25, 24, false],
            '<= matches equal' => ['<=', 25, 25, true],
            '<= fails greater' => ['<=', 25, 26, false],
            '> fails equal' => ['>', 25, 25, false],
            '> matches greater' => ['>', 25, 26, true],
            '< matches lesser' => ['<', 25, 24, true],
            '< fails equal' => ['<', 25, 25, false],
            '== matches' => ['==', 'male', 'male', true],
            '== fails' => ['==', 'male', 'female', false],
        ];
    }

    public function test_in_operator_on_a_scalar_field()
    {
        $condition = ['activity_level' => ['in' => ['sedentary', 'light']]];

        $this->assertTrue($this->evaluator->evaluate($condition, ['activity_level' => 'light']));
        $this->assertFalse($this->evaluator->evaluate($condition, ['activity_level' => 'heavy']));
    }

    public function test_in_operator_on_an_array_field_checks_for_overlap()
    {
        $condition = ['diseases' => ['in' => ['asam-urat']]];

        $this->assertTrue($this->evaluator->evaluate($condition, ['diseases' => ['hipertensi', 'asam-urat']]));
        $this->assertFalse($this->evaluator->evaluate($condition, ['diseases' => ['hipertensi']]));
    }

    public function test_and_requires_every_sub_condition()
    {
        $condition = ['and' => [['bmi' => ['>=' => 27]], ['activity_level' => ['in' => ['sedentary', 'light']]]]];

        $this->assertTrue($this->evaluator->evaluate($condition, ['bmi' => 28, 'activity_level' => 'light']));
        $this->assertFalse($this->evaluator->evaluate($condition, ['bmi' => 28, 'activity_level' => 'heavy']));
        $this->assertFalse($this->evaluator->evaluate($condition, ['bmi' => 20, 'activity_level' => 'light']));
    }

    public function test_or_requires_any_sub_condition()
    {
        $condition = ['or' => [['bmi' => ['>=' => 30]], ['diseases' => ['in' => ['hipertensi']]]]];

        $this->assertTrue($this->evaluator->evaluate($condition, ['bmi' => 32, 'diseases' => []]));
        $this->assertTrue($this->evaluator->evaluate($condition, ['bmi' => 20, 'diseases' => ['hipertensi']]));
        $this->assertFalse($this->evaluator->evaluate($condition, ['bmi' => 20, 'diseases' => []]));
    }

    public function test_not_negates_the_sub_condition()
    {
        $condition = ['not' => ['diseases' => ['in' => ['asam-urat']]]];

        $this->assertTrue($this->evaluator->evaluate($condition, ['diseases' => ['hipertensi']]));
        $this->assertFalse($this->evaluator->evaluate($condition, ['diseases' => ['asam-urat']]));
    }

    public function test_a_missing_context_field_never_matches()
    {
        $this->assertFalse($this->evaluator->evaluate(['bmi' => ['>=' => 25]], []));
        $this->assertFalse($this->evaluator->evaluate(['bmi' => ['>=' => 25]], ['bmi' => null]));
    }

    public function test_an_unknown_operator_throws()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->evaluator->evaluate(['bmi' => ['~=' => 25]], ['bmi' => 25]);
    }

    public function test_nested_and_or_not()
    {
        // Matches diabetics OR (overweight AND sedentary), but never smokers.
        $condition = [
            'and' => [
                ['not' => ['smoking_status' => ['==' => 'current']]],
                ['or' => [
                    ['diseases' => ['in' => ['diabetes-tipe-2']]],
                    ['and' => [['bmi' => ['>=' => 25]], ['activity_level' => ['==' => 'sedentary']]]],
                ]],
            ],
        ];

        $this->assertTrue($this->evaluator->evaluate($condition, [
            'smoking_status' => 'never', 'diseases' => ['diabetes-tipe-2'], 'bmi' => 20, 'activity_level' => 'heavy',
        ]));
        $this->assertTrue($this->evaluator->evaluate($condition, [
            'smoking_status' => 'never', 'diseases' => [], 'bmi' => 27, 'activity_level' => 'sedentary',
        ]));
        $this->assertFalse($this->evaluator->evaluate($condition, [
            'smoking_status' => 'current', 'diseases' => ['diabetes-tipe-2'], 'bmi' => 20, 'activity_level' => 'sedentary',
        ]));
        $this->assertFalse($this->evaluator->evaluate($condition, [
            'smoking_status' => 'never', 'diseases' => [], 'bmi' => 20, 'activity_level' => 'heavy',
        ]));
    }
}
