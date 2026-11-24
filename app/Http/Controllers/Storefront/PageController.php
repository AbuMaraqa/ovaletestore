<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Settings\ContactSettings;
use App\Support\Seo\JsonLd;
use App\Support\Seo\SeoData;
use Inertia\Inertia;
use Inertia\Response;

/** Static storefront pages (content in lang files; contact data from ContactSettings). */
class PageController extends Controller
{
    public function contact(ContactSettings $contact): Response
    {
        $locale = app()->getLocale();

        return Inertia::render('Storefront/Pages/Contact', [
            'contact' => [
                'phone_lines' => $contact->phoneLinesFor($locale) ?: ($contact->phone ? [['number' => $contact->phone, 'label' => null]] : []),
                'whatsapp' => $contact->whatsapp,
                'email' => $contact->email,
                'locations' => $contact->locationsFor($locale),
                'social' => array_filter($contact->social_links),
            ],
            'seo' => SeoData::make(__('storefront.contact_us'), __('storefront.contact_intro'))
                ->forCurrentRoute()
                ->withJsonLd(JsonLd::organization()),
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('Storefront/Pages/About', [
            'seo' => SeoData::make(__('storefront.about_us'), __('storefront.about_lead'))->forCurrentRoute(),
        ]);
    }
}
