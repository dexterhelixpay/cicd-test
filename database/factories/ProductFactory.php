<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title' => $this->faker->catchPhrase,
            'description' => $this->faker->paragraph,
            'price' => $this->faker->boolean(75)
                ? ($this->faker->numberBetween(1, 5) * 500) - 1
                : null,
            'are_multiple_orders_allowed' => $this->faker->boolean(25),
            'is_visible' => $this->faker->boolean,
        ];
    }

    /**
     * Indicate that the merchant is visible.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function visible()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_visible' => true,
            ];
        });
    }
}
