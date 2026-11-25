<?php

namespace App\Mail;

use App\Models\User;
use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "Your trader account is approved" — same look as the order emails, in the trader's language. */
class TraderApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public User $trader, public string $mailLocale = 'ar')
    {
        $this->locale($mailLocale);
    }

    public function envelope(): Envelope
    {
        $settings = app(MailSettings::class);
        $siteName = app(GeneralSettings::class)->siteName($this->mailLocale);

        return new Envelope(
            from: new Address($settings->from_address ?: (string) config('mail.from.address'), $settings->from_name ?: $siteName),
            subject: __('traders.mail_subject', ['site' => $siteName], $this->mailLocale),
        );
    }

    public function content(): Content
    {
        $general = app(GeneralSettings::class);

        return new Content(view: 'mail.trader-approved', with: [
            'trader' => $this->trader,
            'tier' => $this->trader->priceTier?->label($this->mailLocale),
            'mailLocale' => $this->mailLocale,
            'dir' => in_array($this->mailLocale, ['ar', 'he'], true) ? 'rtl' : 'ltr',
            'siteName' => $general->siteName($this->mailLocale),
            'logo' => $general->logo_path ? asset('storage/'.$general->logo_path) : null,
            'shopUrl' => url($this->mailLocale.'/shop'),
        ]);
    }
}
