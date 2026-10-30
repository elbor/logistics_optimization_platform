<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Прострочені токени водія вже не працюють, але лишаються в базі — прибираємо
Schedule::command('sanctum:prune-expired --hours=24')->daily();
