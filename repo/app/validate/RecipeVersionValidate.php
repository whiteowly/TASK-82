<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

class RecipeVersionValidate extends Validate
{
    private const VALID_UNITS = ['g', 'kg', 'ml', 'l', 'tsp', 'tbsp', 'piece', 'pcs'];

    protected $rule = [
        'total_time'  => 'require|integer|between:1,720',
        'difficulty'  => 'in:easy,medium,hard',
        'steps'       => 'array|min:1|max:50',
        'ingredients' => 'array|min:1',
    ];

    protected $message = [
        'total_time.require' => 'Total time is required',
        'total_time.integer' => 'Total time must be an integer',
        'total_time.between' => 'Total time must be between 1 and 720 minutes',
        'difficulty.in'      => 'Difficulty must be one of: easy, medium, hard',
        'steps.array'        => 'Steps must be an array',
        'steps.min'          => 'At least 1 step is required',
        'steps.max'          => 'Maximum 50 steps allowed',
        'ingredients.array'  => 'Ingredients must be an array',
        'ingredients.min'    => 'At least 1 ingredient is required',
    ];

    /**
     * Validate individual ingredient entries after standard rule checks.
     * Called from the controller when ingredients are present.
     */
    public static function validateIngredients(array $ingredients): ?string
    {
        foreach ($ingredients as $i => $ing) {
            if (empty($ing['name'])) {
                return "Ingredient #" . ($i + 1) . ": name is required";
            }
            if (!isset($ing['quantity']) || !is_numeric($ing['quantity']) || (float)$ing['quantity'] <= 0) {
                return "Ingredient #" . ($i + 1) . ": quantity must be a number greater than 0";
            }
            if (empty($ing['unit']) || !in_array($ing['unit'], self::VALID_UNITS, true)) {
                return "Ingredient #" . ($i + 1) . ": unit must be one of: " . implode(', ', self::VALID_UNITS);
            }
        }
        return null;
    }
}
