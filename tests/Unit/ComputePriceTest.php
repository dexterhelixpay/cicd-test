<?php

use App\Models\User;
use App\Support\Prices;
use Illuminate\Support\Str;

it('computes total price', function () {
    /** @var \Tests\TestCase $this */

  $merchant = new \App\Models\Merchant();
  $merchant->fill([
    'name' => 'New Merchant',
    'is_outstanding_balance_enabled' => false
  ]);

  $products = collect([
    ['quantity' => 1, 'price' => 0],
    ['quantity' => 2, 'price' => 0]
  ]);

  $setProductPrices = function ($prices) use ($products) {
    return $products
        ->map(function ($product, $index) use ($prices) {
            data_set($product, 'price', $prices[$index]);
            return $product;
        });
  };

  // expectation
  expect(Prices::compute(merchant: $merchant, products: $products))
    ->toBeArray()
    ->toMatchArray([
        'total_price' => 0
    ]);

  expect(Prices::compute(merchant: $merchant, products: $setProductPrices([null,null])))
    ->toBeArray()
    ->toMatchArray([
        'total_price' => 0
    ]);

  expect(Prices::compute(merchant: $merchant, products: $setProductPrices([10,100])))
    ->toBeArray()
    ->toMatchArray([
        'total_price' => 210
    ]);

});
