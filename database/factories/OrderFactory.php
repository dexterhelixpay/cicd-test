<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\PaymentType;
use Faker\Provider\en_PH\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $this->faker->addProvider(new Address($this->faker));

        return [
            'payment_type_id' => PaymentType::inRandomOrder()->value('id'),
            'payment_status_id' => 1,

            'total_price' => ($this->faker->numberBetween(1, 5) * 500) - 1,

            'payor' => $this->faker->name,
            'billing_date' => now()->addDays(30)->toDateString(),
            'billing_address' => $this->faker->address,
            'billing_city' => $this->faker->city,
            'billing_province' => $this->faker->province,
            'billing_barangay' => $this->faker->barangay,
            'billing_zip_code' => $this->faker->postcode,

            'recipient' => $this->faker->name,
            'shipping_date' => now()->addDays($this->faker->numberBetween(1, 7))->toDateString(),
            'shipping_address' => $this->faker->address,
            'shipping_city' => $this->faker->city,
            'shipping_province' => $this->faker->province,
            'shipping_barangay' => $this->faker->barangay,
            'shipping_zip_code' => $this->faker->postcode,

            'order_number' => $this->faker->numberBetween(1),
        ];
    }
}
