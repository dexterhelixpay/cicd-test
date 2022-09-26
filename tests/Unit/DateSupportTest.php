<?php

use App\Support\Date;
use Illuminate\Support\Carbon;

it('validates a date', function () {
    /** @var \Tests\TestCase $this */

    $this->assertEquals(false, Date::isValid(''));
    $this->assertEquals(false, Date::isValid(now()->format('M D, Y')));
    $this->assertEquals(false, Date::isValid(now()->format('Y-d-m')));

    collect(Date::DATE_FORMATS)
        ->each(function ($date) {
            $this->assertEquals(true, Date::isValid(now()->format($date)));
        });
});

it('formats a date', function () {
    /** @var \Tests\TestCase $this */

    $this->assertEquals(null, Date::toCarbon(''));
    $this->assertEquals(null, Date::toCarbon(now()->format('M D, Y')));
    $this->assertEquals(null, Date::toCarbon(now()->format('Y-d-m')));

    collect(Date::DATE_FORMATS)
        ->each(function ($date) {
            $date = now()->format($date);

            $validFormat = collect(Date::DATE_FORMATS)
                ->first(function ($format) use ($date) {
                    return Date::isValid($date, $format);
                });

            $formatted = Carbon::createFromFormat($validFormat, $date);
            $this->assertEquals($formatted, Date::toCarbon($date));
        });
});
