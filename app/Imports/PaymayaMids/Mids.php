<?php

namespace App\Imports\PaymayaMids;

use App\Models\PaymayaMid;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class Mids implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    /**
     * @param  Collection  $collection
     * @return void
     */
    public function collection(Collection $collection)
    {
        $collection->each(function (Collection $row) {
            $isVault = $row->get('products') === 'Online Vault';
            $isPwp = $row->get('products') === 'Pay By PayMaya';

            PaymayaMid::updateOrCreate([
                'mid' => $row->get('mids'),
            ],  [
                'business_segment' => $row->get('business_segment'),
                'mdr' => round(floatval($row->get('updated_mdr')) * 100, 2),
                'mcc' => (string) $row->get('updated_mcc'),
                'is_vault' => $isVault,
                'is_pwp' => $isPwp,
            ]);
        });
    }
}
