<?php

use App\Console\Commands\PublishScheduledNews;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Transiciona noticias 'scheduled' a 'published' cuando llega su fecha programada.
Schedule::command(PublishScheduledNews::class)->everyMinute();
