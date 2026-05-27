<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class NoHtmlRule implements ValidationRuleInterface
{
    /**
     * @var list<string>
     */
    private const FORBIDDEN_PATTERNS = [
        // Any real or encoded HTML/XML-like tag.
        '/<\s*\/?\s*[a-z][a-z0-9:_-]*(?:\s[^<>]*)?>/iu',

        // HTML comments, CDATA, PHP/template-like tags.
        '/<!--|-->|<!\[CDATA\[|<\?|<%|%>/iu',

        // Scriptable protocols.
        '/\b(?:javascript|vbscript|livescript|mocha|data)\s*:/iu',

        // Inline event handlers: onclick=, onerror=, onload=...
        '/\bon[a-z]+\s*=/iu',

        // Dangerous browser/document references.
        '/\b(?:document|window)\s*\./iu',
        '/\b(?:document\.cookie|document\.write|innerHTML|outerHTML|parentNode)\b/iu',

        // CSS/script execution patterns.
        '/\bexpression\s*\(/iu',
        '/\b(?:eval|alert|prompt|confirm)\s*\(/iu',

        // Common dangerous elements even if malformed.
        '/\b(?:script|iframe|object|embed|svg|math|meta|link|style|base|form|input|button|textarea)\b/iu',
    ];

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        if (!is_string($value)) {
            return false;
        }

        $value = trim($value);

        if ($value === '') {
            return true;
        }

        $decoded = $this->decodeRepeatedly($value);
        $normalized = $this->normalize($decoded);

        if ($decoded !== strip_tags($decoded)) {
            return false;
        }

        foreach (self::FORBIDDEN_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return false;
            }
        }

        return true;
    }

    private function decodeRepeatedly(string $value): string
    {
        $previous = null;
        $decoded = $value;

        for ($i = 0; $i < 3 && $decoded !== $previous; $i++) {
            $previous = $decoded;

            $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (str_contains($decoded, '%')) {
                $decoded = rawurldecode($decoded);
            }
        }

        return $decoded;
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
