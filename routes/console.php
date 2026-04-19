<?php

use Illuminate\Support\Facades\Schedule;


Schedule::command('payment:check')
    ->everyThreeMinutes()
    ->withoutOverlapping();
Schedule::command('payment:check')
    ->everyTwoMinutes()
    ->withoutOverlapping();
