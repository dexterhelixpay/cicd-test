<?php

namespace Database\Seeders;

use App\Models\TableColumn;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class TableColumnSeeder extends Seeder
{

    public $columns = [
        [
            'text' => 'Order #',
            'value' => 'id',
            'width' => '100px',
            'align' => 'center',
            'sortable' => true,
            'sort' => 1,
            'is_default' => true
        ],
        [
            'text' => 'Billing Date',
            'value' => 'billing_date',
            'width' => '120px',
            'align' => 'center',
            'sortable' => true,
            'sort' => 2,
            'is_default' => true
        ],
        [
            'text' => 'Payment Date',
            'value' => 'paid_at',
            'width' => '135px',
            'align' => 'center',
            'sortable' => true,
            'sort' => 2,
            'is_default' => true
        ],
        [
            'text' => 'Customer',
            'value' => 'recipient',
            'width' => '150px',
            'sortable' => true,
            'sort' => 3,
            'is_default' => true
        ],
        [
            'text' => 'City',
            'value' => 'billing_city',
            'sortable' => false,
            'width' => '135px',
            'sort' => 4,
            'is_default' => false
        ],
        [
            'text' => 'Subscription ID',
            'value' => 'subscription_id',
            'width' => '145px',
            'align' => 'center',
            'sortable' => true,
            'sort' => 5,
            'is_default' => true
        ],
        [
            'text' => 'Order',
            'value' => 'order',
            'width' => '180px',
            'align' => 'center',
            'sortable' => false,
            'sort' => 6,
            'is_default' => true
        ],
        [
            'text' => 'Price',
            'value' => 'total_price',
            'sortable' => true,
            'align' => 'center',
            'width' => '150px',
            'sort' => 7,
            'is_default' => true
        ],
        [
            'text' => 'Recurrence',
            'value' => 'recurrence',
            'sortable' => false,
            'align' => 'center',
            'width' => '100px',
            'sort' => 8,
            'is_default' => true
        ],
        [
            'text' => 'Fulfillment Date',
            'value' => 'fulfilled_date',
            'sortable' => false,
            'align' => 'center',
            'width' => '135px',
            'sort' => 9,
            'is_default' => true
        ],
        [
            'text' => 'Delivery Date',
            'value' => 'shipping_date',
            'sortable' => false,
            'align' => 'center',
            'width' => '135px',
            'sort' => 10,
            'is_default' => true
        ],
        [
            'text' => 'Delivery Date/Fulfillment Date',
            'value' => 'shipping_date',
            'sortable' => false,
            'align' => 'center',
            'width' => '135px',
            'sort' => 11,
            'is_default' => true
        ],
        [
            'text' => 'Status',
            'value' => 'order_status_id',
            'align' => 'center',
            'width' => '100px',
            'sortable' => false,
            'sort' => 12,
            'is_default' => true
        ],
        [
            'text' => 'Order Status',
            'value' => 'fulfilled_at',
            'align' => 'center',
            'width' => '100px',
            'sortable' => false,
            'sort' => 13,
            'is_default' => false
        ],
        [
            'text' => 'Actions',
            'value' => 'actions',
            'sortable' => false,
            'align' => 'center',
            'width' => '50px',
            'sort' => 14,
            'is_default' => true
        ],
        [
            'text' => '',
            'value' => 'data-table-select',
            'width' => '50px',
            'align' => 'center',
            'sortable' => false,
            'sort' => 15,
        ],
        [
            'text' => 'Original Price',
            'value' => 'original_price',
            'sortable' => true,
            'align' => 'center',
            'width' => '150px',
            'sort' => 16,
        ],
        [
            'text' => 'Voucher Code',
            'value' => 'voucher_code',
            'sortable' => true,
            'align' => 'center',
            'width' => '150px',
            'sort' => 17,
        ],
        [
            'text' => 'Customer phone number',
            'value' => 'subscription.customer.mobile_number',
            'sortable' => true,
            'width' => '100px',
            'sort' => 18,
        ],

        [
            'text' => 'Shipping Address',
            'value' => 'shipping_address',
            'sortable' => false,
            'width' => '135px',
            'sort' => 19,
        ],
        [
            'text' => '# of order in subscription',
            'value' => 'order_number',
            'width' => '100px',
            'align' => 'center',
            'sortable' => true,
            'sort' => 20,
        ],
        [
            'text' => 'Payment method',
            'value' => 'payment_type.name',
            'width' => '180px',
            'align' => 'center',
            'sortable' => true,
            'sort' => 21,
        ],
        [
            'text' => 'Customer email address',
            'value' => 'subscription.customer.email',
            'sortable' => true,
            'align' => 'center',
            'width' => '100px',
            'sort' => 22,
        ],
        [
            'text' => 'Amount before voucher',
            'value' => 'amount_before_voucher',
            'sortable' => true,
            'align' => 'center',
            'width' => '150px',
            'sort' => 23,
        ],
        [
            'text' => 'Voucher amount',
            'value' => 'voucher',
            'sortable' => false,
            'align' => 'center',
            'width' => '100px',
            'sort' => 24,
        ],

        [
            'text' => 'Total amount charged',
            'value' => 'total_charged',
            'sortable' => false,
            'align' => 'center',
            'width' => '100px',
            'sort' => 25,
        ],

        [
            'text' => 'Voucher Code',
            'value' => 'voucher_code',
            'sortable' => false,
            'align' => 'center',
            'width' => '100px',
            'sort' => 26,
        ],
        [
            'text' => 'Custom Fields',
            'value' => 'subscription.other_info',
            'sortable' => false,
            'align' => 'center',
            'width' => '180px',
            'sort' => 27,
        ],
        [
            'text' => 'Shopify order ID',
            'value' => 'shopify_order_id',
            'sortable' => true,
            'align' => 'center',
            'width' => '100px',
            'sort' => 28,
        ]
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TableColumn::truncate();

        collect($this->columns)->each(function ($column) {
            TableColumn::firstOrNew(Arr::only($column, ['text', 'value']))
                ->fill($column)
                ->setAttribute('table', 'orders')
                ->save();
        });
    }
}
