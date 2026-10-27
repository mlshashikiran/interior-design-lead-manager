<?php
/** Atomic inbound WhatsApp activity and minimal provider replay receipt. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_WhatsApp_Inbox {
    const MAX_MESSAGES = 100;
    const MAX_BYTES = 1048576;

    public static function setting($name, $default = '') {
        if (IDLM_Secret_Store::is_platform_secret($name)) { return IDLM_Secret_Store::get($name, '', true); }
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0269: setting(): fixed lead aliases/tables; phone expression nests prepare(REPLACE) over a literal character list; phone IN parameters/IDs prepared; live canonical matching and receipt writes are transactional.
        $value=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",$name));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->last_error ? new WP_Error('idlm_whatsapp_settings','Unable to verify current webhook settings.') : (null === $value ? $default : maybe_unserialize($value));
    }

    public static function message($provider, $id, $phone, $text) {
        if (!in_array($provider, array('meta','interakt'), true) || !is_string($id) || !preg_match('/^[A-Za-z0-9._:=+\/-]{1,255}$/D', $id) || !is_string($phone) || !preg_match('/^\+?[0-9 () .-]{7,32}$/D', $phone) || !is_string($text) || strlen($text) > 65535) { return false; }
        $phone = IDLM_Lead_Lifecycle_Service::normalize_identity_phone($phone);
        $text = sanitize_textarea_field($text);
        if (!preg_match('/^[1-9][0-9]{6,14}$/D', $phone) || '' === trim($text)) { return false; }
        return array('provider'=>$provider,'id'=>$id,'phone'=>$phone,'text'=>$text);
    }

    public static function response($messages) {
        if (!is_array($messages) || count($messages) > self::MAX_MESSAGES || in_array(false, $messages, true)) { return new WP_REST_Response(array('message'=>'Invalid inbound message identity or content.'),400); }
        $counts=array('processed'=>0,'replayed'=>0,'failed'=>0);$status=200;
        foreach ($messages as $message) {
            $result=self::record($message);
            if (is_wp_error($result)) { $counts['failed']++;$status=503; }
            elseif (in_array($result['status'], array('processed','replayed'), true)) { $counts[$result['status']]++; }
            else { $counts['failed']++; if (200 === $status) { $status=409; } }
        }
        return new WP_REST_Response(array_merge(array('success'=>200 === $status,'message'=>200 === $status ? 'Inbound messages committed.' : 'Some messages require retry or phone/message identity review.'),$counts),$status);
    }

    public static function record($message, $owned_intake = false) {
        global $wpdb;
        $message=is_array($message) ? self::message($message['provider'] ?? null,$message['id'] ?? null,$message['phone'] ?? null,$message['text'] ?? null) : false;
        if (!$message || (IDLM_Lead_Transaction::active() && !$owned_intake)) { return new WP_Error('idlm_whatsapp_context','An independent committed message transaction is required.'); }
        $key='idlm_whatsapp_receipt_'.hash('sha256',$message['provider'].'|'.$message['id']);
        $lock=IDLM_Recovery_Guard::name('wm_'.substr(hash('sha256',$key),0,20));
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0270: record(): fixed lead aliases/tables; phone expression nests prepare(REPLACE) over a literal character list; phone IN parameters/IDs prepared; live canonical matching and receipt writes are transactional.
        if (1 !== (int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)',$lock))) { return new WP_Error('idlm_whatsapp_busy','Message handling is busy. Retry.'); }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        try {
            return IDLM_Lead_Transaction::run(static function() use ($message,$key,$lock,$wpdb) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0271: record(): fixed lead aliases/tables; phone expression nests prepare(REPLACE) over a literal character list; phone IN parameters/IDs prepared; live canonical matching and receipt writes are transactional.
                $engine=IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$wpdb->options)));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                IDLM_Lead_Transaction::required('INNODB' === strtoupper((string)$engine));
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0272: record(): fixed lead aliases/tables; phone expression nests prepare(REPLACE) over a literal character list; phone IN parameters/IDs prepared; live canonical matching and receipt writes are transactional.
                IDLM_Lead_Transaction::required((int)$wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)',$lock)) === (int)$wpdb->get_var('SELECT CONNECTION_ID()'));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                // Read primary committed state, independent of option/object caches.
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0273: record(): fixed lead aliases/tables; phone expression nests prepare(REPLACE) over a literal character list; phone IN parameters/IDs prepared; live canonical matching and receipt writes are transactional.
                $stored=IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE",$key)));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $fingerprint=hash_hmac('sha256',$message['phone'].'|'.$message['text'],wp_salt('auth'));
                if (null !== $stored) {
                    $receipt=@unserialize($stored,array('allowed_classes'=>false));
                    return array('status'=>is_array($receipt) && ($receipt['version']??0)===1 && ($receipt['fingerprint']??'')===$fingerprint && !empty($receipt['activity_id']) ? 'replayed' : 'conflict');
                }
                // Match the same full-phone/India-local normalization used at intake.
                // No suffix lookup: distinct canonical matches require human review.
                $digits='l.phone';
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0274: record(): fixed lead aliases/tables; phone expression nests prepare(REPLACE) over a literal character list; phone IN parameters/IDs prepared; live canonical matching and receipt writes are transactional.
                foreach (array('+',' ','-','(',')','.',"\t","\r","\n") as $char) { $digits=$wpdb->prepare("REPLACE($digits,%s,'')",$char); }
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $phones=array($message['phone']);
                if (12 === strlen($message['phone']) && '91' === substr($message['phone'],0,2)) { $phones[]=substr($message['phone'],2);$phones[]='0'.substr($message['phone'],2); }
                $placeholders=implode(',',array_fill(0,count($phones),'%s'));
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- REL007-SQL-0275: record(): fixed lead aliases/tables; phone expression nests prepare(REPLACE) over a literal character list; phone IN parameters/IDs prepared; live canonical matching and receipt writes are transactional.
                $leads=IDLM_Lead_Transaction::read($wpdb->get_results($wpdb->prepare("SELECT DISTINCT c.id, c.phone FROM {$wpdb->prefix}idlm_leads l INNER JOIN {$wpdb->prefix}idlm_leads c ON c.id=CASE WHEN l.is_duplicate=1 THEN l.duplicate_of_id ELSE l.id END WHERE c.is_duplicate=0 AND $digits IN ($placeholders) ORDER BY c.id LIMIT 3 FOR UPDATE",$phones)));
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                if (count($leads)!==1 || IDLM_Lead_Lifecycle_Service::normalize_identity_phone($leads[0]->phone)!==$message['phone']) { return array('status'=>'unmatched_or_ambiguous'); }
                $prefix='meta' === $message['provider'] ? '[Direct WA Reply]: ' : '[WhatsApp Reply]: ';
                IDLM_Lead_Transaction::required(IDLM_DB::log_activity($leads[0]->id,'whatsapp_reply',$prefix.$message['text'],0));
                $receipt=array('version'=>1,'fingerprint'=>$fingerprint,'activity_id'=>(int)$wpdb->insert_id,'received_at'=>time());
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0276: record(): fixed lead aliases/tables; phone expression nests prepare(REPLACE) over a literal character list; phone IN parameters/IDs prepared; live canonical matching and receipt writes are transactional.
                IDLM_Lead_Transaction::required(1 === $wpdb->insert($wpdb->options,array('option_name'=>$key,'option_value'=>serialize($receipt),'autoload'=>'no')));
                IDLM_Lead_Transaction::required(IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",$key))) === serialize($receipt));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                return array('status'=>'processed');
            });
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0277: record(): fixed lead aliases/tables; phone expression nests prepare(REPLACE) over a literal character list; phone IN parameters/IDs prepared; live canonical matching and receipt writes are transactional.
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
