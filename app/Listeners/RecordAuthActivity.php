<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Sign-ins, sign-outs, password resets and failed sign-in attempts go to the "auth" log
 * (the IP and browser are added to every entry by AppServiceProvider).
 */
class RecordAuthActivity
{
    public function handleLogin(Login $event): void
    {
        $this->record('login', $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        $this->record('logout', $event->user);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->record('password_reset', $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        activity('auth')
            ->event('login_failed')
            ->causedByAnonymous()
            ->withProperties(['label' => $this->safeIdentifier($event->credentials['email'] ?? null)])
            ->tap(fn ($activity) => $event->user instanceof User ? $activity->subject()->associate($event->user) : null)
            ->log('login_failed');
    }

    /** People sometimes type their password into the email box: store it only if it is an email. */
    private function safeIdentifier(mixed $value): string
    {
        $value = trim((string) $value);

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? mb_substr(strtolower($value), 0, 190) : '(invalid)';
    }

    private function record(string $event, mixed $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        activity('auth')
            ->event($event)
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties(['label' => $user->name])
            ->log($event);
    }
}
