<?php

namespace App\Imports;

use App\Models\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class OrderPricesImport implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithValidation
{
    use Importable;

    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        $collection
            ->each(function (Collection $row) {
                if (!$order = Order::find($row->get('order_id'))) {
                    return;
                }

                $order->update([
                    'total_price' => $row['amount'] ?? 0
                ]);
            });
    }

    /**
    * @return array
    */
    public function rules(): array
    {
        return [
            'order_id' => [
                'required',
                'exists:orders,id'
            ],
            'amount' => 'nullable|int',
        ];
    }
}
