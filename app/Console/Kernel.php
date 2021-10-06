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
        Commands\GetEraRewards::class,
        Commands\GetBlockInfo::class,
        Commands\GetValidators::class,
        Commands\ProcessHistory::class,
        Commands\GetPeers::class,
        Commands\CheckNotifications::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('api:CheckNotifications')->everyFiveMinutes()->runInBackground();

        $schedule->command('api:GetBlockInfo')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('api:GetEraRewards')->everyFiveMinutes()->withoutOverlapping();


        $schedule->command('api:GetAccountInfo')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('api:GetAccountBalance')->everyFiveMinutes()->withoutOverlapping();

        $schedule->command('api:ProcessHistory')->everyFiveMinutes()->withoutOverlapping()->runInBackground();

        $schedule->command('api:GetValidators')->hourly()->withoutOverlapping();
        $schedule->command('api:GetValidatorAccountInfo')->daily()->withoutOverlapping();
        $schedule->command('api:GetPeers')->daily()->withoutOverlapping();
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
