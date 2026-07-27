<?php declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

use Illuminate\Support\Facades\Schedule;

// 終了したオークションを毎分確定し、出品者・落札者へ通知する。
Schedule::command('auctions:close')->everyMinute()->withoutOverlapping();
