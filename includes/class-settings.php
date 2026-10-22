<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Grouped non-secret settings with mirrored legacy option compatibility. */
class IDLM_Settings {
    const VERSION = '1.0.0';
    const VERSION_OPTION = 'idlm_settings_version';
    const ROUTING_OPTION = 'idlm_routing_settings';
    const WHITE_LABEL_OPTION = 'idlm_white_label_settings';

    public static function init() {
        if (self::VERSION !== get_option(self::VERSION_OPTION, '')) {
            self::sync_routing_from_legacy();
            update_option(self::VERSION_OPTION, self::VERSION, false);
        }
        add_action('wp_head', array(__CLASS__, 'render_frontend_favicon'), 2);
    }

    /** Return the stable white-label defaults used by every plugin-owned surface. */
    public static function white_label_defaults() {
        return array(
            'brand_name' => 'Interior Design Lead Manager',
            'logo_id' => 0,
            'compact_logo_id' => 0,
            'favicon_id' => 0,
            'primary_color' => '#7A5017',
            'accent_color' => '#C99644',
            'sidebar_color' => '#111A24',
            'workspace_title' => 'Lead Manager',
            'login_heading' => 'One workspace for every customer conversation.',
            'support_email' => '',
            'footer_text' => '',
            'show_plugin_attribution' => '0',
        );
    }

    /** Read one normalized white-label snapshot. */
    public static function get_white_label() {
        $saved = get_option(self::WHITE_LABEL_OPTION, array());
        $settings = wp_parse_args(is_array($saved) ? $saved : array(), self::white_label_defaults());
        foreach (array('logo_id', 'compact_logo_id', 'favicon_id') as $key) {
            $settings[$key] = absint($settings[$key]);
        }
        foreach (array('primary_color', 'accent_color', 'sidebar_color') as $key) {
            $normalized = self::normalize_hex($settings[$key]);
            $settings[$key] = $normalized ?: self::white_label_defaults()[$key];
        }
        // Shashi: missing/default credit is off; preserve existing scalar choices.
        $settings['show_plugin_attribution'] = is_scalar($settings['show_plugin_attribution']) && '1' === (string) $settings['show_plugin_attribution'] ? '1' : '0';
        return $settings;
    }

    /** Validate and sanitize one submitted white-label snapshot before any settings are saved. */
    public static function validate_white_label($raw) {
        $raw = is_array($raw) ? $raw : array();
        $defaults = self::white_label_defaults();
        $settings = array(
            'brand_name' => sanitize_text_field(isset($raw['brand_name']) ? $raw['brand_name'] : $defaults['brand_name']),
            'logo_id' => absint(isset($raw['logo_id']) ? $raw['logo_id'] : 0),
            'compact_logo_id' => absint(isset($raw['compact_logo_id']) ? $raw['compact_logo_id'] : 0),
            'favicon_id' => absint(isset($raw['favicon_id']) ? $raw['favicon_id'] : 0),
            'primary_color' => self::normalize_hex(isset($raw['primary_color']) ? $raw['primary_color'] : ''),
            'accent_color' => self::normalize_hex(isset($raw['accent_color']) ? $raw['accent_color'] : ''),
            'sidebar_color' => self::normalize_hex(isset($raw['sidebar_color']) ? $raw['sidebar_color'] : ''),
            'workspace_title' => sanitize_text_field(isset($raw['workspace_title']) ? $raw['workspace_title'] : $defaults['workspace_title']),
            'login_heading' => sanitize_text_field(isset($raw['login_heading']) ? $raw['login_heading'] : $defaults['login_heading']),
            'support_email' => sanitize_email(isset($raw['support_email']) ? $raw['support_email'] : ''),
            'footer_text' => sanitize_textarea_field(isset($raw['footer_text']) ? $raw['footer_text'] : ''),
            'show_plugin_attribution' => '1' === ($raw['show_plugin_attribution'] ?? '') ? '1' : '0',
        );
        $errors = array();

        if ('' === $settings['brand_name']) {
            $errors['brand_name'] = 'Enter a brand name.';
        }
        if ('' === $settings['workspace_title']) {
            $errors['workspace_title'] = 'Enter a workspace title.';
        }
        if ('' === $settings['login_heading']) {
            $errors['login_heading'] = 'Enter a sign-in heading.';
        }
        if (!empty($raw['support_email']) && !is_email($settings['support_email'])) {
            $errors['support_email'] = 'Enter a valid support email address.';
        }
        foreach (array('primary_color' => 'Primary colour', 'accent_color' => 'Accent colour', 'sidebar_color' => 'Sidebar colour') as $key => $label) {
            if ('' === $settings[$key]) {
                $errors[$key] = $label . ' must be a six-digit hexadecimal value.';
                $settings[$key] = $defaults[$key];
            }
        }
        if (self::contrast_ratio($settings['primary_color'], '#FFFFFF') < 4.5) {
            $errors['primary_color'] = 'Primary colour must provide at least 4.5:1 contrast with white button text.';
        }
        if (self::contrast_ratio($settings['sidebar_color'], '#FFFFFF') < 4.5) {
            $errors['sidebar_color'] = 'Sidebar colour must provide at least 4.5:1 contrast with white navigation text.';
        }
        foreach (array('logo_id' => 'Main logo', 'compact_logo_id' => 'Compact mark', 'favicon_id' => 'Browser icon') as $key => $label) {
            $media_error = self::validate_brand_asset($settings[$key], $label);
            if ($media_error) {
                $errors[$key] = $media_error;
            }
        }

        return array('settings' => $settings, 'errors' => $errors);
    }

