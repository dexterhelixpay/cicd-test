<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\PricingType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MerchantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (!app()->isProduction()) {
            DB::transaction(function () {
                $dbDisk = Storage::disk('database');

                $this->getTestMerchants()
                    ->each(function ($data) use ($dbDisk) {
                        $merchant = Merchant::make()
                            ->forceFill(Arr::except($data, 'products'))
                            ->forceFill([
                                'password' => bcrypt('demo1234'),

                                'is_enabled' => true,
                                'has_api_access' => true,

                                'verified_at' => now(),
                            ]);

                        $merchant->uploadLogo(
                            file_get_contents(database_path("seeders/{$data['logo_image_path']}"))
                        );

                        $merchant->saveQuietly();

                        if ($products = $data['products'] ?? false) {
                            $products->each(function ($data, $index) use ($merchant, $dbDisk) {
                                $product = $merchant->products()->create($data);
                                $index++;

                                $imagePath = "seeders/images/merchants/{$merchant->username}/{$index}.png";

                                if ($dbDisk->exists($imagePath)) {
                                    $productImage = $product->images()
                                        ->make(['sort_number' => 1])
                                        ->uploadImage(
                                            file_get_contents(database_path($imagePath))
                                        );

                                    $productImage->saveQuietly();
                                }
                            });
                        }
                    });
            });
        }
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    protected function getTestMerchants()
    {
        return collect([
            [
                'pricing_type_id' => PricingType::FIXED_PRICING,

                'username' => 'brooklyn',
                'email' => 'b99waffles@gmail.com',

                'name' => 'Brooklyn 99 Waffles',
                'subdomain' => 'brooklyn',
                'description_title' => 'The Brooklyn 99 Waffle Story',

                'logo_image_path' => 'images/merchants/brooklyn.png',
                'header_background_color' => '#FAEAE2',
                'background_color' => '#F8A982',
                'highlight_color' => '#894841',

                'website_url' => 'https://www.b99waffles.com/',
                'instagram_handle' => '@b99waffles',

                'card_discount' => 10,

                'has_shippable_products' => true,

                'faqs' => '<h1><b>Will customers need a BukoPay account to complete their subscription purchase?
                    </b></h1> <br><p>Nope! We’re taking away the hassle! Customers simply enter their address
                    information and payment details and would not be required to sign up and set up a password.</p><br><h1><b>What is the
                    customer journey for Payments and Reminders?</b></h1> <br><p>For payment, customers would have an option between
                    auto-charge and auto-reminder payment options. We highly encourage merchants to provide an extra discount for auto-charge
                    orders as cancellations of subscriptions under auto-charge are less likely to happen compared to subscriptions under auto-reminder.
                    Email/sms messages for both would be triggered three days prior to the payment date with a reminder message and payment details nicely
                    summarized. Customers can view their subscription details, edit their order, change their payment method, and, if needed, skip or cancel
                    their subscription.</p><br><h1><b>How can customers see their subscription information and edit/cancel their subscriptions?</b></h1> <br><p>BukoPay
                    automatically updates customers via emails and SMS to confirm their initial subscription order, to remind them about upcoming payments
                    and to confirm when payments are made. These emails are "dynamic" with links to edit, cancel, or skip their subscriptions right
                    there in the messages. They can simply click to cancel anytime they want and the merchant will be notified.</p><br><h1><b>How will
                    customer input the shipping region and payment method?</b></h1> <br><p>Merchants can set their shipping fees for their BukoPay storefront
                    so that this will be automatically added to the payment transactions. Merchants can select different shipping fees depending on if the customer
                    is in Metro Manila or in the provinces. Regarding the payment method, customers can choose to pay using a credit card, digital wallet,
                    or bank transfer.</p>',

                'faqs_title' => 'Learn more how subscriptions work',

                'products' => collect([
                    [
                        'title' => 'Belgian Waffles',
                        'description' => 'Belgian Waffles with a crispy exterior and light fluffy interior.',
                        'price' => 180,

                        'are_multiple_orders_allowed' => true,
                    ],
                    [
                        'title' => 'Berry Waffles',
                        'description' => 'Fluffy on the inside and full of buttery vanilla flavor!',
                        'price' => 300,

                        'are_multiple_orders_allowed' => true,
                    ],
                    [
                        'title' => 'Chocolate Waffles',
                        'description' => 'Topped with homemade chocolate sauce, they are perfect for a special breakfast treat.',
                        'price' => 180,

                        'are_multiple_orders_allowed' => true,
                    ],
                    [
                        'title' => 'Peanut Butter Waffles',
                        'description' => 'These tasty waffles are a great way to add a little more protein to your breakfast!',
                        'price' => 300,

                        'are_multiple_orders_allowed' => true,
                    ],
                    [
                        'title' => 'Cookies and Cream Waffles',
                        'description' => 'Waffles with crushed Oreo™ cookie pieces mixed in! Top with whipped cream and even more Oreo™ pieces for the ultimate cookies and cream-inspired breakfast',
                        'price' => 310,

                        'are_multiple_orders_allowed' => true,
                    ],
                ])
            ],

            [
                'pricing_type_id' => PricingType::VARIABLE_PRICING,

                'username' => 'meralco',
                'email' => 'meralco@gmail.com',

                'name' => 'Meralco Corporation',
                'subdomain' => 'meralco',
                'description_title' => 'About Us',

                'logo_image_path' => 'images/merchants/meralco.png',
                'header_background_color' => '#FC7019',
                'background_color' => '#FCF4E6',
                'highlight_color' => '#963F2D',

                'website_url' => 'https://www.meralco.com.ph/',
                'instagram_handle' => '@meralcoph',

                'has_shippable_products' => false,

                'products' => collect([
                    [
                        'title' => 'Monthly Consumption',
                        'description' => 'Monthly Bills',

                        'are_multiple_orders_allowed' => false,
                    ],
                    [
                        'title' => 'Kuryente Load',
                        'description' => 'Prepaid Electricity Service',

                        'are_multiple_orders_allowed' => false,
                    ],
                ]),
            ],

            [
                'pricing_type_id' => PricingType::VARIABLE_PRICING,

                'username' => 'tendopay',
                'email' => 'tendopay@gmail.com',

                'name' => 'TendoPay',
                'subdomain' => 'tendopay',
                'description_title' => 'About Us',

                'logo_image_path' => 'images/merchants/tendopay.png',
                'header_background_color' => '#25A5F1',
                'background_color' => '#25A5F1',
                'highlight_color' => '#00FFC4',

                'website_url' => 'https://tendopay.ph/',
                'instagram_handle' => '@tendopay',

                'has_shippable_products' => false,
            ],
        ]);
    }
}
