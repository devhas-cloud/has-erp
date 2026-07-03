<?php

use App\Services\TaskAlertService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');



//schedule task alert setiap jam 8 pagi
Schedule::call(function () {
    app(TaskAlertService::class)->sendAlerts();
})->dailyAt('08:00')->name('send-task-alerts')->withoutOverlapping();
