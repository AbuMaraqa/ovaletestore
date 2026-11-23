<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Contact details and slogan taken from the original ovaletestore.com header/footer (tracking params removed). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('contact.phone', fn () => '+972593387610');
        $this->migrator->update('contact.whatsapp', fn () => '972593387610');

        if (! $this->migrator->exists('contact.extra_phones')) {
            $this->migrator->add('contact.extra_phones', ['+972599955288', '+972599955929', '+972599958890']);
        }

        $this->migrator->update('contact.social_links', fn () => [
            'facebook' => 'https://www.facebook.com/ovaletestore',
            'instagram' => 'https://www.instagram.com/ovalete_store_dura/',
            'snapchat' => 'https://www.snapchat.com/@ovaletestore',
            'tiktok' => 'https://www.tiktok.com/@ovaletestore',
            'telegram' => 'https://t.me/OvaleteStoreDura',
        ]);

        $this->migrator->update('general.tagline', fn ($tagline) => array_merge((array) $tagline, [
            'ar' => 'اوفليت… شو ما تمنيت لقيت',
            'en' => 'Ovalete — whatever you wish for, you’ll find',
            'he' => 'Ovalete — כל מה שתרצו, תמצאו',
        ]));
    }
};
