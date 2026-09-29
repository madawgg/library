<?php

use Illuminate\Support\Facades\Schedule;

// Spec 002 (RF-05b): flag loans that have been active for more than two months.
Schedule::command('loans:check-overdue')->dailyAt('02:00');
