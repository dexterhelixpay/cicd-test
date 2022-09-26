<?php

use App\Models\Merchant;
use Illuminate\Database\Seeder;

class SetDefaultFontSettings_2022_04_01_155000 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $fontSettings = [
            'storefront_description' => [
                    'font-family'=> null,
                    'font-size'=> null,
                    'font-weight'=> null,
                    'line-height' => null,
                    'mobile-font-size' => null
            ],
                'storefront_description_items'=> [
                    'font-family'=> null,
                    'font-size'=> null,
                    'font-weight'=> null,
                    'line-height' => null,
                    'mobile-font-size' => null
            ],
                'membership_description'=> [
                    'font-family'=> null,
                    'font-size'=> null,
                    'font-weight'=> null,
                    'line-height' => null,
                    'mobile-font-size' => null
            ],
                'membership_description_items'=> [
                    'font-family'=> null,
                    'font-size'=> null,
                    'font-weight'=> null,
                    'line-height' => null,
                    'mobile-font-size' => null
            ],
                'product_title'=> [
                    'font-family'=> null,
                    'font-size'=> null,
                    'font-weight'=> null,
                    'line-height' => null,
                    'mobile-font-size' => null
            ],
                'product_body'=> [
                    'font-family'=> null,
                    'font-size'=> null,
                    'font-weight'=> null,
                    'line-height' => null,
                    'mobile-font-size' => null
            ],
                'product_price'=> [
                    'font-family'=> null,
                    'font-size'=> null,
                    'font-weight'=> null,
                    'line-height' => null,
                    'mobile-font-size' => null
            ],
                'product_group_tab'=> [
                    'font-family'=> null,
                    'font-size'=> null,
                    'font-weight'=> null,
                    'line-height' => null,
                    'mobile-font-size' => null
            ],
                'faq_link'=> [
                    'font-family'=> null,
                    'font-size'=> null,
                    'font-weight'=> null,
                    'line-height' => null,
                    'mobile-font-size' => null
            ],
        ];

        Merchant::query()
            ->where('is_enabled', true)
            ->cursor()
            ->tapEach(function (Merchant $merchant) use($fontSettings) {
                $merchant->forceFill([
                    'font_settings' => $fontSettings
                ])->update();
            })->all();
    }
}
