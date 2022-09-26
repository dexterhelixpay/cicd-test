<?php

namespace App\Libraries\Shopify;

use App\Libraries\Shopify\Requests\Order as OrderRequest;

class Order extends Api
{
    /**
     * Create an order.
     *
     * @param  \App\Libraries\Shopify\Requests\Order  $request
     * @return \Illuminate\Http\Client\Response
     */
    public function create(OrderRequest $request)
    {
        return $this->client()
            ->timeout(90)
            ->post('admin/api/2021-07/orders.json', $request->data());
    }
}
