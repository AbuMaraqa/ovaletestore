<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Whitelist sanitizer for admin/imported rich text that is rendered with v-html on the storefront.
 * Scripts, event handlers, iframes, styles and javascript: URLs are removed.
 */
final class RichText
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $clean = trim(self::sanitizer()->sanitize($html));

        return $clean === '' ? null : $clean;
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowElement('p')->allowElement('br')->allowElement('strong')->allowElement('b')
                ->allowElement('em')->allowElement('i')->allowElement('u')->allowElement('ul')
                ->allowElement('ol')->allowElement('li')->allowElement('h2')->allowElement('h3')
                ->allowElement('h4')->allowElement('blockquote')->allowElement('span')
                ->allowElement('table')->allowElement('thead')->allowElement('tbody')
                ->allowElement('tr')->allowElement('th')->allowElement('td')
                ->allowElement('a', ['href', 'title'])
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->forceAttribute('a', 'rel', 'noopener nofollow')
                ->withMaxInputLength(200_000),
        );
    }
}
