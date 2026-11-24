<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Store locations (with map) and labelled phone numbers, taken from the original ovaletestore.com footer.
 * They replace the single `address` and the unlabelled `extra_phones`; all of it is editable in
 * Admin → Settings → Contact. The first location's coordinates are the pin of the original footer map.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('contact.phone_lines', [
            ['number' => '+972593387610', 'label' => [
                'ar' => 'قياسات العلب أو التفاصيل الفنية أو صور الأصناف',
                'en' => 'Box sizes, technical details or product photos',
                'he' => 'מידות אריזות, פרטים טכניים או תמונות מוצרים',
            ]],
            ['number' => '+972599955929', 'label' => ['ar' => 'لأسعار الجملة', 'en' => 'Wholesale prices', 'he' => 'מחירים סיטונאיים']],
            ['number' => '+972599958890', 'label' => ['ar' => 'للأسعار والأصناف', 'en' => 'Prices and products', 'he' => 'מחירים ומוצרים']],
            ['number' => '+972599955288', 'label' => ['ar' => 'رقم الإدارة', 'en' => 'Management', 'he' => 'הנהלה']],
        ]);

        $this->migrator->add('contact.locations', [
            [
                'name' => ['ar' => 'فرع دورا', 'en' => 'Dura branch', 'he' => 'סניף דורא'],
                'address' => [
                    'ar' => 'دوار العلم، خلف بنك فلسطين، بجانب صالات الرجعي',
                    'en' => 'Al-Alam roundabout, behind Bank of Palestine, next to Al-Rajabi halls',
                    'he' => 'כיכר אל-עלם, מאחורי בנק פלסטין, ליד אולמות אל-רג׳בי',
                ],
                'lat' => 31.507,
                'lng' => 35.037417,
            ],
            [
                'name' => ['ar' => 'فرع بيت لحم', 'en' => 'Bethlehem branch', 'he' => 'סניף בית לחם'],
                'address' => [
                    'ar' => 'بيت لحم، الدوحة، طلوع الحاووز، بجانب قاعات بلقيس',
                    'en' => 'Bethlehem, Al-Doha, Al-Hawooz rise, next to Balqis halls',
                    'he' => 'בית לחם, אל-דוחה, עליית אל-חאוז, ליד אולמות בלקיס',
                ],
                'lat' => null,
                'lng' => null,
            ],
        ]);

        $this->migrator->delete('contact.extra_phones');
        $this->migrator->delete('contact.address');
    }
};
