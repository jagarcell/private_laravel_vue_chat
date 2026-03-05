<?php

use App\Services\Users\SyncExpiredSessionPresenceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Synchronize online presence against active sessions and broadcast offline updates.
 */
Artisan::command('presence:sync-expired-sessions', function (SyncExpiredSessionPresenceService $syncService): void {
    $expiredUsersCount = $syncService->handle();

    $this->info("Synced expired sessions. Broadcasted offline updates for {$expiredUsersCount} user(s).");
})->purpose('Broadcast offline status updates for users whose sessions expired');

/**
 * Run presence synchronization every minute.
 */
Schedule::command('presence:sync-expired-sessions')->everyMinute();
