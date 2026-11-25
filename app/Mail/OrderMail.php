<?php

namespace App\Mail;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Order emails in the WooCommerce tradition: brand header, a heading, the order table, totals and
 * addresses (resources/views/mail). Queued, rendered in the recipient's language and direction.
 *
 * kind: new_order (store inboxes) · confirmation (customer) · status (customer, after a status change)
 */
class OrderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** An order deleted before the mail is sent: drop the job instead of failing it. */
    public bool $deleteWhenMissingModels = true;

    /** @param OrderStatus|null $status the status this mail announces (the order may move on before it is sent) */
    public function __construct(public Order $order, public string $kind, public string $mailLocale = 'ar', public ?OrderStatus $status = null)
    {
        $this->status ??= $order->status;
        $this->locale($mailLocale);
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $settings = app(MailSettings::class);
        $siteName = app(GeneralSettings::class)->siteName($this->mailLocale);
        $address = $settings->from_address ?: (string) config('mail.from.address');

        return new Envelope(
            from: new Address($address, $settings->from_name ?: $siteName),
            replyTo: $this->kind === 'new_order' && $this->order->customer_email ? [new Address($this->order->customer_email, $this->order->customer_name)] : [],
            subject: __("mail.subject_{$this->kind}", [
                'number' => $this->order->number,
                'site' => $siteName,
                'status' => __("orders.status_{$this->status->value}", [], $this->mailLocale),
            ], $this->mailLocale),
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing('items');
        $general = app(GeneralSettings::class);
        $money = fn (int $minor) => Money::format($minor, $this->order->currency, $this->mailLocale);

        return new Content(view: 'mail.order', with: [
            'order' => $this->order,
            'kind' => $this->kind,
            'mailLocale' => $this->mailLocale,
            'dir' => in_array($this->mailLocale, ['ar', 'he'], true) ? 'rtl' : 'ltr',
            'siteName' => $general->siteName($this->mailLocale),
            'tagline' => $general->tagline[$this->mailLocale] ?? $general->tagline['ar'] ?? '',
            'logo' => $general->logo_path ? asset('storage/'.$general->logo_path) : null,
            'money' => $money,
            'status' => $this->status,
            // Queue workers have no request locale, so build /{locale}/… links explicitly.
            'adminUrl' => $this->localized('ar', route('admin.orders.show', $this->order, false)),
            'accountUrl' => $this->order->user_id ? $this->localized($this->mailLocale, route('account.orders.show', ['number' => $this->order->number], false)) : null,
        ]);
    }

    private function localized(string $locale, string $path): string
    {
        $path = '/'.ltrim((string) preg_replace('#^/(ar|en|he)(?=/|$)#', '', $path), '/');

        return url($locale.($path === '/' ? '' : $path));
    }
}
