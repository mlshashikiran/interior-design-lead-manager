<?php
/** Additive lead-owned ledger declarations. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }
final class IDLM_Lead_Payment_Schema {
    public static function tables() {
        return array('idlm_lead_payment_schedules', 'idlm_lead_payment_confirmations', 'idlm_lead_payment_versions', 'idlm_lead_payment_allocation_sets', 'idlm_lead_payment_events');
    }
    public static function statements() {
        global $wpdb; $c = $wpdb->get_charset_collate(); $p = $wpdb->prefix;
        return array(
            "CREATE TABLE {$p}idlm_lead_payment_schedules (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                lead_id bigint(20) unsigned NOT NULL,
                version_no bigint(20) unsigned NOT NULL,
                quote_id bigint(20) unsigned NOT NULL,
                revision_id bigint(20) unsigned NOT NULL,
                currency varchar(3) NOT NULL,
                basis_total decimal(14,2) NOT NULL,
                snapshot longtext NOT NULL,
                supersedes_id bigint(20) unsigned NOT NULL DEFAULT 0,
                reason text NOT NULL,
                actor_id bigint(20) unsigned NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY lead_version (lead_id, version_no),
                KEY revision_id (revision_id)
            ) ENGINE=InnoDB $c;",
            "CREATE TABLE {$p}idlm_lead_payment_confirmations (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                lead_id bigint(20) unsigned NOT NULL,
                current_version_id bigint(20) unsigned NOT NULL DEFAULT 0,
                created_by bigint(20) unsigned NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY lead_id (lead_id)
            ) ENGINE=InnoDB $c;",
            "CREATE TABLE {$p}idlm_lead_payment_versions (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                lead_id bigint(20) unsigned NOT NULL,
                confirmation_id bigint(20) unsigned NOT NULL,
                version_no bigint(20) unsigned NOT NULL,
                amount decimal(14,2) NOT NULL,
                currency varchar(3) NOT NULL,
                received_date date NOT NULL,
                method varchar(80) NOT NULL,
                reference varchar(255) NOT NULL DEFAULT '',
                normalized_reference varchar(255) NOT NULL DEFAULT '',
                attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
                note text NOT NULL,
                duplicate_review_reason text NOT NULL,
                status varchar(20) NOT NULL,
                reason text NOT NULL,
                supersedes_id bigint(20) unsigned NOT NULL DEFAULT 0,
                actor_id bigint(20) unsigned NOT NULL,
                recorded_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY confirmation_version (confirmation_id, version_no),
                KEY lead_id (lead_id),
                KEY attachment_id (attachment_id)
            ) ENGINE=InnoDB $c;",
            "CREATE TABLE {$p}idlm_lead_payment_allocation_sets (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                lead_id bigint(20) unsigned NOT NULL,
                schedule_id bigint(20) unsigned NOT NULL,
                snapshot longtext NOT NULL,
                actor_id bigint(20) unsigned NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY lead_id (lead_id),
                KEY schedule_id (schedule_id)
            ) ENGINE=InnoDB $c;",
            "CREATE TABLE {$p}idlm_lead_payment_events (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                lead_id bigint(20) unsigned NOT NULL,
                request_key varchar(64) NOT NULL,
                request_hash varchar(64) NOT NULL,
                event_type varchar(50) NOT NULL,
                schedule_id bigint(20) unsigned NOT NULL,
                confirmation_id bigint(20) unsigned NOT NULL DEFAULT 0,
                receipt_version_id bigint(20) unsigned NOT NULL DEFAULT 0,
                allocation_set_id bigint(20) unsigned NOT NULL,
                actor_id bigint(20) unsigned NOT NULL,
                occurred_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY lead_request (lead_id, request_key),
                KEY lead_id (lead_id)
            ) ENGINE=InnoDB $c;"
        );
    }
}
