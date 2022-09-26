<?php

namespace App\Support;

use App\Exceptions\BadRequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class PaymentSchedule
{
    /**
     * Get the multiplier of the given frequency.
     *
     * @param  string  $frequency
     * @return int
     */
    public static function getFrequencyMultiplier(string $frequency)
    {
        return match ($frequency) {
            'bimonthly' => 2,
            'quarterly' => 3,
            'semiannual' => 6,
            'annually' => 12,
            default => 1,
        };
    }

    /**
     * Get the proper sort number of the given frequency.
     *
     * @param  string  $frequency
     * @return int
     */
    public static function getFrequencySortNumber(string $frequency)
    {
        $sortNumber = collect([
            'single',
            'weekly',
            'semimonthly',
            'monthly',
            'bimonthly',
            'quarterly',
            'semiannual',
            'annually',
        ])->search($frequency);

        return $sortNumber === false ? 0 : ($sortNumber + 1);
    }

    /**
     * Get the billing cycle from the given schedule and date.
     *
     * @param  array  $schedule
     * @param  \Illuminate\Support\Carbon|string|null  $date
     * @return array
     */
    public static function getBillingCycle($schedule, $date = null, $isLastBillingOrderDifferInFrequency = false)
    {
        $date = Carbon::parse($date);
        switch (data_get($schedule, 'frequency')) {
            case 'weekly':
                $dayOfWeek = (int) $schedule['day_of_week'];

                if ($isLastBillingOrderDifferInFrequency) {
                    $dayOfWeek =  $date->dayOfWeek;
                }

                if ($date->dayOfWeek === $dayOfWeek) {
                    return [
                        $date->toDateString(),
                        $date->clone()->next($dayOfWeek)->toDateString(),
                    ];
                }

                $nextDayOfWeek = $date->clone()->next($dayOfWeek);

                return [
                    $nextDayOfWeek->toDateString(),
                    $nextDayOfWeek->clone()->next($dayOfWeek)->toDateString(),
                ];

            case 'semimonthly':
                if (Arr::has($schedule, 'day_of_week')) {
                    $dayOfWeek = (int) $schedule['day_of_week'];

                    if ($isLastBillingOrderDifferInFrequency) {
                        $dayOfWeek =  $date->dayOfWeek;
                    }

                    if ($date->dayOfWeek === $dayOfWeek) {
                        return [
                            $date->toDateString(),
                            $date->clone()->addWeeks(2)->toDateString(),
                        ];
                    }

                    $nextDay = $date->clone()->next($dayOfWeek);

                    return [
                        $nextDay->toDateString(),
                        $nextDay->clone()->addWeeks(2)->toDateString(),
                    ];
                }

                $firstDay = (int) $schedule['days'][0];
                $secondDay = (int) $schedule['days'][1];

                if ($date->day === $firstDay) {
                    $dates = [
                        $date->toDateString(),
                        $date->clone()
                            ->setUnitNoOverflow('day', $secondDay, 'month')
                            ->toDateString(),
                    ];
                }

                if ($date->day === $secondDay) {
                    $dates = [
                        $date->toDateString(),
                        $date->clone()
                            ->addMonthNoOverflow()
                            ->setDay($firstDay)
                            ->toDateString(),
                    ];
                }

                $dayIndex = collect($schedule['days'])
                    ->search(function ($day) use ($date) {
                        return $date->day < $day;
                    });

                if ($dayIndex === 0) {
                    $dates = [
                        $date->clone()
                            ->setUnitNoOverflow('day', $firstDay, 'month')
                            ->toDateString(),
                        $date->clone()
                            ->setUnitNoOverflow('day', $secondDay, 'month')
                            ->toDateString(),
                    ];
                }

                if (!isset($dates)) {
                    $dates = [
                        $date->clone()
                            ->setUnitNoOverflow('day', $secondDay, 'month')
                            ->toDateString(),
                        $date->clone()
                            ->addMonthNoOverflow()
                            ->setUnitNoOverflow('day', $firstDay, 'month')
                            ->toDateString(),
                    ];
                }

                if (count(array_unique($dates)) === 1) {
                    $dates[0] = Carbon::parse($dates[1])
                        ->subDay()
                        ->toDateString();
                }

                return $dates;

            case 'single':
            case 'monthly':
                $day = (int) $schedule['day'] ?? now()->day;

                if ($isLastBillingOrderDifferInFrequency) {
                    $day =  $date->day;
                }

                if (data_get($schedule, 'buffer_days')) {
                    if ($date->day > $day) {
                        $nearestMonth = $date->clone()
                            ->addMonthNoOverflow()
                            ->setDay($day);

                        $nextMonth = $date->clone()
                            ->addMonthNoOverflow()
                            ->setUnitNoOverflow('day', $day, 'month');
                    } else {
                        $nearestMonth = $date->clone()->setDay($day);
                        $nextMonth = $date->clone()->setUnitNoOverflow('day', $day, 'month');
                    }

                    $skippedMonth = now()->startOfDay()->diffInDays($nearestMonth->toDateString(), false) < $schedule['buffer_days']
                        ? $nextMonth->clone()->addMonthNoOverflow()->setUnitNoOverflow('day', $day, 'month')
                        : $nearestMonth;

                    if ($skippedMonth->startOfDay()->equalTo(Carbon::parse($date)->startOfDay())) {
                        $skippedMonth = $skippedMonth->clone()->addMonthNoOverflow()->setUnitNoOverflow('day', $day, 'month');
                    }

                    return [
                        $nextMonth->toDateString(),
                        $skippedMonth->toDateString(),
                    ];
                }

                if ($date->day > $day) {
                    $nextMonth = $date->clone()
                        ->addMonthNoOverflow()
                        ->setUnitNoOverflow('day', $day, 'month');

                    return [
                        $nextMonth->toDateString(),
                        $nextMonth->clone()
                            ->addMonthNoOverflow()
                            ->setUnitNoOverflow('day', $day, 'month')
                            ->toDateString(),
                    ];
                }

                $nextDay = $date->clone()->setUnitNoOverflow('day', $day, 'month');

                return [
                    $nextDay->toDateString(),
                    $nextDay->clone()
                        ->addMonthNoOverflow()
                        ->setUnitNoOverflow('day', $day, 'month')
                        ->toDateString(),
                ];

            case 'bimonthly':
            case 'semiannual':
            case 'quarterly':
                    $numberOfMonths = match ($schedule['frequency']) {
                        'bimonthly' => 2,
                        'quarterly' => 3,
                        'semiannual' => 6,
                    };

                    $day = (int) $schedule['day'];

                    if ($isLastBillingOrderDifferInFrequency) {
                        $day =  $date->day;
                    }

                    if ($date->day > $day) {
                        $nextMonth = $date->clone()
                            ->addMonthsWithoutOverflow(1)
                            ->setUnitNoOverflow('day', $day, 'month');

                        return [
                            $nextMonth->toDateString(),
                            $nextMonth->clone()
                                ->addMonthsWithoutOverflow($numberOfMonths)
                                ->setUnitNoOverflow('day', $day, 'month')
                                ->toDateString(),
                        ];
                    }

                    $nextDay = $date->clone()->setUnitNoOverflow('day', $day, 'month');

                    return [
                        $nextDay->toDateString(),
                        $nextDay->clone()
                            ->addMonthsWithoutOverflow($numberOfMonths)
                            ->setUnitNoOverflow('day', $day, 'month')
                            ->toDateString(),
                    ];

            case 'annually':
                    $day = (int) $schedule['day'];
                    $month = (int) $schedule['month'];

                    $nextSpecifiedDay = $date->clone()->setDay($day);

                    if ($date->month > $month ) {
                        $nextMonth = $date->clone()
                            // ->addYearNoOverflow(1)
                            ->setMonth($month)
                            ->setDay($day);

                        return [
                            $nextMonth->toDateString(),
                            $nextMonth->clone()->addYearNoOverflow(1)->toDateString(),
                        ];
                    }

                    if ($date->day < $day) {
                        $nextSpecifiedDay = $date->clone()
                            ->setDay($day);
                    } else {
                        $nextSpecifiedDay = $date;
                    }

                    return [
                        $nextSpecifiedDay->toDateString(),
                        $nextSpecifiedDay->clone()->addYearNoOverflow(1)->toDateString(),
                    ];

            case 'interval':
                $value = (int) $schedule['value'];
                $unit = $schedule['unit'];

                return [
                    $date->toDateString(),
                    $date->clone()->addUnit($unit, $value)->toDateString(),
                ];

            default:
                throw new BadRequestException('The selected payment schedule frequency is invalid.');
        }
    }

    /**
     * Get the string representation of the schedule.
     *
     * @param  array  $schedule
     * @return string
     */
    public static function toKeyString($schedule)
    {
        $number = $schedule['day_of_week'] ?? $schedule['day'] ?? '';

        return trim("{$schedule['frequency']}-{$number}", '-');
    }

    /**
     * Get the next estimated billing date.
     *
     * @param  array  $schedule
     * @param  \Illuminate\Support\Carbon|string|null  $date
     * @return \Illuminate\Support\Carbon|null
     */
    public static function getNextEstimatedBillingDate($schedule, $date = null)
    {
        $date = Carbon::parse($date)->startOfDay();

        switch ($schedule['frequency']) {
            case 'weekly':
                $addedWeeks = 1;

            case 'semimonthly':
                $addedWeeks = isset($addedWeeks) ? $addedWeeks : 2;
                $dayOfWeek = (int) $schedule['day_of_week'];

                if ($date->dayOfWeek === $dayOfWeek) {
                    return $date->clone()->addWeeks($addedWeeks);
                }

                $nextDayOfWeek = $date->clone()->next($dayOfWeek);

                return $nextDayOfWeek->clone()->addWeeks($addedWeeks);

            case 'single':
            case 'monthly':
                $addedMonths = 1;

            case 'bimonthly':
                $addedMonths = isset($addedMonths) ? $addedMonths : 3;

            case 'quarterly':
                $addedMonths = isset($addedMonths) ? $addedMonths : 3;

            case 'semiannual':
                $addedMonths = isset($addedMonths) ? $addedMonths : 6;

            case 'annually':
                $addedMonths = isset($addedMonths) ? $addedMonths : 12;

                $day = (int) ($schedule['day'] ?? now()->day);

                $nextDay = $date->clone()->setUnitNoOverflow('day', $day, 'month');

                return $nextDay->clone()
                    ->addMonthsWithoutOverflow($addedMonths)
                    ->setUnitNoOverflow('day', $day, 'month')
                    ->startOfDay();

            default:
                return null;
        }
    }
}
