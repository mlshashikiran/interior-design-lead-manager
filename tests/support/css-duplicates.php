<?php
/**
 * Finds style rules that can never win: an identical rule (same conditions, selectors and
 * declarations) appears later in the same stylesheet, so the later copy always overrides it.
 * Builder: Shashi.
 */

/** Parse a stylesheet into style rules with byte ranges and their @media/@supports context. */
function idlm_css_rules(string $css): array {
    $rules = array();
    idlm_css_walk($css, 0, strlen($css), '', $rules);
    return $rules;
}

function idlm_css_normalize(string $text): string {
    return trim((string) preg_replace('/\s+/', ' ', $text));
}

/** Index of the matching close brace for the open brace at $open, skipping strings and comments. */
function idlm_css_close(string $css, int $open, int $end): int {
    $depth = 0;
    for ($i = $open; $i < $end; $i++) {
        $c = $css[$i];
        if ('/' === $c && '*' === ($css[$i + 1] ?? '')) { $i = strpos($css, '*/', $i + 2) + 1; continue; }
        if ('"' === $c || "'" === $c) { for ($i++; $i < $end && $css[$i] !== $c; $i++) { if ('\\' === $css[$i]) { $i++; } } continue; }
        if ('{' === $c) { $depth++; }
        if ('}' === $c && 0 === --$depth) { return $i; }
    }
    throw new RuntimeException('Unbalanced braces.');
}

function idlm_css_walk(string $css, int $start, int $end, string $context, array &$rules): void {
    $i = $start;
    while ($i < $end) {
        if (ctype_space($css[$i])) { $i++; continue; }
        if ('/' === $css[$i] && '*' === ($css[$i + 1] ?? '')) { $i = strpos($css, '*/', $i + 2) + 2; continue; }
        $prelude_start = $i;
        while ($i < $end && '{' !== $css[$i] && ';' !== $css[$i]) {
            if ('/' === $css[$i] && '*' === ($css[$i + 1] ?? '')) { $i = strpos($css, '*/', $i + 2) + 2; continue; }
            $i++;
        }
        if ($i >= $end) { return; }
        if (';' === $css[$i]) { $i++; continue; }
        $prelude = idlm_css_normalize((string) preg_replace('#/\*.*?\*/#s', '', substr($css, $prelude_start, $i - $prelude_start)));
        $condition = strtolower((string) preg_replace(array('/\s*([:(),])\s*/', '/^@(media|supports)\(/'), array('$1', '@$1 ('), $prelude));
        $close = idlm_css_close($css, $i, $end);
        if (preg_match('/^@(media|supports)\b/i', $prelude)) {
            idlm_css_walk($css, $i + 1, $close, $context . '|' . $condition, $rules);
        } elseif ('@' !== ($prelude[0] ?? '')) {
            $body = (string) preg_replace('#/\*.*?\*/#s', '', substr($css, $i + 1, $close - $i - 1));
            $declarations = array_values(array_filter(array_map(static function ($declaration) {
                $declaration = idlm_css_normalize($declaration);
                $colon = strpos($declaration, ':');
                return false === $colon ? $declaration : strtolower(rtrim(substr($declaration, 0, $colon))) . ':' . ltrim(substr($declaration, $colon + 1));
            }, explode(';', $body)), 'strlen'));
            $selectors = array_map('idlm_css_normalize', explode(',', $prelude));
            $rules[] = array('start' => $prelude_start, 'end' => $close + 1, 'context' => $context,
                'key' => $context . "\n" . implode(',', $selectors) . "\n" . implode(';', $declarations), 'nested' => false !== strpos($body, '{'));
        }
        $i = $close + 1;
    }
}

/** Rules that an identical later rule always overrides. */
function idlm_css_dead_duplicates(string $css): array {
    $rules = idlm_css_rules($css);
    $last = array();
    foreach ($rules as $index => $rule) { $last[$rule['key']] = $index; }
    $dead = array();
    foreach ($rules as $index => $rule) {
        if ($last[$rule['key']] !== $index) { $dead[] = $rule; }
    }
    return $dead;
}
