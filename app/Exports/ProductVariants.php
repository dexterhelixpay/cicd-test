<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Protection;

class ProductVariants implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStrictNullComparison
{
    use Exportable, RegistersEventListeners;

    /**
     * Create a new export instance.
     *
     * @param  \Illuminate\Support\Collection  $variants
     * @return void
     */
    public function __construct(
        public Collection $variants
    ) {
        //
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function collection()
    {
        return $this->variants;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Variant ID',
            'Product',
            'Variant',
            'Stock',
        ];
    }

    /**
     * @param  \App\Models\ProductVariant  $variant
     * @return array
     */
    public function map($variant): array
    {
        return [
            $variant->getKey(),
            $variant->title,
            $variant->variant_title,
            $variant->stock,
        ];
    }

    /**
     * @param  \Maatwebsite\Excel\Events\AfterSheet  $event
     * @return void
     */
    public static function afterSheet(AfterSheet $event)
    {
        $worksheet = $event->getSheet()->getDelegate();

        $worksheet->freezePane('A2');
        $worksheet->getProtection()->setSheet(true);

        $row = 2;

        while ($worksheet->getCell("A{$row}")->getValue()) {
            $worksheet->getStyle("D{$row}")
                ->getProtection()
                ->setLocked(Protection::PROTECTION_UNPROTECTED);

            $row++;
        }
    }
}
