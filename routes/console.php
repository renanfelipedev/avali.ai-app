<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Verifica e pré-aquece o cache de status da IA a cada 30 minutos em background
Schedule::command('ia:check-status')->everyThirtyMinutes();
