<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', ['ar' => 'أوفاليتي ستور', 'en' => 'Ovalete Store', 'he' => 'Ovalete Store']);
        $this->migrator->add('general.tagline', [
            'ar' => 'مستلزمات الحلويات والمشروبات بجودة عالمية',
            'en' => 'Premium pastry, dessert & beverage supplies',
            'he' => 'חומרי גלם איכותיים לקונדיטוריה ומשקאות',
        ]);
        $this->migrator->add('general.logo_path', null);
        $this->migrator->add('general.favicon_path', null);
        $this->migrator->add('general.default_locale', 'ar');
        $this->migrator->add('general.enabled_locales', ['ar', 'en', 'he']);

        $this->migrator->add('contact.phone', null);
        $this->migrator->add('contact.whatsapp', null);
        $this->migrator->add('contact.email', null);
        $this->migrator->add('contact.address', ['ar' => '', 'en' => '', 'he' => '']);
        $this->migrator->add('contact.social_links', ['facebook' => null, 'instagram' => null, 'tiktok' => null]);

        $this->migrator->add('store.currency', 'ILS');
        $this->migrator->add('store.products_per_page', 24);
        $this->migrator->add('store.show_out_of_stock', true);
        $this->migrator->add('store.min_order_total', 0);
        $this->migrator->add('store.shipping_fee', 0);
        $this->migrator->add('store.guest_checkout', true);

        $this->migrator->add('seo.home_title', [
            'ar' => 'أوفاليتي ستور | مستلزمات الحلويات والمشروبات',
            'en' => 'Ovalete Store | Pastry & Beverage Supplies',
            'he' => 'Ovalete Store | חומרי גלם לקונדיטוריה ומשקאות',
        ]);
        $this->migrator->add('seo.home_description', [
            'ar' => 'تسوق أكثر من 800 منتج من مستلزمات الحلويات، الشوكولاتة، الحشوات، القهوة والمشروبات من أفضل العلامات العالمية.',
            'en' => 'Shop 800+ pastry, chocolate, filling, coffee and beverage supplies from leading global brands.',
            'he' => 'מעל 800 מוצרים לקונדיטוריה, שוקולד, מילויים, קפה ומשקאות מהמותגים המובילים.',
        ]);
        $this->migrator->add('seo.og_image_path', null);
        $this->migrator->add('seo.allow_indexing', true);
    }
};
