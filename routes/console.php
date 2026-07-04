<?php

declare(strict_types=1);

use App\Jobs\Erp\CheckAwaitingSupplierQuotesJob;
use App\Jobs\Erp\CheckExpiredQuotesJob;
use App\Jobs\Erp\CheckExpiringQuotesJob;
use App\Jobs\Erp\CheckOverdueInvoicesJob;
use Illuminate\Support\Facades\Schedule;

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

// ERP Alert & Notification Jobs
// These jobs run daily to check for items requiring attention

// Check for buyer quotes expiring in 7, 3, and 1 days
Schedule::job(new CheckExpiringQuotesJob)
    ->dailyAt('08:00')
    ->name('erp:check-expiring-quotes')
    ->withoutOverlapping()
    ->onOneServer();

// Check for buyer quotes that expired yesterday; email buyer and key accounts
Schedule::job(new CheckExpiredQuotesJob)
    ->dailyAt('08:30')
    ->name('erp:check-expired-quotes')
    ->withoutOverlapping()
    ->onOneServer();

// Check for overdue invoices and update status
Schedule::job(new CheckOverdueInvoicesJob)
    ->dailyAt('09:00')
    ->name('erp:check-overdue-invoices')
    ->withoutOverlapping()
    ->onOneServer();

// Check for supplier quotes awaiting response for more than 7 days
Schedule::job(new CheckAwaitingSupplierQuotesJob)
    ->dailyAt('10:00')
    ->name('erp:check-awaiting-supplier-quotes')
    ->withoutOverlapping()
    ->onOneServer();

// Recompute price_review_needed on published articles (FX drift, quoted-price changes)
Schedule::command('articles:refresh-price-review')
    ->dailyAt('07:00')
    ->name('articles:refresh-price-review')
    ->withoutOverlapping()
    ->onOneServer();
