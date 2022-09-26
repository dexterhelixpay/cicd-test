<?php

namespace App\Libraries\Shopify;

class Product extends Api
{
    /**
     * Get products using the given IDs.
     *
     * @param  string|array  $id
     * @return \Illuminate\Http\Client\Response
     */
    public function getById($id)
    {
        $queries = collect($id)->map(function ($id, $index) {
            $id = "\"gid://shopify/Product/{$id}\"";

            return "product{$index}: product(id: {$id}) {
                id
                descriptionHtml
                hasOnlyDefaultVariant
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
                variants(first: 5) {
                    edges {
                        node {
                            id
                            compareAtPrice
                            displayName
                            inventoryItem {
                                requiresShipping
                            }
                            legacyResourceId
                            price
                            sku
                            title
                        }
                    }
                }
            }";
        })->join(' ');

        return $this->client()->post('admin/api/2022-04/graphql.json', [
            'query' => "query { {$queries} }",
        ]);
    }
}
