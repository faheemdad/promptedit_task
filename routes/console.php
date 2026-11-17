<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('generations:reconcile')->everyMinute()->withoutOverlapping();

Artisan::command('users:make-admin {email}', function (string $email) {
    $updated = User::query()->where('email', $email)->update(['is_admin' => true]);

    $updated ? $this->info("{$email} is now an admin.") : $this->error("No user with email {$email}.");
})->purpose('Grant a user access to the admin report');