    /** Return display information for a saved or submitted brand image. */
    public static function brand_asset($attachment_id) {
        $attachment_id = absint($attachment_id);
        if (!$attachment_id || !function_exists('wp_get_attachment_image_url')) {
            return array('id' => 0, 'url' => '', 'filename' => '');
        }
        $url = wp_get_attachment_image_url($attachment_id, 'medium');
        $filename = function_exists('get_the_title') ? get_the_title($attachment_id) : '';
        return array(
            'id' => $attachment_id,
            'url' => $url ? $url : '',
            'filename' => $filename ? $filename : basename((string) $url),
        );
    }

    /** Return scoped semantic token overrides for plugin-owned frontend roots. */
    public static function white_label_style($settings = null) {
        $settings = is_array($settings) ? wp_parse_args($settings, self::white_label_defaults()) : self::get_white_label();
        return '--idlm-action:' . self::normalize_hex($settings['primary_color']) . ';'
            . '--idlm-action-hover:' . self::darken_hex($settings['primary_color'], 18) . ';'
            . '--idlm-action-soft:' . self::lighten_hex($settings['primary_color'], 90) . ';'
            . '--idlm-focus-ring:' . self::normalize_hex($settings['primary_color']) . ';'
            . '--idlm-brand-gold:' . self::normalize_hex($settings['accent_color']) . ';'
            . '--idlm-sidebar:' . self::normalize_hex($settings['sidebar_color']) . ';';
    }

