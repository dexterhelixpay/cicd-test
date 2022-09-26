<?php

namespace App\Imports\PaymayaMids;

use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithConditionalSheets;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class Workbook implements WithMultipleSheets
{
    use Importable, WithConditionalSheets;

    /**
     * @return array
     */
    public function conditionalSheets(): array
    {
        return [
            'Sheet1' => new Keys,
            'Sheet2' => new Mids,
        ];
    }
}
