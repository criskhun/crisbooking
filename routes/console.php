<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
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
