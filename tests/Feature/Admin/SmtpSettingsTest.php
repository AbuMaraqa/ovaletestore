<?php

use App\Settings\MailSettings;
use App\Support\MailServer;
use Illuminate\Support\Facades\DB;

function mailPayload(array $overrides = []): array
{
    return [
        'from_name' => '', 'from_address' => 'orders@ovalete.test', 'order_recipients' => '',
        'notify_new_order' => true, 'customer_confirmation' => true, 'customer_status_updates' => true,
        'smtp_enabled' => true, 'smtp_host' => 'smtp.gmail.com', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
        'smtp_username' => 'store@gmail.com', 'smtp_password' => 'app-pass-1234',
        ...$overrides,
    ];
}

it('saves the SMTP server with the password encrypted and never sent back', function () {
    $this->actingAs(staffUser('admin'));
    $this->put(route('admin.settings.update', 'mail'), mailPayload())->assertRedirect()->assertSessionHasNoErrors();

    $settings = app(MailSettings::class)->refresh();
    expect($settings->smtp_host)->toBe('smtp.gmail.com')->and($settings->smtp_password)->toBe('app-pass-1234');

    $raw = (string) DB::table('settings')->where('group', 'mail')->where('name', 'smtp_password')->value('payload');
    expect($raw)->not->toContain('app-pass-1234');

    $this->get(route('admin.settings.edit', 'mail'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('values.smtp_password_set', true)->missing('values.smtp_password'))
        ->assertDontSee('app-pass-1234', false);
});

it('keeps the saved password when the field is left empty, and removes it on request', function () {
    $this->actingAs(staffUser('admin'));
    $this->put(route('admin.settings.update', 'mail'), mailPayload());

    $this->put(route('admin.settings.update', 'mail'), mailPayload(['smtp_password' => '', 'smtp_port' => 465, 'smtp_encryption' => 'ssl']))->assertSessionHasNoErrors();
    expect(app(MailSettings::class)->refresh()->smtp_password)->toBe('app-pass-1234');

    $this->put(route('admin.settings.update', 'mail'), mailPayload(['smtp_password' => '', 'smtp_password_clear' => true]))->assertSessionHasNoErrors();
    expect(app(MailSettings::class)->refresh()->smtp_password)->toBeNull();
});

it('uses the admin SMTP server for outgoing mail when enabled, and .env otherwise', function () {
    $settings = app(MailSettings::class);
    $settings->smtp_enabled = true;
    $settings->smtp_host = 'mail.example.com';
    $settings->smtp_port = 465;
    $settings->smtp_encryption = 'ssl';
    $settings->smtp_username = 'u@example.com';
    $settings->smtp_password = 'secret';
    $settings->save();

    MailServer::apply();
    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('mail.example.com')
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps')
        ->and(config('mail.mailers.smtp.password'))->toBe('secret');

    config(['mail.default' => 'array']);
    $settings->smtp_enabled = false;
    $settings->save();
    MailServer::apply();
    expect(config('mail.default'))->toBe('array');
});

it('requires a host and port when SMTP is enabled, and keeps the permission check', function () {
    $this->actingAs(staffUser('admin'));
    $this->put(route('admin.settings.update', 'mail'), mailPayload(['smtp_host' => '']))->assertSessionHasErrors('smtp_host');
    $this->put(route('admin.settings.update', 'mail'), mailPayload(['smtp_host' => 'bad host;rm']))->assertSessionHasErrors('smtp_host');

    $this->actingAs(staffUser('content-manager'))->put(route('admin.settings.update', 'mail'), mailPayload())->assertForbidden();
});
