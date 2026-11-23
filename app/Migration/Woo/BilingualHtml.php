<?php

namespace App\Migration\Woo;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * The source descriptions hold both languages in one HTML field: Arabic blocks followed by
 * the same text in English (sometimes inside one <p>, separated by <br>).
 * Splits them into a clean Arabic and a clean English document, keeping the markup.
 */
final class BilingualHtml
{
    /** Minimum share of letters of the minority script for a block to count as mixed. */
    private const MIXED_RATIO = 0.2;

    private const SPLITTABLE = ['p', 'div', 'ul', 'ol', 'h2', 'h3', 'h4', 'blockquote', 'span', 'strong', 'b', 'em', 'i', 'u', 'td'];

    /** @return array{ar: ?string, en: ?string} en is null when the source has no English part */
    public static function split(?string $html): array
    {
        if ($html === null || trim($html) === '') {
            return ['ar' => null, 'en' => null];
        }

        [$ar, $latin] = self::letters(strip_tags($html));
        if ($latin === 0 || $ar === 0) {
            // Single-language text: an English-only description serves both locales.
            return ['ar' => $html, 'en' => $latin > 0 ? $html : null];
        }

        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementById('root');
        if ($root === null) {
            return ['ar' => $html, 'en' => null];
        }

        [$arNodes, $enNodes] = self::partition($root);

        $render = function (array $nodes) use ($doc): ?string {
            $out = trim(implode('', array_map(fn (DOMNode $n) => $doc->saveHTML($n), $nodes)));
            $out = (string) preg_replace('#(<br\s*/?>\s*)+(</(p|h[2-4]|div|li|strong|b|em|span)>)#u', '$2', $out);
            $out = (string) preg_replace('#(</(p|h[2-4]|div|ul|ol|table)>)\s*(<br\s*/?>\s*)+#u', "$1\n", $out);
            $out = (string) preg_replace('#<(p|h[2-4]|div|strong)>(\s|&nbsp;|<br\s*/?>)*</\1>#u', '', $out);
            $out = trim((string) preg_replace('#^(<br\s*/?>\s*)+|(<br\s*/?>\s*)+$#u', '', $out));

            return $out === '' ? null : $out;
        };

        $arHtml = $render($arNodes);
        $enHtml = $render($enNodes);

        return $enHtml === null || $arHtml === null
            ? ['ar' => $html, 'en' => null]
            : ['ar' => $arHtml, 'en' => $enHtml];
    }

    /**
     * Splits the children of $parent into Arabic and English node lists (clones).
     *
     * @return array{0: list<DOMNode>, 1: list<DOMNode>}
     */
    private static function partition(DOMNode $parent): array
    {
        $ar = [];
        $en = [];

        foreach (self::segments($parent) as $segment) {
            $text = implode('', array_map(fn (DOMNode $n) => $n->textContent, $segment));
            $script = self::script($text);

            // A single mixed element (e.g. <p>عربي<br>English</p>): split inside it, keep the wrapper.
            if ($script === 'mixed' && count($segment) === 1 && $segment[0] instanceof DOMElement
                && in_array(strtolower($segment[0]->nodeName), self::SPLITTABLE, true)) {
                [$innerAr, $innerEn] = self::partition($segment[0]);
                if ($innerAr !== [] && $innerEn !== []) {
                    $ar[] = self::wrap($segment[0], $innerAr);
                    $en[] = self::wrap($segment[0], $innerEn);

                    continue;
                }
                $script = self::majority($text);
            }

            if ($script === 'mixed') {
                $script = self::majority($text);
            }

            $clones = array_map(fn (DOMNode $n) => $n->cloneNode(true), $segment);
            match ($script) {
                'ar' => array_push($ar, ...$clones),
                'en' => array_push($en, ...$clones),
                default => null, // whitespace / empty blocks are dropped
            };
        }

        return [$ar, $en];
    }

    /**
     * Groups child nodes: every block element is its own segment; inline runs are split at <br>.
     *
     * @return list<list<DOMNode>>
     */
    private static function segments(DOMNode $parent): array
    {
        $segments = [];
        $current = [];

        foreach (iterator_to_array($parent->childNodes) as $child) {
            $name = strtolower($child->nodeName);
            if ($name === 'br') {
                if ($current !== []) {
                    $segments[] = $current;
                }
                $current = [];

                continue;
            }

            if (in_array($name, ['p', 'div', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'table'], true)) {
                if ($current !== []) {
                    $segments[] = $current;
                }
                $segments[] = [$child];
                $current = [];

                continue;
            }

            $current[] = $child;
        }

        if ($current !== []) {
            $segments[] = $current;
        }

        // Re-insert line breaks between consecutive inline segments when rebuilt.
        return array_map(function (array $segment) use ($parent): array {
            $isInline = ! in_array(strtolower($segment[0]->nodeName), ['p', 'div', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'table'], true);
            if ($isInline && $parent->ownerDocument !== null) {
                $segment[] = $parent->ownerDocument->createElement('br');
            }

            return $segment;
        }, $segments);
    }

    /** @param list<DOMNode> $children */
    private static function wrap(DOMElement $original, array $children): DOMElement
    {
        $wrapper = $original->cloneNode(false);
        assert($wrapper instanceof DOMElement);
        foreach ($children as $child) {
            $wrapper->appendChild($child);
        }

        return $wrapper;
    }

    /** @return 'ar'|'en'|'mixed'|'none' */
    private static function script(string $text): string
    {
        [$ar, $latin] = self::letters($text);
        $total = $ar + $latin;
        if ($total === 0) {
            return 'none';
        }

        if ($ar > 0 && $latin > 0 && min($ar, $latin) / $total >= self::MIXED_RATIO) {
            return 'mixed';
        }

        return $ar >= $latin ? 'ar' : 'en';
    }

    /** @return 'ar'|'en'|'none' */
    private static function majority(string $text): string
    {
        [$ar, $latin] = self::letters($text);

        return $ar + $latin === 0 ? 'none' : ($ar >= $latin ? 'ar' : 'en');
    }

    /** @return array{0:int, 1:int} [arabic letters, latin letters] */
    private static function letters(string $text): array
    {
        return [
            (int) preg_match_all('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text),
            (int) preg_match_all('/[A-Za-z]/', $text),
        ];
    }
}
