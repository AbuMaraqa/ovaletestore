<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('home.hero_image', []);
        $this->migrator->add('home.hero_title', [
            'ar' => 'كل مستلزمات الحلويات والمشروبات في مكان واحد',
            'en' => 'All your pastry & beverage supplies in one place',
            'he' => 'כל חומרי הגלם לקונדיטוריה ולמשקאות במקום אחד',
        ]);
        $this->migrator->add('home.hero_subtitle', [
            'ar' => 'شوكولاتة، حشوات، كريمات، قهوة وزينة من أشهر الماركات العالمية — بجودة مضمونة وتوصيل سريع.',
            'en' => 'Chocolate, fillings, creams, coffee and decorations from leading global brands — guaranteed quality, fast delivery.',
            'he' => 'שוקולד, מילויים, שמנות, קפה וקישוטים מהמותגים המובילים בעולם — איכות מובטחת ומשלוח מהיר.',
        ]);
        $this->migrator->add('home.brand_logos', []);
        $this->migrator->add('home.spotlight_images', []);
        $this->migrator->add('home.spotlight_title', [
            'ar' => 'قهوة عالمية لفنجانك المثالي',
            'en' => 'World-class coffee for your perfect cup',
            'he' => 'קפה עולמי לכוס המושלמת שלך',
        ]);
        $this->migrator->add('home.spotlight_text', [
            'ar' => 'حبوب إسبريسو مختارة من أفضل المحامص العالمية، لتحضير قهوة احترافية في مقهاك أو منزلك.',
            'en' => 'Selected espresso beans from top international roasters, for professional coffee at your café or home.',
            'he' => 'פולי אספרסו נבחרים מבתי הקלייה המובילים בעולם, לקפה מקצועי בבית הקפה או בבית.',
        ]);
        $this->migrator->add('home.spotlight_category_id', null);
    }
};
