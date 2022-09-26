<?php

namespace App\Imports;

use App\Models\Subscription;
use App\Models\OrderStatus;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class SubscriptionPrices implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithValidation
{
    use Importable;

    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        $collection
            ->each(function (Collection $row) {
                if (!$subscription = Subscription::find($row->get('subscription_id'))) {
                    return;
                }

                $subscription->forceFill([
                    'total_price' => $row['amount'] ?? null,
                    'total_price_updated_at' => now()->toDateTimeString(),
                ])->update();

                $subscription->orders()
                    ->whereNotIn('order_status_id', [
                        OrderStatus::PAID,
                        OrderStatus::SKIPPED,
                    ])
                    ->update([
                        'total_price' => $row['amount'] ?? null,
                    ]);
            });
    }

    /**
    * @return array
    */
    public function rules(): array
    {
        return [
            'subscription_id' => [
                'required',
                Rule::exists('subscriptions', 'id')->whereNull('deleted_at'),
            ],
            'amount' => 'nullable|int|min:0',
        ];
    }
}
