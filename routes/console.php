<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// For Production: * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
// For local testing: php artisan pixelforge:poll-generations
Schedule::command('pixelforge:poll-generations')
    ->everyMinute()
    ->withoutOverlapping();

