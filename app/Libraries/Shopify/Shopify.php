<?php

namespace App\Libraries\Shopify;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Shopify
{
    /**
     * The Shopify HTTP client
     *
     * @var \Illuminate\Http\Client\PendingRequest
     */
    protected $client;

    /**
     * Create a new Shopify instance.
     *
     * @param  string  $shopUrl
     * @param  string  $authToken
     * @return void
     */
    public function __construct($shopUrl, $authToken)
    {
        $this->client = Http::asJson()
            ->baseUrl($this->prependScheme($shopUrl))
            ->withHeaders([
                'Accepts' => 'application/json',
                'X-Shopify-Access-Token' => $authToken,
            ]);
    }

    /**
     * Convert given data to JSONL file.
     *
     * @param  array  $data
     * @return string
     */
    public static function toJsonl($data)
    {
        $result = collect($data)->reduce(function ($result, $datum) {
            return $result . json_encode($datum) . "\n";
        }, '');

        return trim($result);
    }

    /**
     * Parse the given product JSONL data.
     *
     * @param  string  $data
     * @return array
     */
    public static function parseProductJsonl($data)
    {
        $wrapped = '[' . rtrim(str_replace(["\r\n", "\r", "\n"], ',', $data), ',') . ']';
        $decoded = json_decode($wrapped, true);

        $products = [];

        foreach ($decoded as $data) {
            if (!Arr::has($data, '__parentId')) {
                array_push($products, $data);
                continue;
            }

            if (!preg_match('/^gid:\/\/shopify\/([A-Za-z]+)/', $data['id'], $matches)) {
                continue;
            }

            $collectionOnlyKeys = [
                'id',
                'legacyResourceId',
                '__parentId'
            ];

            $isParentCollection = Arr::has($data, $collectionOnlyKeys)
                && count(Arr::except($data, $collectionOnlyKeys)) === 0;

            switch ($matches[1]) {
                case 'Metafield':
                    $key = 'metafields';
                    break;

                case 'ProductImage':
                    $key = 'images';
                    break;

                case 'ProductVariant':
                    $key = 'variants';
                    break;

                case 'Collection':
                    $key = 'collections';
                    break;

                case 'Product':
                    if ($isParentCollection) {
                        $key = 'products';
                    } else {
                        continue 2;
                    }
                break;
            }

            foreach ($products as &$product) {
                if ($data['__parentId'] === $product['id']) {
                    $product[$key] = data_get($product, $key, []);
                    array_push($product[$key], $data);

                    break;
                }
            }
        }

        return $products;
    }


