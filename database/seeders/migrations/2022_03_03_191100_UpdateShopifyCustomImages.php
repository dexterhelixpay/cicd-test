<?php

use App\Models\OrderedProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class UpdateShopifyCustomImages_2022_03_03_191100 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            OrderedProduct::query()
                ->whereNotNull('shopify_custom_links')
                ->cursor()
                ->tapEach(function (OrderedProduct $orderedProduct) {
                    if (count($orderedProduct->shopify_custom_links) == 0) return;

                    $customLinks = collect($orderedProduct->shopify_custom_links)
                        ->map(function ($link) {
                            $path = Arr::has($link, 'path')
                                ? 'path'
                                : 'image_path';

                            return [
                                'path' => $link[$path],
                                'field_name' => $link['field_name'],
                                'label' => $link['label'],
                                'file_type' => shopify_link_file_type(
                                        Str::of($link[$path])->explode('.')->last()
                                    )
                            ];
                        });

                    $orderedProduct->forceFill([
                        'shopify_custom_links' => $customLinks
                    ])->update();

                    if ($subscribedProduct = $orderedProduct->subscribedProduct()->first()) {
                        $subscribedProduct->forceFill([
                            'shopify_custom_links' => $customLinks
                        ])->update();
                    }
                })->all();
        });
    }
}
