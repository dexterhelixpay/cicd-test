<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\PricingType;
use App\Models\Product;
use Faker\Provider\en_PH\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

class MerchantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Merchant::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $this->faker->addProvider(new PhoneNumber($this->faker));

        $username = $this->faker->unique()->userName;
        $verifiedAt = $this->faker->boolean(75)
            ? $this->faker->dateTime->format('Y-m-d H:i:s')
            : null;

        return [
            'pricing_type_id' => $this->faker->numberBetween(1, 2),

            'username' => $username,
            'email' => "{$username}@" . $this->faker->safeEmailDomain,
            'mobile_number' => mobile_number($this->faker->unique()->mobileNumber),
            'password' => bcrypt($this->faker->password()),

            'name' => $this->faker->company,
            'description_title' => 'Subscribe and Save!',
            'description_items' => [
                'Save money with subscriptions',
                'Early Access to promos for subscribers',
            ],

            'background_color' => $this->faker->safeHexColor,
            'highlight_color' => $this->faker->safeHexColor,
            'header_background_color' => $this->faker->safeHexColor,

            'website_url' => $this->faker->url,
            'instagram_handle' => $username,

            'card_discount' => $this->faker->boolean(75)
                ? $this->faker->numberBetween(1, 5) * 5
                : null,

            'has_api_access' => $this->faker->boolean,
            'has_shippable_products' => $this->faker->boolean,
            'is_enabled' => (bool) $verifiedAt,

            'verified_at' => $verifiedAt,
        ];
    }

    /**
     * Indicate that the user is suspended.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function verified()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_enabled' => true,
                'verified_at' => $this->faker->dateTime->format('Y-m-d H:i:s'),
            ];
        });
    }

    /**
     * Configure the factory.
     *
     * @return $this
     */
    public function configure()
    {
        return $this
            ->afterCreating(function (Merchant $merchant) {
                if ($merchant->verified_at) {
                    $merchant->products()->saveMany(
                        Product::factory($this->faker->numberBetween(1, 3))
                            ->visible()
                            ->make()
                    );
                }
            });
    }
}
