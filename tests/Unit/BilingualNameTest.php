<?php

use App\Migration\Woo\BilingualName;

it('splits Arabic and English parts at the separating dot', function (string $raw, string $ar, ?string $en) {
    expect(BilingualName::split($raw))->toBe(['ar' => $ar, 'en' => $en]);
})->with([
    ['عجينة سكر احمر1ك.Red Fondant – 1 kg', 'عجينة سكر احمر1ك', 'Red Fondant – 1 kg'],
    ['كريما طبيخ مستر شيف 500 مل.Mr. Chef Cooking Cream – 500 ml', 'كريما طبيخ مستر شيف 500 مل', 'Mr. Chef Cooking Cream – 500 ml'],
    ['مفن كاكاو سما SAMA احمدي/ شوال 10 كيلو.', 'مفن كاكاو سما SAMA احمدي/ شوال 10 كيلو', null],
    ['كريما و ألبان.Creams&amp;Dairy', 'كريما و ألبان', 'Creams&Dairy'],
]);

it('optionally splits trailing Latin words (category names)', function () {
    expect(BilingualName::split('آيسات Iced Drinks', allowTrailingLatin: true))->toBe(['ar' => 'آيسات', 'en' => 'Iced Drinks']);
});
