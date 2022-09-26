<?php

use App\Models\Product;
use App\Models\Merchant;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\OrderedProduct;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use App\Models\ProductRecurrence;
use App\Models\SubscribedProduct;
use Illuminate\Support\Facades\DB;
use App\Models\ProductDescriptionItem;

class DeleteUnusedMerchants_2022_01_31_09170900 extends Seeder
{

    public $merchantIds = [115,170,172,174];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            Merchant::query()
                ->whereNotIn('id', $this->merchantIds)
                ->withTrashed()
                ->cursor()
                ->tapEach(function (Merchant $merchant)  {
                    $merchant->vouchers()->each(function($voucher) {
                        $voucher->usedVouchers()->delete();
                    });
                    $merchant->customers()->each(function($customer) {
                        $customer->cards()->forceDelete();
                        $customer->wallets()->forceDelete();
                    });
                    $merchant->orders()->each(function($order) {
                        $order->attachments()->detach();
                        $order->attemptLogs()->forceDelete();
                        $order->products()->forceDelete();
                    });
                    $merchant->products()->each(function($product) {
                        $product->allVariants()->each(function($variant) {
                            $variant->optionValues()->detach();
                        });
                        $product->allVariants()->forceDelete();
                        $product->groups()->detach();
                        $product->recurrences()->forceDelete();
                        $product->images()->forceDelete();
                        $product->items()->forceDelete();
                        $product->options()->each(function($option) {
                            $option->values()->forceDelete();
                        });
                        $product->options()->forceDelete();
                    });
                    $merchant->subscriptions()->each(function($subscription) {
                        $subscription->attachments()->forceDelete();
                        $subscription->products()->forceDelete();
                    });
                    $merchant->users()->forceDelete();
                    $merchant->vouchers()->forceDelete();
                    $merchant->paymentTypes()->detach();
                    $merchant->checkouts()->forceDelete();
                    $merchant->productGroups()->forceDelete();
                    $merchant->emailBlasts()->forceDelete();
                    $merchant->finances()->forceDelete();
                    $merchant->descriptionItems()->forceDelete();
                    $merchant->importBatches()->forceDelete();
                    $merchant->customFields()->forceDelete();
                    $merchant->subscriptionCustomFields()->forceDelete();
                    $merchant->recurrences()->forceDelete();
                    $merchant->shippingMethods()->forceDelete();
                    $merchant->customers()->forceDelete();
                    $merchant->orders()->forceDelete();
                    $merchant->products()->forceDelete();
                    $merchant->subscriptions()->forceDelete();
                    $merchant->forceDelete();
                })
                ->all();

            $productIds =  Product::whereIn('merchant_id', $this->merchantIds)
                ->pluck('id')
                ->toArray();
            ProductVariant::whereNotIn('product_id', $productIds)
                ->forceDelete();
            ProductImage::whereNotIn('product_id', $productIds)
                ->forceDelete();
            SubscribedProduct::whereNotIn('product_id', $productIds)
                ->forceDelete();
            OrderedProduct::whereNotIn('product_id', $productIds)
                ->forceDelete();
            ProductRecurrence::whereNotIn('product_id', $productIds)
                ->forceDelete();
            ProductDescriptionItem::whereNotIn('product_id', $productIds)
                ->forceDelete();
            ProductOption::query()
                ->whereNotIn('product_id', $productIds)
                ->cursor()
                ->tapEach(function (ProductOption $option)  {
                    $option->values()->forceDelete();
                    $option->forceDelete();
                })
                ->all();

        });
    }
}
