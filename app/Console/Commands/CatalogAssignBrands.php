<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\BrandTranslation;
use App\Models\Product;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('catalog:assign-brands {--dry-run : Report matches without writing}')]
#[Description('Create the store brands (with the original logos) and link products whose names mention them')]
class CatalogAssignBrands extends Command
{
    /**
     * en name => [ar name, he name, logo file (home/…), name patterns (PCRE, matched against ar+en product names)].
     * Products are only linked when they have no brand yet — manual assignments are never overwritten.
     */
    private const BRANDS = [
        'Lotus' => ['لوتس', 'לוטוס', 'home/brand-4.webp', ['/\blotus\b/i', '/لوتس/u']],
        'Nutella' => ['نوتيلا', 'נוטלה', 'home/brand-5.webp', ['/nutella/i', '/نوتيلا/u']],
        'Chocolake' => ['شوكوليك', 'שוקולייק', 'home/brand-7.webp', ['/chocolake|shocolake/i', '/شوكوليك/u']],
        "Kahan's" => ['كاهانز', 'כהן', 'home/brand-8.webp', ['/kahan/i', '/كاهان/u']],
        'PMC' => ['بي إم سي', 'PMC', 'home/brand-0.webp', ['/\bPMC\b/']],
        'Al Fares' => ['الفارس', 'אל פארס', 'home/brand-1.webp', ['/\bal ?fares\b/i', '/الفارس/u']],
        'Complex Bar' => ['كومبلكس بار', 'קומפלקס בר', 'home/brand-2.webp', ['/complex ?bar/i', '/كومبلكس/u']],
        'Pinch & Drop' => ['بنش آند دروب', 'פינץ׳ אנד דרופ', 'home/brand-3.webp', ['/pinch ?(&|and) ?drop/i']],
        'Taamim Plus' => ['طعميم بلس', 'טעמים פלוס', 'home/brand-6.webp', ['/taamim/i', '/טעמים/u']],
        'Monin' => ['مونين', 'מונין', null, ['/\bmonin\b/i', '/مونين/u']],
        'Aroma Gold' => ['أروما جولد', 'ארומה גולד', null, ['/aroma ?gold/i', '/اروما|أروما/u']],
        'Callebaut' => ['كاليبوت', 'קאלבאו', null, ['/callebaut/i', '/كاليبوت/u']],
        'SAMA' => ['سما', 'סמא', null, ['/\bSAMA\b/']],
        'San Martino' => ['سان مارتينو', 'סן מרטינו', null, ['/san ?martino/i', '/سان مارتينو/u']],
        'Lavazza' => ['لافازا', 'לוואצה', null, ['/lavazza/i', '/لافازا/u']],
    ];

    public function handle(): int
    {
        // Bulk import: thousands of rows would bury the human audit trail.
        activity()->disableLogging();

        $dryRun = (bool) $this->option('dry-run');
        $rows = [];

        foreach (array_keys(self::BRANDS) as $sort => $en) {
            [$ar, $he, $logo, $patterns] = self::BRANDS[$en];

            $matches = Product::query()->whereNull('brand_id')->with('translations')->get()
                ->filter(fn (Product $p) => $p->translations->contains(
                    fn ($t) => collect($patterns)->contains(fn ($re) => preg_match($re, (string) $t->name) === 1),
                ));

            if (! $dryRun) {
                DB::transaction(function () use ($en, $ar, $he, $logo, $sort, $matches) {
                    $brand = $this->findOrCreateBrand($en, $ar, $he, $sort);

                    if ($logo && $brand->getMedia('logo')->isEmpty() && Storage::disk('public')->exists($logo)) {
                        $brand->addMedia(Storage::disk('public')->path($logo))->preservingOriginal()
                            ->usingFileName(BrandTranslation::makeSlug($en, 'en').'-logo.webp')->toMediaCollection('logo');
                    }

                    Product::query()->whereKey($matches->modelKeys())->update(['brand_id' => $brand->id]);
                });
            }

            $rows[] = [$en, $logo ? 'yes' : '—', $matches->count()];
        }

        $this->table(['Brand', 'Logo', 'Products linked'], $rows);
        $this->components->info($dryRun ? '[dry-run] nothing written.' : 'Brands ready. Unmatched products can be assigned in Admin → Products.');

        return self::SUCCESS;
    }

    private function findOrCreateBrand(string $en, string $ar, string $he, int $sort): Brand
    {
        $existing = BrandTranslation::query()->where('locale', 'en')->where('name', $en)->value('brand_id');
        $brand = $existing ? Brand::query()->findOrFail($existing) : new Brand;

        $brand->fill(['is_active' => true, 'is_featured' => true, 'sort_order' => $brand->exists ? $brand->sort_order : $sort]);
        foreach (['ar' => $ar, 'en' => $en, 'he' => $he] as $locale => $name) {
            $brand->translateOrNew($locale)->name = $name;
        }
        $brand->save();

        return $brand;
    }
}
