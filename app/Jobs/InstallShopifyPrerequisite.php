<?php

namespace App\Jobs;

use App\Libraries\Shopify\Rest\Metafield;
use App\Libraries\Shopify\Rest\Product as ShopifyProduct;
use App\Libraries\Shopify\Shopify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class InstallShopifyPrerequisite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The merchant model
     *
     * @var \App\Models\Merchant
     */
    protected $merchant;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($merchant)
    {
        $this->merchant = $merchant;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $shopify = new Shopify(
            $this->merchant->shopify_domain, $this->merchant->shopify_info['access_token']
        );

        $metafieldDefinitions = json_decode($shopify->getFrequencyMetafieldDefinitions(), true);

        $definitions = data_get($metafieldDefinitions, 'data.metafieldDefinitions.edges', null);

        if ($definitions) {
            $productDefinition = collect($definitions)
                ->filter(function ($definition) {
                    if (!Arr::has($definition, 'node.name')) return false;

                    return $definition['node']['name'] == 'Frequency';
                })->first();

            if ($productDefinition) {
                $this->merchant->forceFill([
                    'shopify_info' => array_merge(
                        $this->merchant->shopify_info ?? [],
                        ['metafield_definition_id' => data_get($productDefinition, 'node.id', null)]
                    )
                ])->update();
            } else {
                $this->createMetafieldDefinition($shopify);
            }
        } else {
            $this->createMetafieldDefinition($shopify);
        }

        $shopify->createSegment('Helixpay', "customer_tags = 'Helixpay'");
    }

    /**
     * Create product metafield definition
     *
     * @return void
     */
    protected function createMetafieldDefinition($shopify)
    {
        $createdMetafield = json_decode($shopify->createFrequencyMetafieldDefinition(), true);

        if ($definitionId = data_get($createdMetafield, 'data.metafieldDefinitionCreate.createdDefinition.id', null)) {
            $this->merchant->forceFill([
                'shopify_info' => array_merge(
                    $this->merchant->shopify_info ?? [],
                    ['metafield_definition_id' => $definitionId]
                )
            ])->update();
        }
    }
}
