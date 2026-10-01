<?php

use App\Conversations\Commands\DeleteTestConversationsCommand;
use App\Conversations\Email\Commands\ImportEmailsViaImap;
use App\Conversations\Email\Commands\RefreshGmailSubscription;
use App\Webhooks\Controllers\GmailWebhookController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('helpdesk:gmail-webhook-url', function () {
    $this->info(
        url('tickets/mail/incoming/gmail') .
            '?token=' .
            GmailWebhookController::expectedToken(),
    );
})->purpose('Print the authenticated Gmail Pub/Sub push endpoint');

if ($imapConnections = settings('incoming_email.imap.connections')) {
    foreach ($imapConnections as $connection) {
        if (
            $connection['createTickets'] ||
            $connection['createReplies']
        ) {
            Schedule::command(ImportEmailsViaImap::class, [$connection['id']])
                ->everyMinute()
                ->withoutOverlapping(1);
        }
    }
}

if (settings('incoming_email.gmail.enabled')) {
    Schedule::command(RefreshGmailSubscription::class)
        ->daily()
        ->withoutOverlapping();
}

// shared hosting friendly queue: drain the database queue from the single
// cron entry (no long running worker needed)
if (config('queue.default') !== 'sync') {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
        ->everyMinute()
        ->withoutOverlapping(2);
}

Schedule::command(DeleteTestConversationsCommand::class)
    ->hourly();

