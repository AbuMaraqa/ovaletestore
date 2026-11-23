<?php

use App\Migration\Woo\BilingualHtml;
use App\Migration\Woo\BilingualName;

it('splits bilingual descriptions into Arabic and English blocks', function () {
    $html = '<p><strong>بسكويت ليدي فنجر – 200 غم</strong></p><p>بسكويت خفيف وهش.<br />مثالي للتيراميسو.</p>'
        .'<p><strong>Ladyfinger Biscuits – 200g</strong></p><p>Light and airy biscuits.<br />Ideal for tiramisu.</p>';

    $split = BilingualHtml::split($html);

    expect($split['ar'])->toContain('بسكويت ليدي فنجر')->not->toContain('Ladyfinger')
        ->and($split['en'])->toContain('Ladyfinger Biscuits')->toContain('Ideal for tiramisu')->not->toContain('بسكويت');
});

it('splits a single paragraph whose lines alternate languages', function () {
    $html = '<p><strong>فرنش بندق زومر 1 كيلو</strong><br>بندق فرنسي من نوع <strong>زومر</strong> عالي الجودة.<br>'
        .'<strong>French Hazelnuts (Zomer) 1 KG</strong><br>High-quality French hazelnuts.</p>';

    $split = BilingualHtml::split($html);

    expect($split['ar'])->toStartWith('<p>')->toContain('زومر')->not->toContain('High-quality')
        ->and($split['en'])->toStartWith('<p>')->toContain('High-quality French hazelnuts')->not->toContain('بندق');
});

it('keeps single-language descriptions untouched', function () {
    expect(BilingualHtml::split('<p>وصف عربي فقط</p>'))->toBe(['ar' => '<p>وصف عربي فقط</p>', 'en' => null])
        ->and(BilingualHtml::split(null))->toBe(['ar' => null, 'en' => null]);
});

it('splits product names with a trailing English name but keeps a lone Latin brand', function () {
    expect(BilingualName::split('آيس عنب Iced Grape Drink', allowTrailingLatin: true, minTrailingWords: 2))
        ->toBe(['ar' => 'آيس عنب', 'en' => 'Iced Grape Drink'])
        ->and(BilingualName::split('كاسات كوكتيل ربطة 50 كاسة10oz cocktail cups', allowTrailingLatin: true, minTrailingWords: 2))
        ->toBe(['ar' => 'كاسات كوكتيل ربطة 50 كاسة', 'en' => '10oz cocktail cups'])
        ->and(BilingualName::split('شوكولاتة كاليبوت Callebaut', allowTrailingLatin: true, minTrailingWords: 2)['en'])->toBeNull();
});
