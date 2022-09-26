<?php

namespace App\Exports;

use App\Models\Merchant;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
class SecureVoucherTemplate implements FromCollection, ShouldAutoSize, WithEvents, WithDrawings
{
    use Exportable, RegistersEventListeners;


    /**
     * Create a new export instance.
     *
     * @return void
     */
    public function __construct()
    {

    }


    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setPath(storage_path('images/secure_voucher_logo.png'));
        $drawing->setHeight(50);
        $drawing->setCoordinates('A1');

        return $drawing;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return collect();
    }

    /**
     * @param  \Maatwebsite\Excel\Events\AfterSheet  $event
     * @return void
     */
    public static function afterSheet(AfterSheet $event)
    {
        $sheet = $event->getSheet()->getDelegate();
        $sheet->freezePane('A22');

        $sheet->mergeCells("A4:N4")
            ->getCell('A4')
            ->setValue('Enter the phone number and email addresses that are eligible for exclusive access to this voucher.');

        $sheet->mergeCells("A6:N6")
            ->getCell('A6')
            ->setValue('Customers can use these contacts to unlock usage of the exclusive voucher.');

        $sheet->mergeCells("A8:N8")
            ->getCell('A8')
            ->setValue(
                'This template also supports multiple mobile numbers and emails; make sure they are separated by commas'
            );

        $sheet->mergeCells("A10:N10")
            ->getCell('A10')
            ->setValue(
                'Each row represents one access. For instance if one person has phone numbers and 1 email address. put all of these in one row together.'
            );

        $sheet->mergeCells("A12:N12")
            ->getCell('A12')
            ->setValue(
                'The customer can use any of these contacts but once they use it, they will not be able to use the voucher again with their other contact info.'
            );

        $sheet->mergeCells("A14:N14")
            ->getCell('A14')
            ->setValue(
                'For each row of contact info, a customer can use any of these contacts but once they use it, they will not be able to use the voucher'
            );

        $sheet->mergeCells("A15:N15")
            ->getCell('A15')
            ->setValue('again with their other contact info. This is to help limit the exclusive access of the voucher.');

        $sheet->getCell('A17')->setValue('Example:');


        $sheet->getCell('A18')->setValue('Mobile Number')->getStyle('A18')->getFont()->setItalic(true);
        $sheet->getCell('B18')->setValue('Email')->getStyle('B18')->getFont()->setItalic(true);

        $sheet->getCell('A19')->setValue('09123456789,09987654321')->getStyle('A19')->getFont()->setItalic(true);
        $sheet->getCell('B19')->setValue('name@gmail.com')->getStyle('B19')->getFont()->setItalic(true);

        $sheet->getCell('A20')->setValue('09111111111')->getStyle('A20')->getFont()->setItalic(true);
        $sheet->getCell('B20')->setValue('name+@gmail.com')->getStyle('B20')->getFont()->setItalic(true);

        $sheet->getCell('A22')->setValue('Mobile Number')->getStyle('A22')->getFont()->setBold(true);
        $sheet->getCell('B22')->setValue('Email')->getStyle('B22')->getFont()->setBold(true);
    }
}
