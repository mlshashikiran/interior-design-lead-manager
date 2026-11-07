<?php
/**
 * Bounded audit trail of who changed CRM access. Builder: Shashi.
 *
 * One non-autoloaded option, created by the first logged change and never by
 * activation, upgrade or page load. Holds staff names, role slugs and capability
 * names only: never emails, phone numbers, passwords or tokens. Recording can
 * never block the save that triggered it.
 */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Access_Change_Log {
    const OPTION = 'idlm_access_change_log';
    const MAX_ENTRIES = 200;

    public static function events() {
        return array(
            'user_created' => 'Person added',
            'role_changed' => 'Role changed',
            'access_suspended' => 'Access suspended',
            'access_restored' => 'Access restored',
            'role_scope_changed' => 'Role lead visibility changed',
            'role_permissions_changed' => 'Role permissions changed',
        );
    }

    /**
     * Add one entry, newest first, keeping the newest MAX_ENTRIES.
     *
     * @param array $before Only roles, scope, added and removed are kept.
     * @param array $after  Only roles, scope, added and removed are kept.
     * @return bool Whether the entry was handed to storage. Callers ignore this on purpose.
     */
    public static function record($event, $target_type, $target_id, $target_name, $before = array(), $after = array()) {
        try {
            if (!isset(self::events()[$event]) || !in_array($target_type, array('user', 'role'), true)) { return false; }
            $actor_id = (int) get_current_user_id();
            $actor = wp_get_current_user();
            $entry = array(
                'time' => time(),
                'actor_id' => $actor_id,
                'actor_name' => self::safe_name($actor->display_name ?? '', $actor_id),
                'event' => $event,
                'target_type' => $target_type,
                'target_id' => 'user' === $target_type ? (int) $target_id : self::clean_slug($target_id),
                'target_name' => 'user' === $target_type ? self::safe_name($target_name, (int) $target_id) : self::clean_slug($target_name),
                'before' => self::clean_values($before),
                'after' => self::clean_values($after),
            );
            $stored = get_option(self::OPTION, false);
            $entries = array_slice(array_merge(array($entry), is_array($stored) ? $stored : array()), 0, self::MAX_ENTRIES);
            if (false === $stored) {
                add_option(self::OPTION, $entries, '', 'no');
            } else {
                update_option(self::OPTION, $entries, false);
            }
            return true;
        } catch (\Throwable $error) {
            return false;
        }
    }

    /** Newest first. Reading never creates the option. */
    public static function entries() {
        $stored = get_option(self::OPTION, array());
        return is_array($stored) ? array_values(array_filter($stored, 'is_array')) : array();
    }

    /** Plain text for one entry's change. Callers escape on output. */
    public static function describe($entry) {
        $before = is_array($entry['before'] ?? null) ? $entry['before'] : array();
        $after = is_array($entry['after'] ?? null) ? $entry['after'] : array();
        switch ($entry['event'] ?? '') {
            case 'user_created':
                return 'Role: ' . self::role_names($after['roles'] ?? array());
            case 'role_changed':
                return self::role_names($before['roles'] ?? array()) . ' to ' . self::role_names($after['roles'] ?? array());
            case 'role_scope_changed':
                return self::scope_name($before['scope'] ?? '') . ' to ' . self::scope_name($after['scope'] ?? '');
            case 'role_permissions_changed':
                $parts = array();
                if (!empty($after['added'])) { $parts[] = 'Added: ' . self::permission_names($after['added']); }
                if (!empty($before['removed'])) { $parts[] = 'Removed: ' . self::permission_names($before['removed']); }
                return implode('. ', $parts);
        }
        return '';
    }

    private static function clean_values($values) {
        $clean = array();
        foreach (array('roles', 'added', 'removed') as $list) {
            if (isset($values[$list]) && is_array($values[$list])) {
                $clean[$list] = array_values(array_map(array(__CLASS__, 'clean_slug'), array_slice($values[$list], 0, 50)));
            }
        }
        if (isset($values['scope'])) { $clean['scope'] = self::clean_slug($values['scope']); }
        return $clean;
    }

    private static function clean_slug($value) {
        return substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value), 0, 80);
    }

    /** A display name can hold an email or phone number on older accounts. Never store those. */
    private static function safe_name($name, $user_id) {
        $name = trim(wp_strip_all_tags((string) $name));
        if ('' === $name || false !== strpos($name, '@') || preg_match('/\d[\d\s().+-]{5,}\d/', $name)) {
            return sprintf('User #%d', (int) $user_id);
        }
        // By character: a split multibyte name is invalid UTF-8, which the options table refuses and esc_html() blanks.
        return mb_substr($name, 0, 100, 'UTF-8');
    }

    private static function role_names($slugs) {
        $names = array_map(array('IDLM_Admin_Users_Controller', 'role_label'), (array) $slugs);
        return $names ? implode(', ', $names) : 'No role';
    }

    private static function scope_name($scope) {
        $words = array('default' => 'Default', 'all' => 'All leads', 'assigned' => 'Only assigned leads');
        return $words[$scope] ?? (string) $scope;
    }

    private static function permission_names($capabilities) {
        $labels = IDLM_Admin_Users_Controller::selectable_permissions('');
        return implode(', ', array_map(static function($capability) use ($labels) {
            return $labels[$capability] ?? (string) $capability;
        }, (array) $capabilities));
    }
}
