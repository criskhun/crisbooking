<?php

use App\Models\User;
use App\Services\AppNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('user:make-admin {email : Email address of the account to promote}', function (string $email) {
    $email = Str::lower(trim($email));

    $user = User::query()
        ->whereRaw('LOWER(email) = ?', [$email])
        ->first();

    if (! $user) {
        $this->error("No account exists for {$email}. Register or sign in first, then run this command again.");

        return Command::FAILURE;
    }

    $user->forceFill([
        'is_admin' => true,
        'is_active' => true,
    ])->save();

    $this->info("{$user->email} is now an active administrator.");

    return Command::SUCCESS;
})->purpose('Promote an existing account to administrator access');

Artisan::command('notifications:send-email-fallback {--limit=500}', function () {
    $limit = min(5000, max(1, (int) $this->option('limit')));
    $result = app(AppNotificationService::class)->sendUnseenEmailFallbacks($limit);

    $this->info("Sent {$result['sent']} unseen notification email(s); {$result['failed']} failed.");

    return $result['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
})->purpose('Email notifications that recipients have not opened in the app');

Artisan::command('notifications:generate-reminders {--limit=500}', function () {
    $limit = min(5000, max(1, (int) $this->option('limit')));
    $result = app(AppNotificationService::class)->generateBookingReminders($limit);

    $this->info("Created {$result['created']} booking reminder notification(s).");

    return Command::SUCCESS;
})->purpose('Create upcoming-booking and post-booking review reminders');

Schedule::command('notifications:send-email-fallback')->everyMinute()->withoutOverlapping(10);
Schedule::command('notifications:generate-reminders')->everyFifteenMinutes()->withoutOverlapping(10);
