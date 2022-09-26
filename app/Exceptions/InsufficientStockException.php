<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class InsufficientStockException extends Exception
{
    /**
     * The invalid products.
     *
     * @var Collection
     */
    public Collection $invalidProducts;

    /**
     * Create a new exception instance.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $invalidProducts
     * @return void
     */
    public function __construct(array|Arrayable $invalidProducts) {
        $this->invalidProducts = collect($invalidProducts);

        parent::__construct(__('errors.11'), 11);
    }

    /**
     * Get the exception metainfo.
     *
     * @return array
     */
    public function getMeta()
    {
        $mapProducts = fn ($products) => $products
            ->map(function ($product) {
                $title = $product['title'];
                $optionValues = Arr::has($product, 'product_option_values')
                    ? $product['product_option_values']->implode('name', ' / ')
                    : null;

                if ($optionValues) {
                    $title .= ' - ' . $optionValues;
                }

                return compact('title') + Arr::only($product, [
                    'product_id',
                    'product_variant_id',
                    'quantity',
                    'stock',
                ]);
            })
            ->unique()
            ->values()
            ->toArray();

        return collect([
            'out_of_stock' => $mapProducts(
                $this->invalidProducts->where('is_out_of_stock', true)
            ),
            'insufficient_stock' => $mapProducts(
                $this->invalidProducts->where('has_insufficient_stock', true)
            ),
        ])->filter()->toArray();
    }
}
