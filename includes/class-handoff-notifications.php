<?php
/** Qualified handoff email delivery. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Handoff_Notifications {
    public static function send($lead_id, $old_owner_id, $new_owner_id, $activity_message) {
        if ('1' !== IDLM_Settings::get('idlm_handoff_notifications_enabled', '0')) {
            return;
        }

        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0436 (moved in M6): send(): fixed plugin-prefixed lead lookup; lead ID uses %d/absint; read current recipient context at dispatch without caching. Only opt-in handoff mail uses these fields.
        $lead = $wpdb->get_row($wpdb->prepare(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT first_name, last_name, preferred_studio FROM {$wpdb->prefix}idlm_leads WHERE id = %d",
            absint($lead_id)
        ));
        $old_owner = get_userdata($old_owner_id);
        $new_owner = get_userdata($new_owner_id);
        $recipients = array_filter(array(
            $old_owner ? $old_owner->user_email : '',
            $new_owner ? $new_owner->user_email : '',
        ), 'is_email');
        if (empty($recipients)) {
            return;
        }

        $lead_name = $lead ? trim($lead->first_name . ' ' . $lead->last_name) : 'Lead #' . absint($lead_id);
        $subject = sprintf('[Lead Manager] Qualified lead assigned: %s', $lead_name);
        $body = $activity_message . "\n\nLead: " . $lead_name;
        if ($lead && !empty($lead->preferred_studio)) {
            $body .= "\nPreferred Studio: " . $lead->preferred_studio;
        }
        $sent = wp_mail(array_values(array_unique($recipients)), $subject, $body);
        if (!$sent && IDLM_Lead_Outbox::dispatching()) { throw new RuntimeException('Handoff notification was not accepted.'); }
    }

}
