<?php

namespace App\Observers;

use App\Facades\Discord;
use App\Libraries\Shopify\Rest\Metafield;
use App\Models\Post;
use App\Models\ProductDeepLink;
use App\Models\ProductRecurrence;

class ProductObserver
{
    /**
     * Handle the product "creating" event.
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    public function creating($product)
    {
        $this->setShippableFlag($product);
    }

    /**
     * Handle the product "created" event.
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    public function created($product)
    {
        // $product->syncDefaultVariant();
        $this->createDiscordChannel($product);
        $this->attachDeepLink($product);
    }

    /**
     * Handle the product "updated" event.
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    public function updated($product)
    {
        // $this->cascadePriceToRecurrences($product);
        $this->attachDeepLink($product);
        $this->updateVariantTitle($product);
        $this->updateShippableStatus($product);
        $this->cascadeMembershipFlag($product);
    }

    /**
     * Handle the product "deleting" event.
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    public function deleting($product)
    {
        $this->deleteRelationships($product);
        $this->cleanUpMetafields($product);
    }

    /**
     * Attach Deep Link
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    protected function attachDeepLink($product)
    {
        if ($product->wasChanged('deep_link') || $product->wasRecentlyCreated) {
            $productDeepLink = ProductDeepLink::where('deep_link', $product->deep_link)->first();

            if ($productDeepLink) {
                $productDeepLink->update(['product_id' => $product->id]);
            }
        }
    }

    /**
     * Delete related models.
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    protected function deleteRelationships($product)
    {
        $product->allVariants()->get()->each->delete();
        $product->newVariants()->get()->each->delete();
        $product->options()->get()->each->delete();
        $product->properties()->get()->each->delete();
    }

    /**
     * Clean up shopify metafields
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    protected function cleanUpMetafields($product)
    {
        if (!$product->is_shopify_product || !$product->shopify_info) return;

        $metafield = collect($product->shopify_info['metafields'])->filter(function ($metafield) {
            return $metafield['key'] == 'recurrence';
        })->first();

        $metafieldId = data_get($metafield, 'legacyResourceId') ?? data_get($metafield, 'id');
        $productId = data_get($product->shopify_info, 'legacyResourceId') ?? $product->shopify_info['id'];

        if (!$metafieldId || !$productId) return;

        (new Metafield(
            $product->merchant->shopify_domain,
            $product->merchant->shopify_info['access_token']
        ))->delete(
            $productId,
            'products',
            $metafieldId
        )
        ->then(function ($data) {
        }, function ($e) {
        })
        ->wait(false);
    }

    /**
     * Set the product's shippable flag.
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    protected function setShippableFlag($product)
    {
        if ($merchant = $product->merchant()->first()) {
            $product->is_shippable = $merchant->has_shippable_products;
        }
    }

    /**
     * Cascade product prices to recurrences.
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    protected function cascadePriceToRecurrences($product)
    {
        if (!$product->wasChanged('original_price', 'price')) {
            return;
        }

        $product->recurrences()
            ->where('code', 'single')
            ->get()
            ->each
            ->update(function (ProductRecurrence $recurrence) use ($product) {
                $recurrence->update([
                    'original_price' => null,
                    'price' => $product->original_price ?? $product->price,
                ]);
            });

        $product->recurrences()
            ->where('code', '<>', 'single')
            ->get()
            ->each
            ->update(function (ProductRecurrence $recurrence) use ($product) {
                $recurrence->update([
                    'original_price' => $product->original_price,
                    'price' => $product->price,
                ]);
            });
    }

    /**
     * Create discord channel.
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    protected function createDiscordChannel($product)
    {
        if (!$product->merchant->discord_guild_id) {
            return;
        }

        $channels = Discord::guilds()
            ->channels($product->merchant->discord_guild_id)
            ->json();

        $category = collect($channels)->where('name', 'Products')->first();
        $role = Discord::guilds()
            ->addRole(
                $product->merchant->discord_guild_id,
                $product->slug
            );

        $channel = Discord::guilds()
            ->addChannel(
                $product->merchant->discord_guild_id,
                Discord::GUILD_TEXT,
                $product->slug,
                data_get($category, 'id'),
                data_get($role, 'id')
            );

        $product->fill([
                'is_discord_invite_enabled' => true,
                'discord_channel' => data_get($channel, 'name'),
                'discord_role_id' => data_get($role, 'id')
            ])
            ->saveQuietly();
    }

    /**
     * Update the title on the given product's variants.
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    protected function updateVariantTitle($product)
    {
        if ($product->wasChanged('title')) {
            $product->newVariants()->update($product->only('title'));
        }
    }

    /**
     * Update the shippable status on the given product's variants.
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    protected function updateShippableStatus($product)
    {
        if ($product->wasChanged('is_shippable')) {
            $product->variants()->update($product->only('is_shippable'));
        }
    }

    /**
     * Cascade membership flag to current subscriptions.
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    protected function cascadeMembershipFlag($product)
    {
        if (!$product->wasChanged('is_membership')) {
            return;
        }

        $product->subscribedProducts()
            ->whereHas('subscription', function ($query) {
                $query->whereNull('completed_at')->whereNull('cancelled_at');
            })
            ->update(['is_membership' => $product->is_membership]);

        $product->orderedProducts()
            ->whereHas('order.subscription', function ($query) {
                $query->whereNull('completed_at')->whereNull('cancelled_at');
            })
            ->update(['is_membership' => $product->is_membership]);

        Post::flushQueryCache();
    }
}
