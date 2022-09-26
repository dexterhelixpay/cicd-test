<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PriceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'data' => [
                'subtotal_price' => [
                    'label' => 'Subtotal',
                    'amount' => $this['subtotal_price'],
                 ],
                'shipping_fee'=> [
                   'label' => 'Shipping Fee',
                   'amount' => $this['shipping_fee'],
                 ],
                 'bank_fee'=> [
                    'label' => 'Bank Fee',
                    'amount' => $this['bank_fee'],
                  ],
                 'discount_amount' => [
                    'label' => $this['discount_label'],
                    'amount' => $this['discount_amount'],
                    'type' => 'deduction'
                 ],
                 'voucher_amount' => [
                    'label' => 'Voucher Discount',
                    'amount' => $this['voucher_amount'],
                    'type' => 'deduction'
                 ],
                 'convenience_fee' => [
                    'label' => $this['convenience_label'],
                    'amount' => $this['convenience_fee'],
                 ],
                 'vat_amount' => [
                    'label' => 'VAT (12%)',
                    'amount' => $this['vat_amount'],
                  ],
                 'total_price' => [
                    'label' => 'Payment Due',
                    'amount' => $this['total_price'],
                 ],
                 'original_price' => $this['original_price'],
                 'is_free_delivery' => $this['is_free_delivery'],
                 'other_info' => [
                    'product_voucher_discounts' => $this['product_voucher_discounts']
                 ]
            ]

        ];
    }
}
