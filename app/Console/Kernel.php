<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $autoEarlyRemindTime = setting('AutoEarlyRemindTime', '05:30');
        $autoLateRemindTime = setting('AutoLateRemindTime', '06:30');
        $autoCancelTime = setting('AutoCancelTime', '07:30');

        $autoChargeStartTime = setting('AutoChargeStartTime', '07:00');
        $autoChargeEndTime = setting('AutoChargeEndtime', '11:00');

        $schedule->command('merchant:remind', ['--days-before' => 4])
            ->dailyAt($autoEarlyRemindTime);

        $schedule->command('order:remind', ['--type' => 'before'])
          ->dailyAt($autoEarlyRemindTime);

        $schedule->command('order:remind', ['--type' => 'today'])
            ->dailyAt(setting('AutoRemindTime', '06:00'));

        $schedule->command('order:remind', ['--type' => 'after'])
            ->dailyAt($autoLateRemindTime);

        $schedule->command('order:pay', ['--no-interaction'])
            ->everyTenMinutes()
            ->between($autoChargeStartTime, $autoChargeEndTime);

        $schedule->command('order:cancel')
            ->dailyAt($autoCancelTime);

        $schedule->command('order:create')
            ->dailyAt($autoCancelTime);

        $schedule->command('discord:remove-user-role')
            ->dailyAt($autoCancelTime);

        $schedule->command('order:fail', ['--lapsed'])
            ->everyFifteenMinutes();

        $schedule->command('subscription-import:update')
            ->twiceDaily(1, 13);

        $schedule->command('password:remind')->daily();

        $schedule->command('account:disable')->daily();

        $schedule->command('merchant-limit:reset')
            ->hourly();

        $schedule->command('cached-shopify-products:delete')
            ->hourly();

        $schedule->command('marketing-card:cleanup')->daily();

        $schedule->command('email-blast:publish')
            ->everyThirtyMinutes();

        $schedule->command('post:sync', ['--vimeo-transcode'])
            ->everyFiveMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
