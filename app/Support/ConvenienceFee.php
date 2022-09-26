<?php

namespace App\Support;

use App\Models\PaymentType;
use Illuminate\Support\Carbon;
use App\Models\ConvenienceType;
use App\Exceptions\BadRequestException;

class ConvenienceFee
{
    /**
     * Get the convenience fee.
     *
     * @param  array||object  $merchant
     * @param  integer|null  $paymentType
     * @param  string|null  $bankCode
     * @return array
     */
    public static function getConvenienceFee($merchant, $paymentType = null, $bankCode = null)
    {
        if ($paymentType == PaymentType::CASH) {
            return [
                'convenience_label' => $merchant->convenience_label ?? 'Convenience Fee',
                'convenience_fee' => $merchant->convenience_fee ?? 0,
                'convenience_type_id' => $merchant->convenience_type_id ?? null,
            ];
        }

        $paymentMethod = $merchant->paymentTypes()
            ->where('payment_type_id',$paymentType)
            ->first();

        if ($paymentType == PaymentType::BANK_TRANSFER) {
            $bank = collect(json_decode($paymentMethod->pivot->payment_methods))
                ->where('code', $bankCode)
                ->first();

            if (!$bank) {
                throw new BadRequestException('Bank code is invalid.');
            }

            return [
                'convenience_label' => $bank?->convenience_label ?? 'Convenience Fee',
                'convenience_fee' => $bank?->convenience_fee ?? 0,
                'convenience_type_id' => $bank?->convenience_type_id ?? null,
            ];
        }

        return [
            'convenience_label' => $paymentMethod->pivot->convenience_label ?? 'Convenience Fee',
            'convenience_fee' => $paymentMethod->pivot->convenience_fee ?? 0,
            'convenience_type_id' => $paymentMethod->pivot->convenience_type_id ?? null
        ];
    }

    /**
     * Compute the order price with convenience fee.
     *
     * @param  array||object  $merchant
     * @param  integer|null  $paymentType
     * @param  string|null  $bankCode
     * @param  double|null  $total
     * @return array
     */
    public static function calculateOrderPrice($merchant, $paymentType = null, $bankCode = null, $total = null)
    {
        if (!$paymentType) return;

        $convenienceDetails = self::getConvenienceFee($merchant, $paymentType, $bankCode);
        $convenienceFeeLabel = $convenienceDetails['convenience_label'];

        if (
            $total > 0
            && $convenienceDetails['convenience_fee']
        ) {
            if ($convenienceDetails['convenience_type_id'] == ConvenienceType::PERCENTAGE) {
                $convenienceFeeLabel = "{$convenienceDetails['convenience_label']} ({$convenienceDetails['convenience_fee']}%)";
                $convenienceDetails['convenience_fee'] = $total * ($convenienceDetails['convenience_fee'] / 100);
            }

            $total += $convenienceDetails['convenience_fee'];

        }

        return [
            'convenience_label' => $convenienceFeeLabel,
            'convenience_label_original' => $convenienceDetails['convenience_label'],
            'convenience_fee' => $convenienceDetails['convenience_fee'],
            'convenience_type_id' => $convenienceDetails['convenience_type_id'],
            'total_price' => $total,
        ];
    }

}
