<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Settings\ContactSettings;
use App\Settings\GeneralSettings;
use App\Settings\SeoSettings;
use App\Settings\StoreSettings;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** One controller, four typed settings groups. Secrets never live here (they stay in .env). */
class SettingsController extends Controller
{
    public const GROUPS = ['general', 'contact', 'store', 'seo'];

    public const SOCIAL_NETWORKS = ['facebook', 'instagram', 'snapchat', 'tiktok', 'telegram'];

    public function edit(string $group): Response
    {
        $this->authorize('settings.manage');

        return Inertia::render('Admin/Settings/Edit', [
            'group' => $group,
            'groups' => self::GROUPS,
            'values' => $this->values($group),
            'locales' => config('translatable.locales'),
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $this->authorize('settings.manage');

        match ($group) {
            'general' => $this->updateGeneral($request, app(GeneralSettings::class)),
            'contact' => $this->updateContact($request, app(ContactSettings::class)),
            'store' => $this->updateStore($request, app(StoreSettings::class)),
            'seo' => $this->updateSeo($request, app(SeoSettings::class)),
        };

        return back()->with('success', __('admin.updated'));
    }

    private function values(string $group): array
    {
        $url = fn (?string $path) => $path ? asset('storage/'.$path) : null;

        return match ($group) {
            'general' => ($s = app(GeneralSettings::class)) ? [
                'site_name' => $s->site_name, 'tagline' => $s->tagline,
                'logo' => $url($s->logo_path), 'favicon' => $url($s->favicon_path),
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
                'currency' => $s->currency, 'products_per_page' => $s->products_per_page,
                'show_out_of_stock' => $s->show_out_of_stock, 'guest_checkout' => $s->guest_checkout,
                'min_order_total' => Money::toMajor($s->min_order_total),
            ] : [],
            'seo' => ($s = app(SeoSettings::class)) ? [
                'home_title' => $s->home_title, 'home_description' => $s->home_description,
                'og_image' => $url($s->og_image_path), 'allow_indexing' => $s->allow_indexing,
            ] : [],
        };
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
        ]);

        $settings->site_name = array_map('strval', array_filter($data['site_name']));
        $settings->tagline = array_map('strval', array_filter($data['tagline'] ?? []));
        $settings->logo_path = $this->storeAsset($request->file('logo'), 'branding', $settings->logo_path);
        $settings->favicon_path = $this->storeAsset($request->file('favicon'), 'branding', $settings->favicon_path);
        $settings->save();
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
            'currency' => ['required', 'in:ILS,USD,JOD'],
            'products_per_page' => ['required', 'integer', 'min:8', 'max:96'],
            'show_out_of_stock' => ['boolean'],
            'guest_checkout' => ['boolean'],
            'min_order_total' => ['nullable', 'numeric', 'min:0'],
        ]);

        $settings->currency = $data['currency'];
        $settings->products_per_page = (int) $data['products_per_page'];
        $settings->show_out_of_stock = (bool) ($data['show_out_of_stock'] ?? false);
        $settings->guest_checkout = (bool) ($data['guest_checkout'] ?? true);
        $settings->min_order_total = (int) Money::toMinor($data['min_order_total'] ?? 0);
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
