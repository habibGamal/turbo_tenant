<?php

// Only exact, randomly generated test addresses are accepted, even on cleanup.
chdir(__DIR__.'/../../..');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('local', 'testing')) exit(2);
$tenant = App\Models\Tenant::find('e2e');
if (! $tenant || ! $tenant->domains()->where('domain', 'e2e.localhost')->exists()) exit(3);
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$emails = $input['emails'] ?? [];
foreach ($emails as $email) {
    if (! preg_match('/^auth-e2e-[a-f0-9-]+@example\.test$/D', $email)) exit(4);
}
$tenant->run(function () use ($input, $emails): void {
    if ($input['action'] === 'create') {
        if (count($emails) !== 1) exit(5);
        App\Models\User::create(['name' => 'Auth E2E Customer', 'email' => $emails[0], 'email_verified_at' => now(), 'is_admin' => false, 'password' => Illuminate\Support\Facades\Hash::make('ValidPass123!')]);
        return;
    }
    if ($input['action'] === 'inspect') {
        if (count($emails) !== 1) exit(5);
        $user = App\Models\User::where('email', $emails[0])->firstOrFail();
        echo json_encode(['verified' => $user->email_verified_at !== null, 'admin' => (bool) $user->is_admin], JSON_THROW_ON_ERROR);
        return;
    }
    if (in_array($input['action'], ['expire-reset', 'age-reset'], true)) {
        if (count($emails) !== 1) exit(5);
        $broker = config('auth.defaults.passwords');
        $minutes = (int) config("auth.passwords.{$broker}.expire", 60);
        $seconds = $input['action'] === 'expire-reset' ? ($minutes + 1) * 60 : (int) config("auth.passwords.{$broker}.throttle", 60) + 1;
        if ($input['action'] === 'age-reset' && $seconds >= $minutes * 60) exit(8);
        Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $emails[0])->update(['created_at' => now()->subSeconds($seconds)]);
        return;
    }
    if ($input['action'] !== 'cleanup') exit(6);
    Illuminate\Support\Facades\DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
    $users = App\Models\User::whereIn('email', $emails)->get();
    foreach ($users as $user) {
        if (config('session.driver') === 'database') {
            $connection = config('session.connection');
            if ($connection !== null && $connection !== 'tenant') exit(7);
            Illuminate\Support\Facades\DB::connection($connection)->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
        Illuminate\Support\Facades\DB::table('addresses')->where('user_id', $user->id)->delete();
        $user->delete();
    }
});
