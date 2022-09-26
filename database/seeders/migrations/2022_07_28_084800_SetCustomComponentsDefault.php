<?php

use App\Models\Merchant;
use App\Models\CustomField;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class SetCustomComponentsDefault_2022_07_28_084800 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
            $defaultComponent = [
                'Customer Details' => [
                    'is_default' => true,
                    'is_visible' => true,
                    'is_customer_details' => true,
                    'custom_fields' => [
                        'Name' => [
                            'sort_number' => 1,
                            'code' => 'name',
                            'is_default' => true,
                            'data_type' => 'string',
                            'is_required' => true,
                            'is_visible' => true,
                        ],
                        'Email Address' => [
                            'sort_number' => 2,
                            'code' => 'emailAddress',
                            'is_default' => true,
                            'data_type' => 'string',
                            'is_required' => true,
                            'is_visible' => true,
                        ],
                        'Mobile Number' => [
                            'sort_number' => 3,
                            'code' => 'mobileNumber',
                            'is_default' => true,
                            'data_type' => 'number',
                            'is_required' => true,
                            'is_visible' => true,
                        ],
                    ]
                ],
                'Address & Shipping Details' => [
                    'is_default' => true,
                    'is_visible' => true,
                    'is_address_details' => true,
                    'custom_fields' => [
                        'Country' => [
                            'sort_number' => 1,
                            'code' => 'country',
                            'is_default' => true,
                            'data_type' => 'dropdown',
                            'is_required' => true,
                            'is_visible' => true,
                        ],
                        'Street Address / Building Name' => [
                            'sort_number' => 2,
                            'code' => 'address',
                            'is_default' => true,
                            'data_type' => 'string',
                            'is_required' => true,
                            'is_visible' => true,
                        ],
                        'Province' => [
                            'sort_number' => 3,
                            'code' => 'province',
                            'is_default' => true,
                            'data_type' => 'dropdown',
                            'is_required' => true,
                            'is_visible' => true,
                        ],
                        'City' => [
                            'sort_number' => 4,
                            'code' => 'city',
                            'is_default' => true,
                            'data_type' => 'string',
                            'is_required' => true,
                            'is_visible' => true,
                        ],
                        'Barangay' => [
                            'sort_number' => 5,
                            'code' => 'barangay',
                            'is_default' => true,
                            'data_type' => 'string',
                            'is_required' => true,
                            'is_visible' => true,
                        ],
                        'Zip Code' => [
                            'sort_number' => 6,
                            'code' => 'zipCode',
                            'is_default' => true,
                            'data_type' => 'string',
                            'is_required' => true,
                            'is_visible' => true,
                        ]
                    ]
                ]
            ];

            Merchant::query()
                ->whereNull('deleted_at')
                ->cursor()
                ->tapEach(function (Merchant $merchant) use ($defaultComponent) {
                    DB::transaction(function () use ($merchant, $defaultComponent) {
                        collect($defaultComponent)
                            ->each(function ($component, $key) use($merchant) {
                                $title = $key;

                                if (!$merchant->has_shippable_products && data_get($component, 'is_address_details')) {
                                    $title = 'Address & Billing Details';
                                 }

                                $customComponent = $merchant->customComponents()
                                    ->updateOrCreate(
                                        [
                                            'title' => $title
                                        ],
                                        Arr::except($component, 'custom_fields')
                                    );

                                $customComponent->sort_number = $merchant->customComponents()->max('sort_number') + 1;
                                $customComponent->save();

                                collect($component['custom_fields'] ?? [])
                                    ->each(function ($customField, $key) use($customComponent, $merchant) {
                                        $customField = $customComponent->customFields()
                                            ->updateOrCreate([
                                                'merchant_id' => $merchant->id,
                                                'label' => $key
                                            ], $customField);
                                    });

                                if ($key == 'Customer Details') {
                                    $merchant
                                        ->subscriptionCustomFields()
                                        ->where('custom_component_id', 0)
                                        ->get()
                                        ->each(function ($customField) use($customComponent, $merchant) {
                                            $customField->sort_number = $merchant
                                                ->subscriptionCustomFields()
                                                ->where('custom_component_id', $customComponent->id)
                                                ->max('sort_number') + 1;

                                            $customField->custom_component_id = $customComponent->id;
                                            $customField->save();
                                        });
                            }
                        });
                    });
                })->all();
    }
}