    /** Output a CRM-specific browser icon without changing the site's global Site Icon. */
    public static function render_frontend_favicon() {
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }
        $is_crm_page = false;
        foreach (array('idlm_workspace', 'idlm_leads_list', 'idlm_dashboard', 'idlm_kanban') as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                $is_crm_page = true;
                break;
            }
        }
        if (!$is_crm_page) {
            return;
        }
        $settings = self::get_white_label();
        $asset = self::brand_asset($settings['favicon_id']);
        if ($asset['url']) {
            echo '<link rel="icon" href="' . esc_url($asset['url']) . '">' . "\n";
        }
    }

    /** Normalize a hexadecimal colour to the stored six-digit uppercase form. */
    private static function normalize_hex($value) {
        $value = strtoupper(trim((string) $value));
        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : '';
    }

    /** Validate a WordPress attachment selected for a brand image field. */
    private static function validate_brand_asset($attachment_id, $label) {
        if (!$attachment_id) {
            return '';
        }
        if (!function_exists('get_post_mime_type') || !function_exists('get_attached_file')) {
            return $label . ' could not be validated.';
        }
        if (!current_user_can('edit_post', $attachment_id)) {
            return $label . ' is not available to the current administrator.';
        }
        $mime = (string) get_post_mime_type($attachment_id);
        if (!in_array($mime, array('image/png', 'image/jpeg', 'image/webp'), true)) {
            return $label . ' must be a PNG, JPEG, or WebP image.';
        }
        $file = get_attached_file($attachment_id);
        if (!$file || !is_file($file) || filesize($file) > 2 * MB_IN_BYTES) {
            return $label . ' must be an available image no larger than 2 MB.';
        }
        return '';
    }

    /** Calculate WCAG contrast for two validated hexadecimal colours. */
    private static function contrast_ratio($foreground, $background) {
        $luminance = static function($hex) {
            $hex = ltrim((string) $hex, '#');
            $channels = array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
            $channels = array_map(static function($channel) {
                $value = $channel / 255;
                return $value <= 0.03928 ? $value / 12.92 : pow(($value + 0.055) / 1.055, 2.4);
            }, $channels);
            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };
        $first = $luminance($foreground);
        $second = $luminance($background);
        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    /** Build a stable darker interaction colour from the administrator's primary colour. */
    private static function darken_hex($hex, $percent) {
        $hex = self::normalize_hex($hex) ?: self::white_label_defaults()['primary_color'];
        $factor = max(0, 100 - absint($percent)) / 100;
        return sprintf('#%02X%02X%02X',
            round(hexdec(substr($hex, 1, 2)) * $factor),
            round(hexdec(substr($hex, 3, 2)) * $factor),
            round(hexdec(substr($hex, 5, 2)) * $factor)
        );
    }

    /** Build a subtle selected/hover surface from the administrator's primary colour. */
    private static function lighten_hex($hex, $percent) {
        $hex = self::normalize_hex($hex) ?: self::white_label_defaults()['primary_color'];
        $factor = min(100, absint($percent)) / 100;
        return sprintf('#%02X%02X%02X',
            round(hexdec(substr($hex, 1, 2)) + (255 - hexdec(substr($hex, 1, 2))) * $factor),
            round(hexdec(substr($hex, 3, 2)) + (255 - hexdec(substr($hex, 3, 2))) * $factor),
            round(hexdec(substr($hex, 5, 2)) + (255 - hexdec(substr($hex, 5, 2))) * $factor)
        );
    }

    public static function routing_defaults() {
        return array(
            'idlm_reps_see_all_leads' => '0',
            'idlm_assignment_mode' => 'manual',
            'idlm_active_assignment_reps' => array(),
            'idlm_active_pre_sales_reps' => null,
            'idlm_rep_studio_assignments' => array(),
            'idlm_source_assignment_mappings' => array(),
            'idlm_studio_mismatch_behavior' => 'general_pool',
            'idlm_handoff_task_policy' => 'transfer',
            'idlm_handoff_notifications_enabled' => '0',
            'idlm_routing_exception_notifications_enabled' => '0',
            'idlm_max_active_leads_per_rep' => 30,
            'idlm_working_hours_start' => '09:00',
            'idlm_working_hours_end' => '18:00',
            'idlm_working_days' => array(1, 2, 3, 4, 5, 6),
            'idlm_presales_calls_per_day' => 2,
            'idlm_presales_whatsapp_per_day' => 1,
            'idlm_fallback_assignment_enabled' => '0',
            'idlm_fallback_assignment_hours' => 24,
            'idlm_qualified_fallback_enabled' => '0',
            'idlm_qualified_fallback_hours' => 4,
            'idlm_qualified_fallback_end_at' => '',
            'idlm_qualified_fallback_include_existing' => '0',
        );
    }

    public static function get($option_name, $default = null) {
        $defaults = self::routing_defaults();
        if (!array_key_exists($option_name, $defaults)) {
            return get_option($option_name, $default);
        }

        // Standalone keys remain authoritative while compatibility mirroring is active.
        $missing = new stdClass();
        $legacy_value = get_option($option_name, $missing);
        if ($missing !== $legacy_value) {
            return $legacy_value;
        }

        $group = get_option(self::ROUTING_OPTION, array());
        if (is_array($group) && array_key_exists($option_name, $group)) {
            return $group[$option_name];
        }

        return null === $default ? $defaults[$option_name] : $default;
    }

    public static function sync_routing_from_legacy() {
        $group = array();
        foreach (self::routing_defaults() as $option_name => $default) {
            $group[$option_name] = get_option($option_name, $default);
        }
        update_option(self::ROUTING_OPTION, $group, false);
        return $group;
    }
}
