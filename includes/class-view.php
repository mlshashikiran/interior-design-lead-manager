<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Minimal guarded renderer for plugin-owned templates. */
class IDLM_View {
    public static function render($template, $context = array()) {
        $template = str_replace('\\', '/', (string) $template);
        if ('' === $template || !preg_match('/^(?:[a-z0-9-]+\/)*[a-z0-9-]+\.php$/', $template)) {
            return '';
        }

        $template_root = realpath(IDLM_PATH . 'templates');
        $template_path = realpath(IDLM_PATH . 'templates/' . $template);
        if (!$template_root || !$template_path || 0 !== strpos($template_path, $template_root . DIRECTORY_SEPARATOR)) {
            return '';
        }

        $context = is_array($context) ? $context : array();
        ob_start();
        include $template_path;
        return (string) ob_get_clean();
    }

    public static function display($template, $context = array()) {
        echo self::render($template, $context); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Templates escape by context.
    }
}
