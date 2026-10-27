<?php
/** Minimal CAPI delivery evidence independent of diagnostic retention. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_CAPI_Delivery {
    const PREFIX = 'idlm_capi_delivery_';

    public static function key($lead_id, $platform, $event) {
        return self::PREFIX . absint($lead_id) . '_' . hash('sha256', $platform . '|' . $event);
    }

    public static function read($lead_id, $platform, $event) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0528: read(): only fixed options/lead/log tables; receipt/lead/engine values prepared; claim/acknowledgement and generation reads require live transactional state.
        $raw = IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE", self::key($lead_id, $platform, $event)
        )));
        if (null === $raw) { return null; }
        $receipt = @unserialize($raw, array('allowed_classes' => false));
        IDLM_Lead_Transaction::required(is_array($receipt) && ($receipt['version'] ?? 0) === 1
            && ($receipt['lead_id'] ?? null) === (int) $lead_id && ($receipt['platform'] ?? '') === $platform
            && ($receipt['event'] ?? '') === $event && is_bool($receipt['sent'] ?? null)
            && in_array($receipt['status'] ?? '', array('processing', 'sent', 'failed', 'uncertain'), true));
        return $receipt;
    }

    private static function engines() {
        global $wpdb;
        foreach (array($wpdb->options, $wpdb->prefix . 'idlm_capi_logs') as $table) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0529: engines(): only fixed options/lead/log tables; receipt/lead/engine values prepared; claim/acknowledgement and generation reads require live transactional state.
            $engine = IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table)));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            IDLM_Lead_Transaction::required('INNODB' === strtoupper((string) $engine));
        }
    }

    private static function save($receipt) {
        global $wpdb;
        IDLM_Lead_Transaction::required(IDLM_Lead_Transaction::active());
        $key = self::key($receipt['lead_id'], $receipt['platform'], $receipt['event']);
        $value = serialize($receipt);
        $existing = self::read($receipt['lead_id'], $receipt['platform'], $receipt['event']);
        $saved = null === $existing
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0530: save(): only fixed options/lead/log tables; receipt/lead/engine values prepared; claim/acknowledgement and generation reads require live transactional state.
            ? $wpdb->insert($wpdb->options, array('option_name'=>$key, 'option_value'=>$value, 'autoload'=>'no'))
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            : $wpdb->update($wpdb->options, array('option_value'=>$value, 'autoload'=>'no'), array('option_name'=>$key));
        IDLM_Lead_Transaction::required($saved);
        IDLM_Lead_Transaction::required(self::read($receipt['lead_id'], $receipt['platform'], $receipt['event']) === $receipt);
        wp_cache_delete($key, 'options'); wp_cache_delete('notoptions', 'options');
        return true;
    }

    /** Confirmed legacy successes must be copied in the same transaction as pruning. */
    public static function remember_success($lead_id, $platform, $event, $created_at) {
        IDLM_Lead_Transaction::required(IDLM_Lead_Transaction::active() && absint($lead_id) > 0
            && in_array($platform, array('meta', 'google', 'linkedin'), true) && is_string($event) && $event !== '');
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0531: remember_success(): only fixed options/lead/log tables; receipt/lead/engine values prepared; claim/acknowledgement and generation reads require live transactional state.
        $lead = IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_leads WHERE id = %d FOR UPDATE", $lead_id)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if (!$lead) { return true; } // Do not recreate evidence for an already erased lead.
        $receipt = self::read($lead_id, $platform, $event);
        if ($receipt && $receipt['sent']) { return true; }
        $receipt = $receipt ?: array('version'=>1, 'lead_id'=>(int) $lead_id, 'platform'=>$platform, 'event'=>$event, 'status'=>'sent', 'sent'=>false, 'attempt_id'=>'', 'updated_at'=>$created_at);
        // Historical success never resolves an interrupted newer forced attempt.
        $receipt['sent'] = true;
        return self::save($receipt);
    }

    /** Caller holds the existing platform/lead session lock. No HTTP in this transaction. */
    public static function claim($delivery, $force, $history_check, $lock) {
        global $wpdb;
        return IDLM_Lead_Transaction::run(static function() use ($delivery, $force, $history_check, $lock, $wpdb) {
            self::engines();
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0532: claim(): only fixed options/lead/log tables; receipt/lead/engine values prepared; claim/acknowledgement and generation reads require live transactional state.
            IDLM_Lead_Transaction::required((int) $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lock)) === (int) $wpdb->get_var('SELECT CONNECTION_ID()'));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            // Read history before the receipt's current locking read: retention may
            // have just replaced log evidence with a committed receipt.
            $blocked = call_user_func($history_check);
            $receipt = self::read($delivery['lead_id'], $delivery['platform'], $delivery['event']);
            // Older failed receipts did not distinguish rejection from HTTP 5xx
            // or an unrecognized success body. Their outcome cannot be inferred.
            if ($receipt && $receipt['status'] === 'failed' && empty($receipt['rejection_confirmed'])) {
                $receipt['status'] = 'uncertain'; self::save($receipt);
                return array('allowed'=>false, 'message'=>'A prior delivery outcome is uncertain; reconciliation is required.');
            }
            if ($receipt && in_array($receipt['status'], array('processing', 'uncertain'), true)) {
                $receipt['status'] = 'uncertain'; self::save($receipt);
                return array('allowed'=>false, 'message'=>'Delivery outcome is uncertain; reconciliation is required.');
            }
            if ($blocked || (!$force && $receipt && $receipt['sent'])) { return array('allowed'=>false, 'message'=>'Prior accepted or uncertain delivery evidence blocks this event. No new request was sent.'); }
            $receipt = $receipt ?: array('version'=>1, 'lead_id'=>(int) $delivery['lead_id'], 'platform'=>$delivery['platform'], 'event'=>$delivery['event'], 'sent'=>false);
            $receipt['status']='processing'; $receipt['attempt_id']=$delivery['attempt_id']; $receipt['updated_at']=current_time('mysql', true);
            self::save($receipt);
            return array('allowed'=>true);
        });
    }

    /** Log and receipt acknowledgement commit together after transport completes. */
    public static function finish($delivery, $outcome) {
        self::engines();
        $receipt = self::read($delivery['lead_id'], $delivery['platform'], $delivery['event']);
        IDLM_Lead_Transaction::required($receipt && $receipt['status'] === 'processing' && $receipt['attempt_id'] === $delivery['attempt_id']);
        $receipt['status'] = $outcome === 'success' ? 'sent' : ($outcome === 'uncertain' ? 'uncertain' : 'failed');
        $receipt['rejection_confirmed'] = $outcome === 'failed';
        $receipt['sent'] = $receipt['sent'] || $outcome === 'success'; $receipt['updated_at']=current_time('mysql', true);
        return self::save($receipt);
    }

    /** Explicit erasure follows lead deletion; routine retention never removes receipts. */
    public static function erase_leads($lead_ids) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0533: erase_leads(): only fixed options/lead/log tables; receipt/lead/engine values prepared; claim/acknowledgement and generation reads require live transactional state.
        $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $wpdb->options));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->last_error || strtoupper((string) $engine) !== 'INNODB') { return false; }
        foreach ($lead_ids as $id) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0534: erase_leads(): only fixed options/lead/log tables; receipt/lead/engine values prepared; claim/acknowledgement and generation reads require live transactional state.
            if (false === $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like(self::PREFIX . absint($id) . '_') . '%'))) { return false; }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }
        return true;
    }
}
