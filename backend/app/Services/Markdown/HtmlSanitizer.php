<?php

namespace App\Services\Markdown;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Removes any HTML that could execute script in a browser from Markdown-derived
 * content, using an allowlist of the tags/attributes Markdown legitimately
 * produces (headings, code, tables, GFM task lists, links, images).
 *
 * This is applied both when a document is parsed (App\Services\Markdown\MarkdownParser)
 * and every time `content_html` is read from the Document model, so rows written
 * directly to the database before this sanitizer existed are also covered without
 * requiring any data migration.
 */
final class HtmlSanitizer
{
    /**
     * Allowed tags mapped to the attribute names each one may keep.
     * Any tag not listed here is removed entirely, together with its subtree,
     * because Markdown never legitimately needs any tag outside this list.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_TAGS = [
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'p' => [], 'br' => [], 'hr' => [],
        'strong' => [], 'em' => [], 'b' => [], 'i' => [], 's' => [], 'del' => [],
        'blockquote' => [],
        'pre' => [],
        'code' => ['class'],
        'ul' => [], 'ol' => [], 'li' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [],
        'tr' => [],
        'th' => ['align'],
        'td' => ['align'],
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'title'],
        'input' => ['type', 'checked', 'disabled'],
    ];

    /**
     * URL schemes allowed for `href`/`src`. Everything else (javascript:, data:,
     * vbscript:, file:, etc.) is stripped. Schemeless values (relative paths,
     * fragments, query strings) are always allowed.
     *
     * @var list<string>
     */
    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * @var list<string>
     */
    private const ALLOWED_ALIGN_VALUES = ['left', 'center', 'right', 'justify', 'char'];

    private const ROOT_ID = '__command_sphere_html_sanitizer_root__';

    /**
     * Sanitizes an HTML fragment down to the allowlisted subset.
     */
    public static function sanitize(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument;
        $previousErrorSetting = libxml_use_internal_errors(true);

        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8"?><div id="'.self::ROOT_ID.'">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorSetting);

        if (! $loaded) {
            return '';
        }

        $root = $dom->getElementById(self::ROOT_ID);

        if (! $root instanceof DOMElement) {
            return '';
        }

        self::cleanChildren($root, $dom);

        $output = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $rendered = $dom->saveHTML($child);
            $output .= $rendered === false ? '' : $rendered;
        }

        return $output;
    }

    private static function cleanChildren(DOMNode $parent, DOMDocument $dom): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMText) {
                continue;
            }

            if (! $node instanceof DOMElement) {
                $parent->removeChild($node);

                continue;
            }

            $tag = strtolower($node->tagName);

            if (! array_key_exists($tag, self::ALLOWED_TAGS)) {
                $parent->removeChild($node);

                continue;
            }

            self::sanitizeAttributes($node, $tag);
            self::cleanChildren($node, $dom);
        }
    }

    private static function sanitizeAttributes(DOMElement $node, string $tag): void
    {
        $allowedAttributes = self::ALLOWED_TAGS[$tag];
        $attributeNames = [];

        foreach ($node->attributes ?? [] as $attribute) {
            $attributeNames[] = $attribute->name;
        }

        foreach ($attributeNames as $attributeName) {
            $name = strtolower($attributeName);
            $value = $node->getAttribute($attributeName);

            if (! in_array($name, $allowedAttributes, true)) {
                $node->removeAttribute($attributeName);

                continue;
            }

            if (in_array($name, ['href', 'src'], true) && ! self::isSafeUrl($value)) {
                $node->removeAttribute($attributeName);

                continue;
            }

            if ($name === 'align' && ! in_array(strtolower(trim($value)), self::ALLOWED_ALIGN_VALUES, true)) {
                $node->removeAttribute($attributeName);

                continue;
            }

            if ($name === 'class' && preg_match('/^language-[A-Za-z0-9_-]+$/', trim($value)) !== 1) {
                $node->removeAttribute($attributeName);
            }
        }

        if ($tag === 'input') {
            // Only the GFM task-list checkbox rendering is legitimate for a bare <input>.
            $node->setAttribute('type', 'checkbox');
        }
    }

    private static function isSafeUrl(string $value): bool
    {
        // Strip control/whitespace characters browsers ignore while resolving a
        // URL scheme; this defeats the classic `java\tscript:`/newline bypass.
        $normalized = preg_replace('/[\x00-\x20\x7F]+/', '', $value) ?? '';

        if ($normalized === '') {
            return true;
        }

        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $normalized, $matches) !== 1) {
            return true;
        }

        return in_array(strtolower($matches[1]), self::ALLOWED_URL_SCHEMES, true);
    }
}
