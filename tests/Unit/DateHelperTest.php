<?php

use App\Models\Merchant;
use App\Support\Date;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class);

$frequencies = [
    'weekly',
    'semimonthly',
    'single',
    'monthly',
    'quarterly',
    'bimonthly',
    'semiannual',
    'annually'
];


it('formats payment schedule', function () use ($frequencies) {
    /** @var \Tests\TestCase $this */

    collect($frequencies)
        ->each(function ($frequency) {
           $paymentSchedule = format_payment_schedule($frequency, now());

           switch ($frequency) {
               case 'weekly':
               case 'semimonthly':
                    $this->assertCount(2, $paymentSchedule);
                    $this->assertArrayHasKey('frequency', $paymentSchedule);
                    $this->assertArrayHasKey('day_of_week', $paymentSchedule);
                    break;

                case 'single':
                case 'monthly':
                case 'quarterly':
                case 'bimonthly':
                case 'semiannual':
                    $this->assertCount(2, $paymentSchedule);
                    $this->assertArrayHasKey('frequency', $paymentSchedule);
                    $this->assertArrayHasKey('day', $paymentSchedule);
                    break;

                default:
                     $this->assertCount(3, $paymentSchedule);
                     $this->assertArrayHasKey('frequency', $paymentSchedule);
                     $this->assertArrayHasKey('day', $paymentSchedule);
                     $this->assertArrayHasKey('month', $paymentSchedule);
                     break;
            };
        });
});


it('formats payment schedule with null billing date', function () use ($frequencies) {
    /** @var \Tests\TestCase $this */

    collect($frequencies)
        ->each(function ($frequency) {
            format_payment_schedule($frequency, null);
        });
})->throws('Billing date is invalid');


it("formats payment schedule with frequency that doesn't exist", function () use ($frequencies) {
    /** @var \Tests\TestCase $this */

    collect($frequencies)
        ->each(function ($frequency) {
            format_payment_schedule("1{$frequency}", now()->toDateString());
        });
})->throws('The selected payment schedule frequency is invalid.');


it('validates a date', function () {
    /** @var \Tests\TestCase $this */

    $this->assertEquals(false, is_date(''));
    $this->assertEquals(false, is_date(null));
    $this->assertEquals(false, is_date(now()->format('M D, Y')));

    $this->assertEquals(true, is_date(now()->format('Y-m-d')));

    collect([
        'd/m/Y',
        'd/m/y',
        'm/d/y',
        'n/d/Y',
        'n/d/y',
        'n/j/Y',
        'n/t/Y',
    ])
    ->each(function ($date) {
        $this->assertEquals(false, is_date(now()->format($date)));
    });
});

it('it formats day of week to text', function () {
    /** @var \Tests\TestCase $this */

    $this->assertEquals(null, dayOfWeek(''));
    $this->assertEquals('sunday', dayOfWeek(null));

    collect(range(0, 6))
        ->each(function ($day) {
            switch ($day) {
                case 0:
                    $this->assertEquals('sunday', dayOfWeek($day));
                    break;
                case 1:
                    $this->assertEquals('monday', dayOfWeek($day));
                    break;
                case 2:
                    $this->assertEquals('tuesday', dayOfWeek($day));
                    break;
                case 3:
                    $this->assertEquals('wednesday', dayOfWeek($day));
                    break;
                case 4:
                    $this->assertEquals('thursday', dayOfWeek($day));
                    break;
                case 5:
                    $this->assertEquals('friday', dayOfWeek($day));
                    break;
                case 6:
                    $this->assertEquals('saturday', dayOfWeek($day));
                    break;
              }
        });
});

it('formats recurrence with subscription term', function () use ($frequencies) {
    /** @var \Tests\TestCase $this */

    $subscriptionTerms = [
        'Membership',
        'Subscription',
    ];

    collect($subscriptionTerms)
        ->each(function ($term) {
            $recurrenceText = formatRecurrenceText(
                null, Merchant::make(['subscription_term_singular' => $term])
            );

            $this->assertEquals('Single Order', $recurrenceText);
        });

    collect($frequencies)
        ->each(function ($frequency) use ($subscriptionTerms) {

            $frequencyText = match ($frequency) {
                'weekly' => 'Weekly',
                'semimonthly' => 'Every Other Week',
                'single' => 'Single Order',
                'monthly' => 'Monthly',
                'quarterly' => 'Quarterly',
                'bimonthly' => 'Every 2 months',
                'semiannual' => 'Semi Annual',
                'annually' => 'Annual'
            };

            collect($subscriptionTerms)
                ->each(function ($term) use ($frequency, $frequencyText) {
                    $recurrenceText = formatRecurrenceText(
                        $frequency, Merchant::make(['subscription_term_singular' => $term])
                    );

                    $this->assertStringContainsString($frequencyText, $recurrenceText);

                    if ($frequency != 'single') {
                        $this->assertStringContainsString($term, $recurrenceText);
                    }
                });
        });
});
