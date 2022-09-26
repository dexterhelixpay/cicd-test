<?php

namespace App\Services;

use App\Imports\VoucherQualifiedCustomers as ImportQualifiedCustomers;
use App\Models\Merchant;
use App\Models\Voucher;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VoucherService
{
    /**
     * Import the qualified customer info for the given voucher
     *
     * @param  \App\Models\Voucher  $voucher
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  bool $isUpdate
     *
     * @return \Illuminate\Support\Collection
     */
    public function importQualifiedCustomer(Voucher $voucher, UploadedFile $file, $isUpdate = false)
    {
        ($import =  new ImportQualifiedCustomers())->import($file);

        $callback = function (Collection $row) use ($voucher, $isUpdate) {
            if ($isUpdate) {
                $data = array_merge(data_get($row, 'mobile_number'),  data_get($row, 'email'));

                foreach ($data as $info) {
                    $qualifiedCustomer = $voucher->qualifiedCustomers()
                        ->where(function ($query) use ($info) {
                            $query->whereJsonContains('mobile_numbers', $info)
                                ->orWhereJsonContains('emails', $info);
                        })
                        ->first();


                    if ($qualifiedCustomer) {
                        $qualifiedCustomer->update([
                            'mobile_numbers' => data_get($row, 'mobile_number'),
                            'emails' => data_get($row, 'email')
                        ]);

                        return $qualifiedCustomer;
                    }
                }

            }

            $qualifiedCustomer = $voucher->qualifiedCustomers()->make([
                'mobile_numbers' => data_get($row, 'mobile_number'),
                'emails' => data_get($row, 'email')
            ]);
            $qualifiedCustomer->save();

            return $qualifiedCustomer;
        };

        return DB::transaction(function () use ($import, $callback) {
            return $import->customers
                ->flatMap(function (Collection $customers) use ($callback) {
                    return [$callback($customers)];
                })->filter();
        });
    }
}
