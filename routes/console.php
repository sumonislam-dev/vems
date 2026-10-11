<?php

use Illuminate\Support\Facades\Schedule;

// Needs a cron entry running `php artisan schedule:run` every minute (see docs/DEPLOYMENT.md)
Schedule::command('activitylog:clean')->monthly();

Schedule::command('vems:expiry-reminders')->dailyAt('07:00')->timezone('Asia/Dhaka');
Schedule::command('vems:trips-needing-attention')->dailyAt('07:30')->timezone('Asia/Dhaka');

// Backups (spatie/laravel-backup): database + uploaded files, old copies pruned
Schedule::command('backup:clean')->dailyAt('01:00')->timezone('Asia/Dhaka');
Schedule::command('backup:run')->dailyAt('01:30')->timezone('Asia/Dhaka');
Schedule::command('backup:monitor')->dailyAt('03:00')->timezone('Asia/Dhaka');
