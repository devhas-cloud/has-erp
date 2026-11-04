<?php

use App\Services\PayableDueNotifier;
use App\Services\ReceivableDueNotifier;
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

// Pengingat WhatsApp hutang & piutang (aktif/nonaktif, jam kirim, tujuan diatur di
// Master Data -> Notifikasi Finance). Dicek tiap 5 menit; send() hanya mengirim sekali
// per hari pada / setelah jam kirim.
Schedule::call(function () {
    app(PayableDueNotifier::class)->send();
})->everyFiveMinutes()->name('send-payable-due-reminders')->withoutOverlapping();

Schedule::call(function () {
    app(ReceivableDueNotifier::class)->send();
})->everyFiveMinutes()->name('send-receivable-due-reminders')->withoutOverlapping();
