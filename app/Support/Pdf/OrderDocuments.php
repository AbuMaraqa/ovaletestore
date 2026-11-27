<?php

namespace App\Support\Pdf;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Order;
use App\Queries\CompanyStatement;
use App\Settings\ContactSettings;
use App\Settings\GeneralSettings;
use App\Settings\InvoiceSettings;
use App\Support\Money;
use App\Support\SiteFont;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;
use Mccarlosen\LaravelMpdf\LaravelMpdf;

/**
 * Every printed document, rendered with laravel-mpdf on one shared letterhead (pdf/layout):
 *  - invoice (A4, in the order's language), issued when the order is confirmed;
 *  - sales order (A4) for POS company orders, available as soon as the rep places the order;
 *  - company account statement (A4);
 *  - 10×15 cm shipping label for the courier.
 * They use the site font chosen in Settings → General (when it is a TTF/OTF and applies to the language).
 */
final class OrderDocuments
{
    public static function invoice(Order $order): LaravelMpdf
    {
        $invoice = $order->invoice()->first(); // null only for a preview of an order not invoiced yet
        $locale = self::localeOf($order);

        return self::render('pdf.invoice', $locale, [
            'docTitle' => __('invoices.title', [], $locale),
            'docMeta' => array_filter([
                __('invoices.number', [], $locale) => $invoice->number ?? null,
                __('invoices.date', [], $locale) => ($invoice->issued_at ?? $order->placed_at)->format('Y-m-d'),
                __('invoices.order', [], $locale) => $order->number,
            ]),
            ...self::orderData($order, $locale),
        ], [
            'title' => $invoice->number ?? $order->number, // ?? also covers a null $invoice
            'show_watermark' => $order->status === OrderStatus::Cancelled,
            'watermark' => $order->status === OrderStatus::Cancelled ? __('invoices.cancelled', [], $locale) : '',
        ]);
    }

    /** POS: the company's copy of what was ordered, with signature and stamp boxes (before any invoice). */
    public static function salesOrder(Order $order): LaravelMpdf
    {
        $locale = 'ar';

        return self::render('pdf.invoice', $locale, [
            'docTitle' => __('invoices.sales_order', [], $locale),
            'docMeta' => [
                __('invoices.order', [], $locale) => $order->number,
                __('invoices.date', [], $locale) => $order->placed_at->format('Y-m-d H:i'),
            ],
            ...self::orderData($order, $locale),
        ], [
            'title' => $order->number,
            'show_watermark' => $order->status === OrderStatus::Cancelled,
            'watermark' => $order->status === OrderStatus::Cancelled ? __('invoices.cancelled', [], $locale) : '',
        ]);
    }

    public static function statement(Company $company): LaravelMpdf
    {
        $locale = 'ar';
        $entries = app(CompanyStatement::class)->entries($company);
        $currency = Money::storeCurrency();

        return self::render('pdf.statement', $locale, [
            'docTitle' => __('invoices.statement', [], $locale),
            'docMeta' => [
                __('invoices.date', [], $locale) => now()->format('Y-m-d'),
                __('invoices.statement_entries', [], $locale) => (string) count($entries),
            ],
            'company' => $company,
            'entries' => $entries,
            'totals' => [
                'debit' => array_sum(array_column($entries, 'debit')),
                'credit' => array_sum(array_column($entries, 'credit')),
                'balance' => $entries === [] ? 0 : $entries[array_key_last($entries)]['balance'],
            ],
            'money' => fn (int $minor) => Money::format($minor, $currency, $locale),
        ], ['title' => __('invoices.statement', [], $locale).' — '.$company->name]);
    }

    public static function shippingLabel(Order $order): LaravelMpdf
    {
        return self::shippingLabels([$order]);
    }

    /** One 10×15 label page per order (bulk printing from the orders list). @param iterable<Order> $orders */
    public static function shippingLabels(iterable $orders): LaravelMpdf
    {
        $labels = [];
        foreach ($orders as $order) {
            $order->loadMissing('items');
            $labels[] = [
                'order' => $order,
                'pieces' => (int) $order->items->sum('quantity'),
                'collect' => $order->payment_method === PaymentMethod::CashOnDelivery ? Money::format($order->total, $order->currency, 'ar') : null,
            ];
        }

        return self::render('pdf.shipping-label', 'ar', ['labels' => $labels], [
            'format' => [100, 150],
            'margin_left' => 4, 'margin_right' => 4, 'margin_top' => 4, 'margin_bottom' => 4, 'margin_footer' => 0,
            'default_font_size' => '9',
            'title' => count($labels) === 1 ? $labels[0]['order']->number : 'labels',
        ]);
    }

    /** @return array<string, mixed> */
    private static function orderData(Order $order, string $locale): array
    {
        $order->loadMissing(['items', 'company', 'salesRep:id,name']);

        return [
            'order' => $order,
            'money' => fn (int $minor) => Money::format($minor, $order->currency, $locale),
        ];
    }

    /**
     * Renders a view in a language with the shared business data and the site font.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $config
     */
    private static function render(string $view, string $locale, array $data, array $config = []): LaravelMpdf
    {
        $font = SiteFont::pdfFont($locale);
        $config = [
            ...$config,
            // With the site font, mPDF must not swap Arabic runs to its built-in Arabic face (auto language detection).
            ...($font ? ['custom_font_dir' => $font['dir'], 'custom_font_data' => [$font['key'] => $font['files']], 'auto_language_detection' => false] : []),
        ];

        return self::inLocale($locale, fn () => Pdf::loadView($view, [
            ...self::business($locale),
            'dir' => $locale === 'en' ? 'ltr' : 'rtl',
            'fontFamily' => $font['key'] ?? 'xbriyaz',
            ...$data,
        ], [], $config));
    }

    private static function localeOf(Order $order): string
    {
        return in_array($order->locale, ['ar', 'en', 'he'], true) ? $order->locale : 'ar';
    }

    /**
     * Renders in a given language and restores the request's afterwards.
     *
     * @template T
     *
     * @param  callable(): T  $render
     * @return T
     */
    private static function inLocale(string $locale, callable $render): mixed
    {
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            return $render();
        } finally {
            app()->setLocale($previous);
        }
    }

    /** @return array{businessName: string, registration: ?string, address: ?string, phone: ?string, email: ?string, footer: ?string, logo: ?string} */
    private static function business(string $locale): array
    {
        $invoice = app(InvoiceSettings::class);
        $general = app(GeneralSettings::class);
        $contact = app(ContactSettings::class);
        $logo = $general->logo_path ? storage_path('app/public/'.$general->logo_path) : null;

        return [
            'businessName' => $invoice->business_name ?: $general->siteName($locale),
            'registration' => $invoice->registration_number,
            'address' => $invoice->address,
            'phone' => $invoice->phone ?: $contact->phone,
            'email' => $contact->email,
            'footer' => $invoice->footer_note[$locale] ?? $invoice->footer_note['ar'] ?? null,
            'logo' => $logo && is_file($logo) ? $logo : null,
        ];
    }
}
