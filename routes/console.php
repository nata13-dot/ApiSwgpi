<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\SemesterManagementService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(SemesterManagementService::class)->syncCurrentPeriod())
    ->dailyAt('00:10')
    ->name('sync-current-academic-period')
    ->withoutOverlapping();
