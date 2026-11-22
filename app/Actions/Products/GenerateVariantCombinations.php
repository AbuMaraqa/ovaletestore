<?php

namespace App\Actions\Products;

/**
 * Cartesian product of the selected terms of each variation attribute.
 * Input:  [attributeId => [termId, termId], attributeId => [termId]]
 * Output: [[termId, termId], ...] — one array of term ids per valid combination.
 */
class GenerateVariantCombinations
{
    /**
     * @param  array<int, list<int>>  $termsByAttribute
     * @return list<list<int>>
     */
    public function handle(array $termsByAttribute): array
    {
        $sets = array_values(array_filter(
            array_map(fn (array $ids) => array_values(array_unique(array_map('intval', $ids))), $termsByAttribute),
            fn (array $ids) => $ids !== [],
        ));

        if ($sets === []) {
            return [];
        }

        $combinations = [[]];

        foreach ($sets as $termIds) {
            $next = [];
            foreach ($combinations as $combination) {
                foreach ($termIds as $termId) {
                    $next[] = [...$combination, $termId];
                }
            }
            $combinations = $next;
        }

        return $combinations;
    }
}
