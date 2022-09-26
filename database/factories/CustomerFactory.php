<?php

namespace Database\Factories;

use App\Models\Customer;
use Faker\Provider\en_PH\Address;
use Faker\Provider\en_PH\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $this->faker->addProvider(new Address($this->faker));
        $this->faker->addProvider(new PhoneNumber($this->faker));

        return [
            'name' => $this->faker->name,
            'email' => $this->faker->safeEmail,
            'mobile_number' => mobile_number($this->faker->mobileNumber),
            'address' => $this->faker->address,
            'province' => $this->faker->province,
            'city' => $this->faker->city,
            'barangay' => $this->faker->barangay,
            'zip_code' => $this->faker->postcode,
        ];
    }
}
