<?php

namespace App\Observers;

use App\Models\Voucher;

class VoucherObserver
{

    /**
     * Handle the voucher "updating" event.
     *
     * @param  \App\Models\Voucher  $voucher
     * @return void
     */
    public function updated($voucher)
    {
        $this->disableVoucher($voucher);
        $this->enableVoucher($voucher);
    }

    /**
     * Disable voucher if has no remaining voucher
     *
     * @param  \App\Models\Voucher  $voucher
     * @return void
     */
    protected function disableVoucher($voucher)
    {
        if ($voucher->wasChanged('remaining_count') && $voucher->remaining_count < 1) {
            $voucher->is_enabled = false;
            $voucher->saveQuietly();
        }
    }

    /**
     * Enable voucher if voucher was returned
     *
     * @param  \App\Models\Voucher  $voucher
     * @return void
     */
    protected function enableVoucher($voucher)
    {
        if (
            $voucher->getRawOriginal('remaining_count') == 0
            && $voucher->wasChanged('remaining_count')
            && $voucher->remaining_count == 1
        ) {
            $voucher->is_enabled = true;
            $voucher->saveQuietly();
        }
    }
}
