<?php

use App\Settings\ContactSettings;
use Inertia\Testing\AssertableInertia as Assert;

function contactPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'phone' => '+972593387610',
        'whatsapp' => '972593387610',
        'phone_lines' => [
            ['number' => '+972 599-955929', 'label' => ['ar' => 'لأسعار الجملة', 'en' => 'Wholesale', 'he' => '']],
        ],
        'locations' => [
            ['name' => ['ar' => 'فرع دورا', 'en' => 'Dura', 'he' => ''], 'address' => ['ar' => 'دوار العلم', 'en' => '', 'he' => ''], 'coordinates' => '31.507, 35.037417'],
            ['name' => ['ar' => 'فرع بيت لحم'], 'address' => ['ar' => 'بيت لحم، الدوحة'], 'coordinates' => ''],
        ],
    ], $overrides);
}

it('ships the original footer locations and labelled numbers', function () {
    $contact = app(ContactSettings::class);

    expect($contact->locations)->toHaveCount(2)
        ->and($contact->locations[0]['lat'])->toBe(31.507)
        ->and(collect($contact->phone_lines)->pluck('number')->all())
        ->toBe(['+972593387610', '+972599955929', '+972599958890', '+972599955288']);
});

it('lets an admin edit locations and labelled phone numbers', function () {
    $this->actingAs(staffUser('admin'));

    $this->put(route('admin.settings.update', 'contact'), contactPayload())->assertSessionHasNoErrors();

    $contact = app(ContactSettings::class)->refresh();
    expect($contact->phone_lines)->toBe([['number' => '+972599955929', 'label' => ['ar' => 'لأسعار الجملة', 'en' => 'Wholesale']]])
        ->and($contact->locations[0])->toMatchArray(['lat' => 31.507, 'lng' => 35.037417])
        ->and($contact->locations[1])->toMatchArray(['lat' => null, 'lng' => null, 'name' => ['ar' => 'فرع بيت لحم']]);
});

it('validates coordinates, phone numbers and the Arabic branch name', function () {
    $this->actingAs(staffUser('admin'));

    $this->put(route('admin.settings.update', 'contact'), contactPayload([
        'phone_lines' => [['number' => 'call me']],
        'locations' => [['name' => ['ar' => ''], 'coordinates' => 'Dura']],
    ]))->assertSessionHasErrors(['phone_lines.0.number', 'locations.0.name.ar', 'locations.0.coordinates']);
});

it('only lets settings managers change contact details', function () {
    $this->actingAs(staffUser('sales-rep'));

    $this->put(route('admin.settings.update', 'contact'), contactPayload())->assertForbidden();
});

it('shows localized locations with map links in the footer and on the contact page', function () {
    $this->actingAs(staffUser('admin'))->put(route('admin.settings.update', 'contact'), contactPayload());
    auth()->logout();
    $this->useRouteLocale('he');

    $this->get(route('storefront.contact'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('site.locations.0.name', 'Dura') // he → en fallback
            ->where('site.locations.0.directions', 'https://www.google.com/maps/dir/?api=1&destination=31.507%2C35.037417')
            ->where('site.locations.1.name', 'فرع بيت لحم') // he → en → ar
            ->where('site.phone_lines.0.label', 'Wholesale')
            ->where('contact.locations.0.map_embed', fn (string $url) => str_starts_with($url, 'https://www.google.com/maps?q=31.507%2C35.037417'))
            ->where('contact.locations.1.map_embed', fn (string $url) => str_contains($url, 'output=embed')));
});

it('allows only the Google Maps embed as a frame source', function () {
    config(['csp.enabled' => true]); // off by default in phpunit.xml
    $csp = (string) $this->get(route('storefront.home'))->headers->get('Content-Security-Policy');

    expect($csp)->toContain('frame-src https://www.google.com')
        ->and($csp)->toContain("frame-ancestors 'self'");
});
