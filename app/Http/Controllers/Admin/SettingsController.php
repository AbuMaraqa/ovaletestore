<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Settings\ContactSettings;
use App\Settings\GeneralSettings;
use App\Settings\InvoiceSettings;
use App\Settings\MailSettings;
use App\Settings\SeoSettings;
use App\Settings\StoreSettings;
use App\Support\ActivityLog;
use App\Support\MailServer;
use App\Support\Money;
use App\Support\SiteFont;
use App\Support\SiteIcons;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelSettings\Settings;
use Throwable;

/** One controller, five typed settings groups. Secrets never live here (they stay in .env). */
class SettingsController extends Controller
{
    public const GROUPS = ['general', 'contact', 'store', 'seo', 'mail', 'invoice'];

    /** @var array<string, class-string<Settings>> */
    public const SETTINGS = [
        'general' => GeneralSettings::class,
        'contact' => ContactSettings::class,
        'store' => StoreSettings::class,
        'seo' => SeoSettings::class,
        'mail' => MailSettings::class,
        'invoice' => InvoiceSettings::class,
    ];

    public const SOCIAL_NETWORKS = ['facebook', 'instagram', 'snapchat', 'tiktok', 'telegram'];

    public function edit(string $group): Response
    {
        $this->authorize("settings.{$group}");

        return Inertia::render('Admin/Settings/Edit', [
            'group' => $group,
            'groups' => array_values(array_filter(self::GROUPS, fn (string $g) => request()->user()->can("settings.{$g}"))),
            'values' => $this->values($group),
            'locales' => config('translatable.locales'),
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $this->authorize("settings.{$group}");

        $settings = app(self::SETTINGS[$group]);
        $before = $settings->toArray();

        match ($group) {
            'general' => $this->updateGeneral($request, $settings),
            'contact' => $this->updateContact($request, $settings),
            'store' => $this->updateStore($request, $settings),
            'seo' => $this->updateSeo($request, $settings),
            'mail' => $this->updateMail($request, $settings),
            'invoice' => $this->updateInvoice($request, $settings),
        };

        ActivityLog::settingsChanged($group, $before, $settings->toArray());

        return back()->with('success', __('admin.updated'));
    }

    private function values(string $group): array
    {
        $url = fn (?string $path) => $path ? asset('storage/'.$path) : null;

        return match ($group) {
            'general' => ($s = app(GeneralSettings::class)) ? [
                'site_name' => $s->site_name, 'tagline' => $s->tagline,
                'logo' => $url($s->logo_path), 'favicon' => $url($s->favicon_path),
                'font_family' => $s->font_family, 'font_name' => $s->font_name, 'font_scope' => $s->font_scope, 'font_locales' => $s->font_locales,
                'font_files' => array_map(fn ($path) => $url($path), $s->font_files),
            ] : [],
            'contact' => ($s = app(ContactSettings::class)) ? [
                'phone' => $s->phone, 'whatsapp' => $s->whatsapp, 'email' => $s->email,
                'social_links' => $s->social_links, 'phone_lines' => $s->phone_lines,
                'locations' => array_map(fn (array $l) => [
                    'name' => $l['name'] ?? [], 'address' => $l['address'] ?? [],
                    'coordinates' => is_numeric($l['lat'] ?? null) ? $l['lat'].', '.$l['lng'] : '',
                ], $s->locations),
            ] : [],
            'store' => ($s = app(StoreSettings::class)) ? [
                'products_per_page' => $s->products_per_page,
                'show_out_of_stock' => $s->show_out_of_stock, 'guest_checkout' => $s->guest_checkout,
                'min_order_total' => Money::toMajor($s->min_order_total),
            ] : [],
            'seo' => ($s = app(SeoSettings::class)) ? [
                'home_title' => $s->home_title, 'home_description' => $s->home_description,
                'og_image' => $url($s->og_image_path), 'allow_indexing' => $s->allow_indexing,
            ] : [],
            'mail' => $this->mailValues(app(MailSettings::class)),
            'invoice' => $this->invoiceValues(app(InvoiceSettings::class)),
        };
    }

    private function updateMail(Request $request, MailSettings $settings): void
    {
        $data = $request->validate([
            'from_name' => ['nullable', 'string', 'max:120'],
            'from_address' => ['nullable', 'email:rfc', 'max:190'],
            'order_recipients' => ['nullable', 'string', 'max:2000'],
            'notify_new_order' => ['boolean'],
            'customer_confirmation' => ['boolean'],
            'customer_status_updates' => ['boolean'],
            'smtp_enabled' => ['boolean'],
            'smtp_host' => ['nullable', 'required_if_accepted:smtp_enabled', 'string', 'max:190', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'smtp_port' => ['nullable', 'required_if_accepted:smtp_enabled', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl,none'],
            'smtp_username' => ['nullable', 'string', 'max:190'],
            'smtp_password' => ['nullable', 'string', 'max:190'],
            'smtp_password_clear' => ['boolean'],
        ]);

        // One address per line (commas and semicolons accepted too); every one must be valid.
        $recipients = collect(preg_split('/[\s,;]+/', (string) ($data['order_recipients'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $email) => strtolower($email))->unique()->values();
        $invalid = $recipients->first(fn (string $email) => ! filter_var($email, FILTER_VALIDATE_EMAIL));
        if ($invalid !== null || $recipients->count() > 10) {
            throw ValidationException::withMessages(['order_recipients' => __('admin.invalid_recipients', ['email' => $invalid ?? ''])]);
        }

        $settings->from_name = ($data['from_name'] ?? null) ?: null;
        $settings->from_address = ($data['from_address'] ?? null) ?: null;
        $settings->order_recipients = $recipients->all();
        $settings->notify_new_order = (bool) ($data['notify_new_order'] ?? false);
        $settings->customer_confirmation = (bool) ($data['customer_confirmation'] ?? false);
        $settings->customer_status_updates = (bool) ($data['customer_status_updates'] ?? false);

        $smtpBefore = [$settings->smtp_enabled, $settings->smtp_host, $settings->smtp_port, $settings->smtp_encryption, $settings->smtp_username, $settings->smtp_password];
        $settings->smtp_enabled = (bool) ($data['smtp_enabled'] ?? false);
        $settings->smtp_host = ($data['smtp_host'] ?? null) ?: null;
        $settings->smtp_port = (int) ($data['smtp_port'] ?? null ?: 587);
        $settings->smtp_encryption = $data['smtp_encryption'] ?? 'tls';
        $settings->smtp_username = ($data['smtp_username'] ?? null) ?: null;
        // An empty password field keeps the saved one (it is never sent to the browser).
        if ($data['smtp_password_clear'] ?? false) {
            $settings->smtp_password = null;
        } elseif (filled($data['smtp_password'] ?? null)) {
            $settings->smtp_password = $data['smtp_password'];
        }
        $settings->save();

        // Long-running queue workers resolved the old mailer: ask them to restart and pick up the new server.
        $smtpAfter = [$settings->smtp_enabled, $settings->smtp_host, $settings->smtp_port, $settings->smtp_encryption, $settings->smtp_username, $settings->smtp_password];
        if ($smtpBefore !== $smtpAfter) {
            Artisan::call('queue:restart');
        }
    }

    /** Sends a test message right away (not queued) so a wrong server setting shows up immediately. */
    public function testMail(Request $request, MailSettings $settings, GeneralSettings $general): RedirectResponse
    {
        $this->authorize('settings.mail');
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190']]);

        try {
            Mail::raw(__('mail.test_body'), function ($message) use ($data, $settings, $general) {
                $message->to($data['email'])
                    ->from($settings->from_address ?: (string) config('mail.from.address'), $settings->from_name ?: $general->siteName())
                    ->subject(__('mail.subject_test', ['site' => $general->siteName()]));
            });
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('admin.test_mail_failed', ['error' => class_basename($e)]));
        }

        return back()->with('success', __('admin.test_mail_sent', ['email' => $data['email']]));
    }

    private function mailValues(MailSettings $s): array
    {
        MailServer::apply(); // show where mail really goes (admin SMTP over .env)

        return [
            'smtp_enabled' => $s->smtp_enabled, 'smtp_host' => $s->smtp_host, 'smtp_port' => $s->smtp_port,
            'smtp_encryption' => $s->smtp_encryption, 'smtp_username' => $s->smtp_username,
            'smtp_password_set' => filled($s->smtp_password), // never the password itself
            'from_name' => $s->from_name, 'from_address' => $s->from_address,
            'order_recipients' => implode("\n", $s->order_recipients),
            'notify_new_order' => $s->notify_new_order, 'customer_confirmation' => $s->customer_confirmation,
            'customer_status_updates' => $s->customer_status_updates,
            // Read-only: where mail actually goes out (never the password).
            'transport' => [
                'mailer' => (string) config('mail.default'),
                'host' => config('mail.mailers.'.config('mail.default').'.host'),
                'default_from' => (string) config('mail.from.address'),
                'delivers' => ! in_array(config('mail.default'), ['log', 'array'], true),
            ],
        ];
    }

    private function localeRules(string $field, string $rule, bool $requireArabic = true): array
    {
        return collect(config('translatable.locales'))
            ->mapWithKeys(fn ($l) => ["{$field}.{$l}" => ($l === 'ar' && $requireArabic ? 'required|' : 'nullable|').$rule])
            ->all();
    }

    private function updateGeneral(Request $request, GeneralSettings $settings): void
    {
        $data = $request->validate([
            ...$this->localeRules('site_name', 'string|max:80'),
            ...$this->localeRules('tagline', 'string|max:160'),
            'logo' => ['nullable', 'file', 'max:2048', 'mimetypes:image/png,image/webp,image/jpeg'],
            'favicon' => ['nullable', 'file', 'max:512', 'mimetypes:image/png,image/x-icon,image/vnd.microsoft.icon'],
            'font_family' => ['required', 'in:tajawal,custom'],
            'font_name' => ['nullable', 'required_if:font_family,custom', 'string', 'max:60', 'regex:'.SiteFont::NAME_PATTERN],
            'font_scope' => ['required', 'in:all,storefront'],
            'font_locales' => ['required_if:font_family,custom', 'array'],
            'font_locales.*' => ['string', Rule::in(config('translatable.locales'))],
            ...collect(SiteFont::WEIGHTS)->mapWithKeys(fn (string $field) => [$field => ['nullable', 'file', 'max:4096', 'extensions:woff2,woff,ttf,otf',
                function (string $attribute, mixed $file, Closure $fail): void {
                    if ($file instanceof UploadedFile && SiteFont::detect($file) === null) {
                        $fail(__('admin.font_invalid_file'));
                    }
                }]])->all(),
        ]);

        // A custom font needs at least its regular weight (checked before anything is stored).
        if ($data['font_family'] === 'custom' && empty($settings->font_files[400]) && ! $request->hasFile('font_regular')) {
            throw ValidationException::withMessages(['font_regular' => __('admin.font_regular_required')]);
        }
        $files = $settings->font_files;
        foreach (SiteFont::WEIGHTS as $weight => $field) {
            if ($file = $request->file($field)) {
                $files[$weight] = $this->storeFont($file, $files[$weight] ?? null);
            }
        }

        $settings->site_name = array_map('strval', array_filter($data['site_name']));
        $settings->tagline = array_map('strval', array_filter($data['tagline'] ?? []));
        $settings->logo_path = $this->storeAsset($request->file('logo'), 'branding', $settings->logo_path);
        $settings->favicon_path = $this->storeAsset($request->file('favicon'), 'branding', $settings->favicon_path);
        $iconsChanged = $request->hasFile('logo') || $request->hasFile('favicon');
        $settings->font_family = $data['font_family'];
        $settings->font_name = $data['font_name'] ?? $settings->font_name;
        $settings->font_scope = $data['font_scope'];
        $settings->font_locales = array_values(array_unique($data['font_locales'] ?? $settings->font_locales));
        $settings->font_files = $files;
        $settings->save();

        if ($iconsChanged) {
            SiteIcons::generate(); // favicon + home-screen icons follow the new logo/favicon
        }
    }

    private function updateContact(Request $request, ContactSettings $settings): void
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:32'],
            'whatsapp' => ['nullable', 'regex:/^[0-9]{8,15}$/'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'social_links' => ['array'],
            'social_links.*' => ['nullable', 'url:https', 'max:300'],
            'phone_lines' => ['array', 'max:12'],
            'phone_lines.*.number' => ['required', 'string', 'regex:/^\+?[0-9][0-9 \-]{5,19}$/'],
            ...$this->localeRules('phone_lines.*.label', 'string|max:120', requireArabic: false),
            'locations' => ['array', 'max:12'],
            ...$this->localeRules('locations.*.name', 'string|max:120'),
            ...$this->localeRules('locations.*.address', 'string|max:300'),
            // "31.507, 35.0374" — what Google Maps copies when you click a point.
            'locations.*.coordinates' => ['nullable', 'string', 'regex:/^\s*-?\d{1,2}(\.\d+)?\s*,\s*-?\d{1,3}(\.\d+)?\s*$/'],
        ]);

        $settings->phone = $data['phone'] ?? null;
        $settings->whatsapp = $data['whatsapp'] ?? null;
        $settings->email = $data['email'] ?? null;
        $settings->social_links = collect(self::SOCIAL_NETWORKS)
            ->mapWithKeys(fn ($k) => [$k => $data['social_links'][$k] ?? null])->all();
        $settings->phone_lines = array_values(array_map(fn (array $line) => [
            'number' => preg_replace('/[\s\-]/', '', $line['number']),
            'label' => $this->cleanLocalized($line['label'] ?? []),
        ], $data['phone_lines'] ?? []));
        $settings->locations = array_values(array_map(fn (array $location) => [
            'name' => $this->cleanLocalized($location['name'] ?? []),
            'address' => $this->cleanLocalized($location['address'] ?? []),
            ...$this->coordinates($location['coordinates'] ?? null),
        ], $data['locations'] ?? []));
        $settings->save();
    }

    /**
     * "31.507, 35.0374" → lat/lng; empty or out of range → no pin (the map then searches the address).
     *
     * @return array{lat: float|null, lng: float|null}
     */
    private function coordinates(?string $value): array
    {
        $parts = filled($value) ? explode(',', (string) $value, 2) : [];
        if (count($parts) !== 2) {
            return ['lat' => null, 'lng' => null];
        }

        $lat = round((float) trim($parts[0]), 7);
        $lng = round((float) trim($parts[1]), 7);

        return abs($lat) <= 90 && abs($lng) <= 180 ? ['lat' => $lat, 'lng' => $lng] : ['lat' => null, 'lng' => null];
    }

    /** @return array<string, string> */
    private function cleanLocalized(array $values): array
    {
        return array_map('strval', array_filter(array_intersect_key($values, array_flip(config('translatable.locales')))));
    }

    private function updateStore(Request $request, StoreSettings $settings): void
    {
        $data = $request->validate([
            'products_per_page' => ['required', 'integer', 'min:8', 'max:96'],
            'show_out_of_stock' => ['boolean'],
            'guest_checkout' => ['boolean'],
            'min_order_total' => ['nullable', 'numeric', 'min:0'],
        ]);

        $settings->products_per_page = (int) $data['products_per_page'];
        $settings->show_out_of_stock = (bool) ($data['show_out_of_stock'] ?? false);
        $settings->guest_checkout = (bool) ($data['guest_checkout'] ?? true);
        $settings->min_order_total = (int) Money::toMinor($data['min_order_total'] ?? 0);
        $settings->save();
    }

    private function invoiceValues(InvoiceSettings $s): array
    {
        return [
            'business_name' => $s->business_name, 'registration_number' => $s->registration_number,
            'address' => $s->address, 'phone' => $s->phone, 'footer_note' => $s->footer_note,
        ];
    }

    private function updateInvoice(Request $request, InvoiceSettings $settings): void
    {
        $data = $request->validate([
            'business_name' => ['nullable', 'string', 'max:120'],
            'registration_number' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:32'],
            ...$this->localeRules('footer_note', 'string|max:300', requireArabic: false),
        ]);

        $settings->business_name = $data['business_name'] ?? null;
        $settings->registration_number = $data['registration_number'] ?? null;
        $settings->address = $data['address'] ?? null;
        $settings->phone = $data['phone'] ?? null;
        $settings->footer_note = array_map('strval', array_filter($data['footer_note'] ?? []));
        $settings->save();
    }

    private function updateSeo(Request $request, SeoSettings $settings): void
    {
        $data = $request->validate([
            ...$this->localeRules('home_title', 'string|max:70'),
            ...$this->localeRules('home_description', 'string|max:170'),
            'og_image' => ['nullable', 'file', 'max:4096', 'mimetypes:image/jpeg,image/png,image/webp', 'dimensions:min_width=600,min_height=315'],
            'allow_indexing' => ['boolean'],
        ]);

        $settings->home_title = array_map('strval', array_filter($data['home_title']));
        $settings->home_description = array_map('strval', array_filter($data['home_description'] ?? []));
        $settings->og_image_path = $this->storeAsset($request->file('og_image'), 'branding', $settings->og_image_path);
        $settings->allow_indexing = (bool) ($data['allow_indexing'] ?? true);
        $settings->save();
    }

    /** Stores a validated upload under a random name on the public disk and removes the previous file. */
    /** Stored under a generated name with the extension of its real format (never the uploaded name). */
    private function storeFont(UploadedFile $file, ?string $current): string
    {
        [$extension] = SiteFont::detect($file) ?? ['woff2'];
        $path = $file->storeAs('fonts', Str::random(24).'.'.$extension, 'public');

        if ($current && $current !== $path) {
            Storage::disk('public')->delete($current);
        }

        return (string) $path;
    }

    private function storeAsset(?UploadedFile $file, string $dir, ?string $current): ?string
    {
        if (! $file) {
            return $current;
        }

        $path = $file->storeAs($dir, Str::random(24).'.'.$file->guessExtension(), 'public');

        if ($current && $current !== $path) {
            Storage::disk('public')->delete($current);
        }

        return $path;
    }
}
