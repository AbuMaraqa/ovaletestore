<?php

namespace App\Support\Seo;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Settings\ContactSettings;
use App\Settings\GeneralSettings;
use App\Support\Money;
use Illuminate\Support\Str;
use Spatie\SchemaOrg\Schema;

/** Typed schema.org builders (spatie/schema-org); breadcrumbs use laravel-seo's own markup (SeoData::breadcrumbs). */
final class JsonLd
{
    public static function organization(): array
    {
        $general = app(GeneralSettings::class);
        $contact = app(ContactSettings::class);

        $org = Schema::onlineStore()
            ->name($general->siteName())
            ->url(url('/'))
            ->sameAs(array_values(array_filter($contact->social_links)));

        if ($general->logo_path) {
            $org->logo(asset('storage/'.$general->logo_path));
        }
        if ($contact->phone) {
            $org->telephone($contact->phone);
        }

        return $org->toArray();
    }

    public static function website(): array
    {
        return Schema::webSite()
            ->name(app(GeneralSettings::class)->siteName())
            ->url(url('/'))
            ->potentialAction(
                Schema::searchAction()
                    ->target(route('storefront.shop').'?q={search_term_string}')
                    ->setProperty('query-input', 'required name=search_term_string'),
            )
            ->toArray();
    }

    public static function product(Product $product, string $url, ?string $image): array
    {
        $currency = Money::storeCurrency(); // structured data: base-currency prices

        $offers = $product->activeVariants->map(function (ProductVariant $variant) use ($currency, $url) {
            return Schema::offer()
                ->price(number_format($variant->effectivePrice() / 100, 2, '.', ''))
                ->priceCurrency($currency)
                ->sku($variant->sku)
                ->url($url)
                ->itemCondition('https://schema.org/NewCondition')
                ->availability($variant->isPurchasable() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock');
        })->all();

        $schema = Schema::product()
            ->name($product->name)
            ->url($url)
            ->offers($offers);

        // squish() also removes the &nbsp; WooCommerce left in empty descriptions.
        $description = Str::of(html_entity_decode(strip_tags((string) ($product->short_description ?: $product->description))))
            ->squish()->limit(5000)->toString();
        if ($description !== '') {
            $schema->description($description);
        }
        if ($image) {
            $schema->image($image);
        }
        if ($sku = $product->defaultVariant?->sku) {
            $schema->sku($sku);
        }
        if ($product->brand) {
            $schema->brand(Schema::brand()->name($product->brand->name));
        }

        return $schema->toArray();
    }
}
