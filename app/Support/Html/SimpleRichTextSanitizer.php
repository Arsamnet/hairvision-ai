<?php

namespace App\Support\Html;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitizes the small rich-text surface allowed in dashboard messages.
 *
 * @since 1.0.0
 */
class SimpleRichTextSanitizer
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_TAGS = [
        'a',
        'blockquote',
        'br',
        'b',
        'em',
        'i',
        'li',
        'ol',
        'p',
        'strong',
        'u',
        'ul',
    ];

    /**
     * Execute the sanitize operation.
     *
     * @param  string|null  $html  The html value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public function sanitize(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $html = strip_tags($html, '<'.implode('><', self::ALLOWED_TAGS).'>');

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->sanitizeNode($document->documentElement);

        $output = '';

        foreach ($document->documentElement?->childNodes ?? [] as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * Execute the plain text operation.
     *
     * @param  string  $html  The html value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public function plainText(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    /**
     * Execute the sanitize node operation.
     *
     * @param  DOMNode|null  $node  The node value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function sanitizeNode(?DOMNode $node): void
    {
        if (! $node) {
            return;
        }

        if ($node instanceof DOMElement) {
            $href = $node->tagName === 'a' ? trim((string) $node->getAttribute('href')) : '';

            foreach (iterator_to_array($node->attributes) as $attribute) {
                $node->removeAttribute($attribute->name);
            }

            if ($node->tagName === 'a') {
                if ($this->safeHref($href)) {
                    $node->setAttribute('href', $href);
                    $node->setAttribute('rel', 'noopener noreferrer');
                    $node->setAttribute('target', '_blank');
                }
            }
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->sanitizeNode($child);
        }
    }

    /**
     * Execute the safe href operation.
     *
     * @param  string  $href  The href value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    private function safeHref(string $href): bool
    {
        if ($href === '') {
            return false;
        }

        return preg_match('/^(https?:|mailto:|tel:)/i', $href) === 1;
    }
}
