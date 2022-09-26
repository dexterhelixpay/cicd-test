<?php

namespace App\Libraries\Xendit;

use App\Facades\Xendit as Facade;
use App\Libraries\Xendit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FeeRule extends Xendit
{
    /**
     * Constant representing the flat fee.
     *
     * @var string
     */
    const UNIT_FLAT = 'flat';

    /**
     * Constant representing the percentage fee.
     *
     * @var string
     */
    const UNIT_PERCENT = 'percent';

    /**
     * Create a fee rule.
     *
     * @param  string  $name
     * @param  string  $unit
     * @param  float  $amount
     * @param  string  $currency
     * @return \Illuminate\Http\Client\Response
     */
    public function create(
        string $name,
        string $unit,
        float $amount,
        string $currency = Facade::CURRENCY_PHP
    ) {
        Validator::make([
            'unit' => $unit,
            'amount' => $amount,
            'currency' => $currency,
        ], [
            'unit' => [
                'required',
                Rule::in(self::UNIT_FLAT, self::UNIT_PERCENT),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0',
                Rule::when($unit === self::UNIT_PERCENT, 'max:100', 'max:10000000'),
            ],
            'currency' => [
                'required',
                Rule::in(Facade::CURRENCY_PHP, Facade::CURRENCY_IDR),
            ],
        ])->validate();

        return $this->client()->post('fee_rules', [
            'name' => $name,
            'routes' => [
                [
                    'unit' => $unit,
                    'amount' => $amount,
                    'currency' => $currency,
                ],
            ],
        ]);
    }
}
