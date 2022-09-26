<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\PaymentType;
use App\Models\ShippingMethod;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
{
        $frequency = $this->faker->boolean(25)
            ? $this->faker->randomElement([3, 5, 7])
            : $this->faker->randomElement(['weekly', 'semimonthly', 'monthly']);

        $price = ($this->faker->numberBetween(1, 5) * 500) - 1;

        $merchant = Merchant::query()
            ->whereNotNull('verified_at')
            ->inRandomOrder()
            ->first();

        $customer = $merchant->customers()->save(
            Customer::factory()->makeOne()
        );

        return [
            'merchant_id' => $merchant->getKey(),
            'customer_id' => $customer->getKey(),
            'payment_type_id' => PaymentType::inRandomOrder()->value('id'),
            'shipping_method_id' => $merchant->has_shippable_products
                ? ShippingMethod::inRandomOrder()->value('id')
                : null,

            'payment_schedule' => collect([
                'frequency' => $frequency,
                'day_of_week' => $frequency === 'weekly'
                    ? $this->faker->numberBetween(0, 6)
                    : null,
                'days' => $frequency === 'semimonthly'
                    ? $this->faker->randomElements(range(1, 30), 2)
                    : null,
                'day' => $frequency === 'monthly'
                    ? $this->faker->numberBetween(1, 30)
                    : null,
            ])->filter()->all(),

            'total_price' => $price,

            'payor' => $customer->name,
            'billing_address' => $customer->address,
            'billing_province' => $customer->province,
            'billing_city' => $customer->city,
            'billing_barangay' => $customer->barangay,
            'billing_zip_code' => $customer->zip_code,

            'recipient' => $merchant->has_shippable_products
                ? $customer->name
                : null,
            'shipping_address' => $merchant->has_shippable_products
                ? $customer->address
                : null,
            'shipping_province' => $merchant->has_shippable_products
                ? $customer->province
                : null,
            'shipping_city' => $merchant->has_shippable_products
                ? $customer->city
                : null,
            'shipping_barangay' => $merchant->has_shippable_products
                ? $customer->barangay
                : null,
            'shipping_zip_code' => $merchant->has_shippable_products
                ? $customer->zip_code
                : null,

            'total_amount_paid' => $this->faker->boolean(75) ? $price : 0,

            'reference_id' => $this->faker->boolean
                ? mb_strtoupper($this->faker->bothify('????????####'))
                : null,
        ];
    }

    /**
     * Configure the factory.
     *
     * @return $this
     */
    public function configure()
    {
        return $this
            ->afterCreating(function (Subscription $subscription) {
                $product = $subscription->merchant->products()->inRandomOrder()->first();

                $subscription->products()->create(
                    [
                        'product_id' => $product->getKey(),
                        'quantity' => 1,
                        'total_price' => $product->price,
                    ] + $product->only([
                        'title',
                        'description',
                        'price',
                        'are_multiple_orders_allowed',
                    ])
                );

                $order = $subscription->orders()->save(
                    Order::factory()->makeOne()
                );

                $order->products()->create(
                    [
                        'product_id' => $product->getKey(),
                        'quantity' => 1,
                        'total_price' => $product->price,
                    ] + $product->only([
                        'title',
                        'description',
                        'price',
                        'are_multiple_orders_allowed',
                    ])
                );
            });
    }
}
