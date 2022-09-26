<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MerchantSubdomains implements SkipsEmptyRows, ToCollection, WithCalculatedFormulas, WithHeadingRow
{
    use Importable;

    /**
     * @param  Collection  $collection
     * @return void
     */
    public function collection(Collection $collection)
    {
        //
    }
}
