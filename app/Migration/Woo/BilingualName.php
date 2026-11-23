<?php

namespace App\Migration\Woo;

/**
 * The source stores both languages in one string: "عربي.English".
 * Splits at the first dot whose remainder is Latin-only text (so "Mr. Chef" stays intact).
 */
final class BilingualName
{
    private const ARABIC_CLASS = '\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}';

    /**
     * @param  int  $minTrailingWords  products use 2 so a lone Latin brand ("… Callebaut") is not mistaken for a translation
     * @return array{ar:string, en:?string}
     */
    public static function split(string $raw, bool $allowTrailingLatin = false, int $minTrailingWords = 1): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        $offset = 0;
        while (($pos = mb_strpos($name, '.', $offset)) !== false) {
            $before = trim(mb_substr($name, 0, $pos));
            // Stray Arabic diacritics typed right after the dot (e.g. ".ٍSmoothies") belong to neither part.
            $after = trim((string) preg_replace('/^[\p{Mn}\s]+/u', '', mb_substr($name, $pos + 1)));

            if ($before !== '' && self::hasArabic($before) && self::isLatinText($after)) {
                return ['ar' => $before, 'en' => $after];
            }

            $offset = $pos + 1;
        }

        // "آيسات Iced Drinks" / "كاسة10oz cocktail cups": Latin words after the last Arabic character.
        $trailing = '/^(.*['.self::ARABIC_CLASS.'])\s*([A-Za-z0-9][^'.self::ARABIC_CLASS.']*)$/u';
        if ($allowTrailingLatin && preg_match($trailing, $name, $m)
            && preg_match_all('/[A-Za-z]{2,}/', $m[2]) >= $minTrailingWords) {
            return ['ar' => trim($m[1], " .\t"), 'en' => trim($m[2])];
        }

        return ['ar' => trim($name, " .\t"), 'en' => null];
    }

    private static function hasArabic(string $text): bool
    {
        return (bool) preg_match('/['.self::ARABIC_CLASS.']/u', $text);
    }

    private static function isLatinText(string $text): bool
    {
        return $text !== '' && ! self::hasArabic($text) && preg_match('/[A-Za-z]/', $text) === 1;
    }
}