    /**
     * Create a customer
     *
     * @return \Illuminate\Http\Client\Response
     */
    public function updateCustomer($customer)
    {
        $name = Str::splitName($customer->name, false);

        return $this->client->post('admin/api/2022-01/graphql.json', [
            'query' => 'mutation ($input: CustomerInput!) {
                customerUpdate (input: $input) {
                    customer {
                       legacyResourceId
                    }
                    userErrors {
                      field
                      message
                    }
                }
            }',
            'variables' => [
                'input' => [
                    'id' => "gid://shopify/Customer/{$customer->shopify_id}",
                    'addresses' => [
                        'address1' => $customer->address,
                        'city' => $customer->city,
                        'firstName' => $name['firstName'],
                        'lastName' => $name['lastName'],
                        'phone' => $customer->mobile_number,
                        'province' => $customer->province,
                        'country' => $customer->country_name,
                        'zip' => $customer->zip_code
                    ],
                    'email' => $customer->email,
                    'firstName' => $name['firstName'],
                    'lastName' => $name['lastName']
                ]
            ],
        ]);
    }


    /**
     * Create a segment
     *
     * @param string $id
     * @param array|string $tag
     *
     * @return \Illuminate\Http\Client\Response
     */
    public function addTag($id, $tags)
    {
        return $this->client->post('admin/api/2022-01/graphql.json', [
            'query' => 'mutation ($id: ID!, $tags: [String!]!) {
                tagsAdd (id: $id, tags: $tags) {
                    node {
                       id
                    }
                    userErrors {
                      field
                      message
                    }
                }
            }',
            'variables' => [
                'id' => $id,
                'tags' => $tags
            ],
        ]);
    }

    /**
     * Create a segment
     *
     * @param string $name
     * @param string $query
     *
     * @return \Illuminate\Http\Client\Response
     */
    public function createSegment($name, $query)
    {
        return $this->client->post('admin/api/2022-04/graphql.json', [
            'query' => 'mutation ($name: String!, $query: String!) {
                segmentCreate (name: $name, query: $query) {
                    segment {
                       id
                    }
                    userErrors {
                      field
                      message
                    }
                }
            }',
            'variables' => [
                'name' => $name,
                'query' => $query
            ],
        ]);
    }

    /**
     * Create a customer
     *
     * @return \Illuminate\Http\Client\Response
     */
    public function createCustomer($customer)
    {
        $name = Str::splitName($customer->name, false);

        return $this->client->post('admin/api/2022-01/graphql.json', [
            'query' => 'mutation ($input: CustomerInput!) {
                customerCreate (input: $input) {
                    customer {
                       legacyResourceId
                    }
                    userErrors {
                      field
                      message
                    }
                }
            }',
            'variables' => [
                'input' => [
                    'tags' => ['HelixPay'],
                    'addresses' => [
                        'address1' => $customer->address,
                        'city' => $customer->city,
                        'firstName' => $name['firstName'],
                        'lastName' => $name['lastName'],
                        'phone' => $customer->mobile_number,
                        'province' => $customer->province,
                        'country' => $customer->country_name,
                        'zip' => $customer->zip_code
                    ],
                    'email' => $customer->email,
                    'firstName' => $name['firstName'],
                    'lastName' => $name['lastName']
                ]
            ],
        ]);
    }

    /**
     * Delete a metafield definition for frequency.
     *
     * @return \Illuminate\Http\Client\Response
     */
    public function deleteFrequencyMetafieldDefinition($id)
    {
        return $this->client->post('admin/api/2022-01/graphql.json', [
            'query' => 'mutation ($id: ID!, $deleteAllAssociatedMetafields: Boolean!) {
                metafieldDefinitionDelete (id: $id, deleteAllAssociatedMetafields: $deleteAllAssociatedMetafields) {
                    deletedDefinitionId
                    userErrors {
                      field
                      message
                      code
                    }
                }
            }',
            'variables' => [
                'id' => $id,
                'deleteAllAssociatedMetafields' => true,
            ],
        ]);
    }

    /**
     * Get metafield definitions.
     *
     * @return \Illuminate\Http\Client\Response
     */
    public function getFrequencyMetafieldDefinitions()
    {
        return $this->client->post('admin/api/2022-01/graphql.json', [
            'query' => 'query {
                metafieldDefinitions(first: 250, ownerType: PRODUCT) {
                    edges {
                        node {
                          id
                          name
                        }
                    }
                }
            }'
        ]);
    }

    /**
     * Create a metafield definition for frequency.
     *
     * @return \Illuminate\Http\Client\Response
     */
    public function createFrequencyMetafieldDefinition()
    {
        return $this->client->post('admin/api/2022-01/graphql.json', [
            'query' => 'mutation ($definition: MetafieldDefinitionInput!) {
                metafieldDefinitionCreate (definition: $definition) {
                    createdDefinition {
                        id,
                        ownerType
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }',
            'variables' => [
                'definition' => [
                    'name' => 'Frequency',
                    'namespace' => 'bukopay',
                    'key' => 'recurrence',
                    'type' => 'json',
                    'description' => 'Available Frequency',
                    'ownerType' => 'PRODUCT',
                ],
            ],
        ]);
    }

    /**
     * Bulk mutate objects from the uploaded JSONL file.
     *
     * @param  string  $mutation
     * @param  string  $uploadPath
     * @return \Illuminate\Http\Client\Response
     */
    public function bulkMutate($mutation, $uploadPath)
    {
        return $this->client->post('admin/api/2022-01/graphql.json', [
            'query' => 'mutation ($mutation: String!, $uploadPath: String!) {
                bulkOperationRunMutation(
                    mutation: $mutation,
                    stagedUploadPath: $uploadPath
                ) {
                    bulkOperation {
                        id
                        url
                        status
                    }
                    userErrors {
                        message
                        field
                    }
                }
            }',
            'variables' => [
                'mutation' => $mutation,
                'uploadPath' => $uploadPath,
            ],
        ]);
    }

    /**
     * Create a webhook for bulk operations.
     *
     * @return \Illuminate\Http\Client\Response
     */
    public function createBulkOperationsWebhook()
    {
        return $this->client->post('admin/api/2022-01/graphql.json', [
            'query' => 'mutation ($callbackUrl: URL) {
                webhookSubscriptionCreate(
                    topic: BULK_OPERATIONS_FINISH
                    webhookSubscription: {
                        format: JSON,
                        callbackUrl: $callbackUrl
                    }
                ) {
                    userErrors {
                        field
                        message
                    }
                    webhookSubscription {
                        id
                    }
                }
            }',
            'variables' => [
                'callbackUrl' => route('api.v1.shopify.capture'),
            ],
        ]);
    }

    /**
     * Get the bulk operation using the given ID.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Client\Response
     */
    public function getBulkOperation($id)
    {
        return $this->client->post('admin/api/2022-01/graphql.json', [
            'query' => 'query ($id: ID!) {
                node(id: $id) {
                    ... on BulkOperation {
                        url
                        partialDataUrl
                    }
                }
            }',
            'variables' => [
                'id' => $id,
            ],
        ]);
    }

    /**
     * Get all products via collection
     *
     * @return \Illuminate\Http\Client\Response
     */
    public function getAllProductsViaCollection()
    {
        return $this->client->post('admin/api/2022-01/graphql.json', [
            'query' => 'mutation {
                bulkOperationRunQuery(query: """{
                    collections {
                      edges {
                          node {
                              id
                              legacyResourceId
                              products {
                                  edges {
                                      node {
                                          id
                                          legacyResourceId
                                      }
                                  }
                              }
                          }
                      }
                    }
                }""") {
                    bulkOperation {
                        id
                        status
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }'
        ]);
    }

    /**
     * Get total product quantity via product variant
     *
     * @return \Illuminate\Http\Client\Response
     */
    public function getVariantInventory($id)
    {
        return $this->client->post('admin/api/2022-01/graphql.json', [
            'query' => 'query ($id: ID!) {
                productVariant(id: $id) {
                    inventoryItem {
                      id
                      tracked
                      inventoryLevels(first: 10) {
                        edges {
                          node {
                            location {
                              id
                              name
                            }
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

    /**
     * Get all products from Shopify.
     *
     * @return \Illuminate\Http\Client\Response
     */
    public function getAllProducts()
    {
        return $this->client->post('admin/api/2022-01/graphql.json', [
            'query' => 'mutation {
                bulkOperationRunQuery(query: """{
                    products(query: "status:ACTIVE") {
                        edges {
                            node {
                                id
                                legacyResourceId
                                title
                                descriptionHtml
                                images {
                                    edges {
                                        node {
                                            id
                                            url
                                        }
                                    }
                                }
                                metafields {
                                    edges {
                                        node {
                                            id
                                            legacyResourceId
                                            key
                                            namespace
                                            ownerType
                                            type
                                            value
                                            definition {
                                                description
                                                name
                                            }
                                        }
                                    }
                                }
                                options {
                                    id
                                    name
                                    position
                                    values
                                }
                                variants {
                                    edges {
                                        node {
                                            compareAtPrice
                                            id
                                            legacyResourceId
                                            price
                                            selectedOptions {
                                                name
                                                value
                                            }
                                            sku
                                            title
                                            inventoryItem {
                                                requiresShipping
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }""") {
                    bulkOperation {
                        id
                        status
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }',
        ]);
    }

    /**
     * Upload the given file to Shopify.
     *
     * @param  string  $file
     * @return \Illuminate\Http\Client\Response
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function uploadFile($file)
    {
        $filename = Str::random();

        $reserveResponse = $this->client->post('admin/api/2022-01/graphql.json', [
            'query' => 'mutation ($input: [StagedUploadInput!]!) {
                stagedUploadsCreate(input: $input) {
                    userErrors{
                        field,
                        message
                    },
                    stagedTargets {
                        resourceUrl,
                        url,
                        parameters {
                            name,
                            value
                        }
                    }
                }
            }',
            'variables' => [
                'input' => [
                    'resource' => 'BULK_MUTATION_VARIABLES',
                    'filename' => $filename,
                    'mimeType' => 'text/jsonl',
                    'httpMethod' => 'POST',
                ],
            ]
        ]);

        $reserveResponse->throw();

        $target = Arr::first($reserveResponse->json('data.stagedUploadsCreate.stagedTargets'));

        $uploadResponse = Http::asMultipart()
            ->attach('file', $file, "{$filename}.jsonl")
            ->post(
                data_get($target, 'url'),
                collect(data_get($target, 'parameters'))
                    ->mapWithKeys(function ($parameter) {
                        return [$parameter['name'] => $parameter['value']];
                    })
                    ->toArray()
            );

        $uploadResponse->throw();

        return $reserveResponse;
    }

    /**
     * Prepend scheme to the given URL.
     *
     * @param  string  $url
     * @return string
     */
    private function prependScheme($url)
    {
        return parse_url($url, PHP_URL_SCHEME) === null
            ? 'https://' . $url
            : $url;
    }
}
