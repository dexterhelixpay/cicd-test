<?php

namespace App\Imports\PaymayaMids;

use App\Models\PaymayaMid;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class Keys implements SkipsEmptyRows, ToCollection, WithStartRow
{
    /**
     * Constant representing the MID column.
     *
     * @var int
     */
    const MID = 0;

    /**
     * Constant representing the MID label.
     *
     * @var int
     */
    const LABEL = 3;

    /**
     * Constant representing the public key.
     *
     * @var int
     */
    const PUBLIC_KEY = 5;

    /**
     * Constant representing the secret key.
     *
     * @var int
     */
    const SECRET_KEY = 6;

    /**
     * @return int
     */
    public function startRow(): int
    {
        return 2;
    }

    /**
     * @param  Collection  $collection
     * @return void
     */
    public function collection(Collection $collection)
    {
        $collection->each(function (Collection $row) {
            if (!$mid = PaymayaMid::firstWhere('mid', $midValue = $row->get(static::MID))) {
                $isVault = Str::contains($row->get(static::LABEL), 'ONLINE VAULT');
                $isPwp = Str::contains($row->get(static::LABEL), 'PAY WITH PAYMAYA');

                $mid = PaymayaMid::updateOrCreate([
                    'mid' => (string) $midValue,
                ], [
                    'business_segment' => 'Ecommerce',
                    'mdr' => 0,
                    'mcc' => '0000',
                    'is_vault' => $isVault,
                    'is_pwp' => $isPwp,
                ]);
            }

            $publicKey = trim($row->get(static::PUBLIC_KEY) ?: '');

            if (Str::startsWith($publicKey, 'pk-')) {
                $mid->setAttribute('public_key', $publicKey);
            }

            $secretKey = trim($row->get(static::SECRET_KEY) ?: '');

            if (Str::startsWith($secretKey, 'sk-')) {
                $mid->setAttribute('secret_key', $secretKey);
            }

            $mid->save();
        });
    }
}
