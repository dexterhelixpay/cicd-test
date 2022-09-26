<?php

namespace App\Libraries\Shopify;

class ProductVariant extends Api
{
    /**
     * Get product variants using the given IDs.
     *
     * @param  string|array  $id
     * @return \Illuminate\Http\Client\Response
     */
    public function getById($id)
    {
        $queries = collect($id)->map(function ($id, $index) {
            $id = "\"gid://shopify/ProductVariant/{$id}\"";

            return "productVariant{$index}: productVariant(id: {$id}) {
                id
                compareAtPrice
                displayName
                inventoryItem {
                    requiresShipping
                }
                legacyResourceId
                price
                product {
                    id
                    descriptionHtml
                    images(first: 2) {
                        edges {
                            node {
                                id
                                url
                            }
                        }
                    }
                    legacyResourceId
                    title
                }
                selectedOptions {
                    name
                    value
                }
                sku
                title
            }";
        })->join(' ');

        return $this->client()->post('admin/api/2022-04/graphql.json', [
            'query' => "query { {$queries} }",
        ]);
    }

    /**
     * Find the given variant's inventory.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Client\Response
     */
    public function findInventory(string $id)
    {
        return $this->client()->post('admin/api/2022-04/graphql.json', [
            'query' => 'query ($id: ID!) {
                productVariant(id: $id) {
                    inventoryItem {
                        id
                        tracked
                        inventoryLevels(first: 10) {
                            edges {
                                node {
                                    available
                                }
                            }
                        }
                    }
                }
            }',
            'variables' => [
                'id' => "gid://shopify/ProductVariant/{$id}",
            ],
        ]);
    }
}
