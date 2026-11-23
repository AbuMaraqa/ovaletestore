<?php

namespace Database\Seeders;

use App\Enums\ShippingMethodType;
use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

/**
 * Delivery regions + store pickup. Each method is created once (matched by `code`);
 * prices edited later in Admin → Shipping methods are never overwritten by re-seeding.
 * Costs are in minor units (agorot): 3500 = ₪35.
 */
class ShippingMethodSeeder extends Seeder
{
    /** @var array<string, array{type: ShippingMethodType, cost: int, names: array<string, string>, descriptions: array<string, string>}> */
    private const METHODS = [
        'inside' => [
            'type' => ShippingMethodType::Delivery,
            'cost' => 0,
            'names' => ['ar' => 'توصيل للداخل', 'en' => 'Delivery – Inside (48 areas)', 'he' => 'משלוח – תחומי הקו הירוק'],
            'descriptions' => ['ar' => 'التوصيل لمناطق الداخل', 'en' => 'Delivery to the 48 areas', 'he' => 'משלוח ליישובים בתחומי הקו הירוק'],
        ],
        'west_bank' => [
            'type' => ShippingMethodType::Delivery,
            'cost' => 0,
            'names' => ['ar' => 'توصيل للضفة الغربية', 'en' => 'Delivery – West Bank', 'he' => 'משלוח – הגדה המערבית'],
            'descriptions' => ['ar' => 'التوصيل لمدن وقرى الضفة الغربية', 'en' => 'Delivery to West Bank cities and villages', 'he' => 'משלוח לערים ולכפרים בגדה המערבית'],
        ],
        'jerusalem' => [
            'type' => ShippingMethodType::Delivery,
            'cost' => 0,
            'names' => ['ar' => 'توصيل للقدس', 'en' => 'Delivery – Jerusalem', 'he' => 'משלוח – ירושלים'],
            'descriptions' => ['ar' => 'التوصيل داخل القدس', 'en' => 'Delivery within Jerusalem', 'he' => 'משלוח בתוך ירושלים'],
        ],
        'pickup' => [
            'type' => ShippingMethodType::Pickup,
            'cost' => 0,
            'names' => ['ar' => 'استلام من المحل', 'en' => 'Store pickup', 'he' => 'איסוף עצמי מהחנות'],
            'descriptions' => ['ar' => 'استلم طلبك من محلنا بدون رسوم', 'en' => 'Collect your order from our store for free', 'he' => 'אסוף את ההזמנה מהחנות ללא עלות'],
        ],
    ];

    public function run(): void
    {
        $order = 0;
        foreach (self::METHODS as $code => $definition) {
            $method = ShippingMethod::query()->firstOrNew(['code' => $code]);
            if (! $method->exists) {
                $method->fill(['type' => $definition['type'], 'cost' => $definition['cost'], 'is_active' => true, 'sort_order' => $order]);
                foreach ($definition['names'] as $locale => $name) {
                    $translation = $method->translateOrNew($locale);
                    $translation->setAttribute('name', $name);
                    $translation->setAttribute('description', $definition['descriptions'][$locale]);
                }
                $method->save();
            }
            $order++;
        }
    }
}
