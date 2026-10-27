<?php
/** Bounded audience work, current exclusions and durable outcomes. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Audience_Dispatch {
    const PREFIX = 'idlm_audience_work_';
    const QUEUE = 'idlm_audience_sync_queue';
    const SCAN = 'idlm_audience_scan';
    const HOOK = 'idlm_audience_continue';
    const PAGE_SIZE = 100;
    const MAX_PAGES = 3;
    const MAX_ATTEMPTS = 3;

    public static function rule_key($rule) {
        $identity = array();
        foreach (array('platform', 'audience_id', 'stage', 'call_status', 'lead_quality') as $field) { $identity[$field] = (string) ($rule[$field] ?? 'all'); }
        $identity['is_value'] = !empty($rule['is_value']) && (string) $rule['is_value'] === '1';
        return hash('sha256', serialize($identity));
    }
    private static function destination($rule) { return hash('sha256', $rule['platform'].'|'.(string)$rule['audience_id']); }
    public static function eligible($lead, $rule) {
        if (!$lead || !empty($lead->is_duplicate)) { return false; }
        foreach (array('stage', 'call_status', 'lead_quality') as $field) {
            if (($rule[$field] ?? 'all') !== 'all' && (string) ($lead->$field ?? '') !== (string) $rule[$field]) { return false; }
        }
        return true;
    }
    private static function lock_name() { return IDLM_Recovery_Guard::name('audience'); }
    private static function owns_lock() {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0508: owns_lock(): fixed prefixed options/lead/log tables; rule_conditions prepares values for fixed stage/call/quality fields; cursor/limit/IDs bound; exclusion/claim checks are live.
        return IDLM_Recovery_Guard::lease_valid() && (int) $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', self::lock_name())) === (int) $wpdb->get_var('SELECT CONNECTION_ID()');
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
    private static function locked($callback) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0509: locked(): fixed prefixed options/lead/log tables; rule_conditions prepares values for fixed stage/call/quality fields; cursor/limit/IDs bound; exclusion/claim checks are live.
        if (IDLM_Lead_Transaction::active() || 1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', self::lock_name()))) { return self::error('Audience work is busy. Retry shortly.'); }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        try { return call_user_func($callback); }
        catch (Throwable $error) { return self::error('Unable to persist audience work. Pending work was retained.'); }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0510: locked(): fixed prefixed options/lead/log tables; rule_conditions prepares values for fixed stage/call/quality fields; cursor/limit/IDs bound; exclusion/claim checks are live.
        finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::lock_name())); }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
    private static function error($message) { return new WP_Error('idlm_audience_work', $message); }
    private static function engines() {
        global $wpdb;
        foreach (array($wpdb->options, $wpdb->prefix.'idlm_audience_sync_logs') as $table) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0511: engines(): fixed prefixed options/lead/log tables; rule_conditions prepares values for fixed stage/call/quality fields; cursor/limit/IDs bound; exclusion/claim checks are live.
            $engine = IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table)));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            IDLM_Lead_Transaction::required(strtoupper((string) $engine) === 'INNODB');
        }
        IDLM_Lead_Transaction::required(self::owns_lock());
    }
    private static function read($key, $default = null) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0512: read(): fixed prefixed options/lead/log tables; rule_conditions prepares values for fixed stage/call/quality fields; cursor/limit/IDs bound; exclusion/claim checks are live.
        $raw = IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE", $key)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if (null === $raw) { return $default; }
        $value = @unserialize($raw, array('allowed_classes'=>false));
        IDLM_Lead_Transaction::required(is_array($value));
        return $value;
    }
    private static function save($key, $value) {
        global $wpdb;
        IDLM_Lead_Transaction::required(IDLM_Lead_Transaction::active());
        $old = self::read($key);
        if ($old === $value) { return true; }
        $fields = array('option_value'=>serialize($value), 'autoload'=>'no');
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0513: save(): fixed prefixed options/lead/log tables; rule_conditions prepares values for fixed stage/call/quality fields; cursor/limit/IDs bound; exclusion/claim checks are live.
        $saved = null === $old ? $wpdb->insert($wpdb->options, array_merge(array('option_name'=>$key), $fields)) : $wpdb->update($wpdb->options, $fields, array('option_name'=>$key));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        IDLM_Lead_Transaction::required($saved);
        IDLM_Lead_Transaction::required(self::read($key) === $value);
        wp_cache_delete($key, 'options'); wp_cache_delete('notoptions', 'options'); wp_cache_delete('alloptions', 'options');
        return true;
    }
    private static function queue() {
        $ids = self::read(self::QUEUE, array());
        foreach ($ids as $id) { IDLM_Lead_Transaction::required(is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0); }
        return array_values(array_unique(array_map('intval', $ids)));
    }
    private static function work($id) {
        $work = self::read(self::PREFIX.$id);
        if (null === $work) { return array('version'=>1, 'generation'=>IDLM_Recovery_Guard::generation(), 'lead_id'=>(int) $id, 'manual_targets'=>array(), 'states'=>array()); }
        IDLM_Lead_Transaction::required(($work['version'] ?? 0) === 1 && ($work['lead_id'] ?? 0) === (int) $id && ($work['generation'] ?? null) === IDLM_Recovery_Guard::generation() && is_array($work['manual_targets'] ?? null) && is_array($work['states'] ?? null));
        foreach ($work['states'] as $key=>$state) {
            IDLM_Lead_Transaction::required(preg_match('/^[a-f0-9]{64}$/D', $key) && is_array($state) && in_array($state['status'] ?? '', array('pending','processing','sent','excluded','failed','blocked','uncertain'), true) && is_int($state['attempts'] ?? null) && $state['attempts'] >= 0 && is_int($state['next_at'] ?? null) && $state['attempts'] <= self::MAX_ATTEMPTS && $state['next_at'] >= 0 && is_string($state['period'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $state['period']) && is_string($state['destination'] ?? null) && preg_match('/^[a-f0-9]{64}$/D',$state['destination']));
        }
        foreach ($work['manual_targets'] as $key) { IDLM_Lead_Transaction::required(isset($work['states'][$key])); }
        return $work;
    }
    private static function pending($rule) { return array('status'=>'pending', 'attempts'=>0, 'next_at'=>0, 'period'=>gmdate('Y-m-d'), 'destination'=>self::destination($rule)); }

    /** Stop selected manual work; retain unknown outcomes as scheduled-send fences. */
    public static function cancel($ids) {
        if (!is_array($ids) || !$ids || count($ids)>self::PAGE_SIZE || count(array_filter($ids,'is_scalar'))!==count($ids)) { return self::error('Select between 1 and 100 leads per audience batch.'); }
        $ids=array_values(array_unique(array_filter(array_map('absint',$ids))));
        return IDLM_Recovery_Guard::run(static function() use ($ids) {
            return self::locked(static function() use ($ids) {
                return IDLM_Lead_Transaction::run(static function() use ($ids) {
                    self::engines(); $queue=self::queue();
                    foreach ($ids as $id) { $work=self::work($id); $work['manual_targets']=array(); self::save(self::PREFIX.$id,$work); }
                    self::save(self::QUEUE,array_values(array_diff($queue,$ids)));
                    return array('removed'=>count(array_intersect($queue,$ids)));
                });
            });
        });
    }

    /** Existing object access checks remain at the AJAX boundary. */
    public static function enqueue($ids) {
        if (!is_array($ids) || !$ids || count($ids) > self::PAGE_SIZE || count(array_filter($ids, 'is_scalar')) !== count($ids)) { return self::error('Select between 1 and 100 leads per audience batch.'); }
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        return IDLM_Recovery_Guard::run(static function() use ($ids) {
            return self::locked(static function() use ($ids) {
                return IDLM_Lead_Transaction::run(static function() use ($ids) {
                    global $wpdb; self::engines(); $queue = self::queue(); $rules = IDLM_Audiences::get_active_rules();
                    foreach ($ids as $id) {
                        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0514: enqueue(): fixed prefixed options/lead/log tables; rule_conditions prepares values for fixed stage/call/quality fields; cursor/limit/IDs bound; exclusion/claim checks are live.
                        IDLM_Lead_Transaction::required(IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_leads WHERE id = %d FOR UPDATE", $id))) > 0);
                        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        $work = self::work($id); $existing = in_array($id, $queue, true);
                        if ($rules) { $work['manual_targets'] = array(); } // Explicit selection binds current saved definitions.
                        foreach ($rules as $rule) {
                            $key = self::rule_key($rule); $state = $work['states'][$key] ?? null;
                            // Explicit reselection retries confirmed failures, never unknown outcomes.
                            if (!$state || !in_array($state['status'], array('processing','uncertain'), true) && (!$existing || in_array($state['status'], array('failed','blocked'), true))) { $work['states'][$key] = self::pending($rule); }
                            if (!in_array($key, $work['manual_targets'], true)) { $work['manual_targets'][] = $key; }
                        }
                        self::save(self::PREFIX.$id, $work);
                        IDLM_Lead_Transaction::required(IDLM_DB::log_activity($id, 'audience_queue', 'Lead selected for audience sync; current rule exclusions apply.', 0));
                    }
                    self::save(self::QUEUE, array_values(array_unique(array_merge($queue, $ids))));
                    return array('queued'=>count($ids));
                });
            });
        });
    }
    public static function run($manual_only = false, $generation = null, $period = null) {
        if (null !== $period && $period !== gmdate('Y-m-d')) { return array('stale'=>true); }
        $result = IDLM_Recovery_Guard::run(static function() use ($manual_only) {
            return self::locked(static function() use ($manual_only) { return self::run_work($manual_only); });
        }, $generation);
        return false === $result ? self::error('CRM recovery is busy. Pending audience work was retained.') : $result;
    }
    public static function resume($generation, $manual_only, $period) { return self::run((bool) $manual_only, $generation, $period); }
    private static function schedule($manual_only, $delay = 60) {
        $args = array(IDLM_Recovery_Guard::generation(), (bool) $manual_only, gmdate('Y-m-d'));
        if (function_exists('as_schedule_single_action')) {
            if (!as_next_scheduled_action(self::HOOK, $args)) { as_schedule_single_action(time()+max(60, $delay), self::HOOK, $args); }
        } elseif (!wp_next_scheduled(self::HOOK, $args)) { wp_schedule_single_event(time()+max(60, $delay), self::HOOK, $args); }
    }
    private static function run_work($manual_only) {
        $rules = array(); foreach (IDLM_Audiences::get_active_rules() as $rule) { $rules[self::rule_key($rule)] = $rule; }
        $queue = self::queue();
        $summary = array('accepted'=>0, 'mapped'=>0, 'excluded'=>0, 'failed'=>0, 'uncertain'=>0, 'pending'=>count($queue), 'pages'=>0, 'mode'=>$manual_only ? 'manual' : 'scheduled');
        if (!$rules) { return $summary; }
        $deadline = microtime(true)+35; $selected = array_slice($queue, 0, self::PAGE_SIZE); $retry = false;
        // Adopt old ID-only queues without trusting their original eligibility.
        $adopted = IDLM_Lead_Transaction::run(static function() use ($selected, $rules) {
            self::engines();
            foreach ($selected as $id) {
                $work = self::work($id);
                if (!$work['manual_targets']) {
                    foreach ($rules as $key=>$rule) { $work['manual_targets'][]=$key; $work['states'][$key]=$work['states'][$key] ?? self::pending($rule); }
                    self::save(self::PREFIX.$id, $work);
                }
            }
            return true;
        });
        if (is_wp_error($adopted)) { return $adopted; }
        foreach ($rules as $key=>$rule) {
            if ($summary['pages'] >= self::MAX_PAGES || microtime(true) >= $deadline) { $retry = true; break; }
            if ($selected) { $retry = self::page($selected, $key, $rule, true, $summary) || $retry; }
        }
        // Queue cleanup reads current state inside the same primary transaction;
        // it never replaces a stale enqueue snapshot.
        $cleaned = IDLM_Lead_Transaction::run(static function() use ($selected) {
            global $wpdb;
            self::engines(); $current = self::queue(); $remaining = array();
            foreach ($current as $id) {
                if (!in_array($id, $selected, true)) { $remaining[]=$id; continue; }
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0515: run_work(): fixed prefixed options/lead/log tables; rule_conditions prepares values for fixed stage/call/quality fields; cursor/limit/IDs bound; exclusion/claim checks are live.
                $exists=IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_leads WHERE id = %d FOR UPDATE",$id)));
                if (!$exists) { IDLM_Lead_Transaction::required(false!==$wpdb->delete($wpdb->options,array('option_name'=>self::PREFIX.$id))); wp_cache_delete(self::PREFIX.$id,'options'); continue; }
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $work=self::work($id); $complete=!empty($work['manual_targets']);
                foreach ($work['manual_targets'] as $key) { if (!in_array($work['states'][$key]['status'], array('sent','excluded'), true)) { $complete=false; } }
                if (!$complete) { $remaining[]=$id; }
            }
            // Rotate unfinished selected work so a blocked head cannot starve later IDs.
            $head=array_values(array_diff($remaining, $selected)); $tail=array_values(array_intersect($remaining, $selected));
            self::save(self::QUEUE, array_merge($head, $tail)); return count($remaining);
        });
        if (is_wp_error($cleaned)) { return $cleaned; } $summary['pending']=$cleaned;
        if (!$manual_only) {
            foreach ($rules as $key=>$rule) {
                if ($summary['pages'] >= self::MAX_PAGES || microtime(true) >= $deadline) { $retry=true; break; }
                $retry=self::scheduled_page($key, $rule, $queue, $summary) || $retry;
            }
        }
        if (count($queue) > self::PAGE_SIZE || $retry || array_diff(self::queue(), $selected)) { self::schedule($manual_only); }
        return $summary;
    }
    private static function scheduled_page($key, $rule, $queue, &$summary) {
        global $wpdb;
        if (!IDLM_Audiences::has_credentials($rule)) { return false; } // Hold cursor without a retry loop until configuration is repaired.
        $scan=self::read(self::SCAN, array()); $period=gmdate('Y-m-d');
        $entry=$scan[$key] ?? array();
        if (($entry['period'] ?? '') !== $period || ($entry['generation'] ?? null) !== IDLM_Recovery_Guard::generation()) { $entry=array('period'=>$period,'generation'=>IDLM_Recovery_Guard::generation(),'cursor'=>0,'done'=>false); }
        IDLM_Lead_Transaction::required(is_int($entry['cursor'] ?? null) && is_bool($entry['done'] ?? null));
        if ($entry['done']) { return false; }
        $where=IDLM_Audiences::rule_conditions($rule);
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0516: scheduled_page(): fixed prefixed options/lead/log tables; rule_conditions prepares values for fixed stage/call/quality fields; cursor/limit/IDs bound; exclusion/claim checks are live.
        $ids=IDLM_Lead_Transaction::read($wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_leads WHERE {$where} AND id > %d ORDER BY id ASC LIMIT %d", $entry['cursor'], self::PAGE_SIZE)));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $ids=array_map('intval', $ids);
        $retry=$ids ? self::page(array_values(array_diff($ids, $queue)), $key, $rule, false, $summary) : false;
        // Retry this page until all retryable failures become terminal; success
        // receipts skip already accepted contacts on that repeated page.
        if (!$retry) { $entry['cursor']=$ids ? max($ids) : $entry['cursor']; $entry['done']=count($ids)<self::PAGE_SIZE; }
        $saved=IDLM_Lead_Transaction::run(static function() use ($key, $entry) { self::engines(); $scan=self::read(self::SCAN,array()); $scan[$key]=$entry; return self::save(self::SCAN,$scan); });
        IDLM_Lead_Transaction::required($saved);
        return $retry || !$entry['done'];
    }
    private static function page($ids, $key, $rule, $manual, &$summary) {
        global $wpdb;
        $claim=IDLM_Lead_Transaction::run(static function() use ($ids,$key,$rule,$manual,$wpdb) {
            self::engines(); $claimed=array(); $excluded=0; $retry=false;
            // Credentials and enabled rule definition are reloaded at dispatch.
            // A long-lived worker must not rely on its earlier local option cache.
            wp_cache_delete('alloptions','options'); wp_cache_delete('notoptions','options');
            foreach (array('idlm_audience_sync_rules','idlm_meta_access_token','idlm_linkedin_access_token','idlm_sync_meta_audience','idlm_meta_audience_id','idlm_sync_google_audience','idlm_google_user_list_resource','idlm_sync_linkedin_audience','idlm_linkedin_segment_urn') as $name) { wp_cache_delete($name,'options'); }
            $active=array_map(array(__CLASS__,'rule_key'), IDLM_Audiences::get_active_rules());
            if (!in_array($key,$active,true) || !IDLM_Audiences::has_credentials($rule)) { return array('leads'=>array(),'excluded'=>0,'retry'=>false); }
            foreach ($ids as $id) {
                $work=self::work($id); if ($manual && !in_array($key,$work['manual_targets'],true)) { continue; }
                $state=$work['states'][$key] ?? self::pending($rule);
                foreach ($work['states'] as $prior) {
                    if ($prior['destination']===self::destination($rule) && in_array($prior['status'],array('processing','uncertain'),true)) { $state['status']='uncertain'; $work['states'][$key]=$state; self::save(self::PREFIX.$id,$work); break; }
                }
                if ($state['status']==='processing') { $state['status']='uncertain'; $work['states'][$key]=$state; self::save(self::PREFIX.$id,$work); }
                if ($state['status']==='uncertain') { continue; }
                if (!$manual && $state['period'] !== gmdate('Y-m-d') && in_array($state['status'],array('sent','excluded'),true)) { $state=self::pending($rule); }
                if (in_array($state['status'],array('sent','excluded','blocked'),true)) { continue; }
                if ($state['next_at']>time()) { $retry=true; continue; }
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0517: page(): fixed prefixed options/lead/log tables; rule_conditions prepares values for fixed stage/call/quality fields; cursor/limit/IDs bound; exclusion/claim checks are live.
                $lead=IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d FOR UPDATE",$id)));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                if (!self::eligible($lead,$rule) || $rule['platform']==='linkedin' && empty($lead->email) || $rule['platform']==='meta' && empty($lead->phone) && empty($lead->email)) {
                    $state['status']='excluded'; $excluded++;
                } else { $state['status']='processing'; $state['attempts']++; $claimed[]=$lead; }
                $work['states'][$key]=$state; self::save(self::PREFIX.$id,$work);
            }
            return array('leads'=>$claimed,'excluded'=>$excluded,'retry'=>$retry);
        });
        IDLM_Lead_Transaction::required($claim); $summary['excluded']+=$claim['excluded'];
        if (!$claim['leads']) { return $claim['retry']; }
        $summary['pages']++;
        // A generation/session failure after claim is deliberately left uncertain.
        if (!self::owns_lock()) { return false; }
        $outcome=IDLM_Audiences::send_page($claim['leads'],$rule);
        if (!self::owns_lock()) { return false; }
        $finished=IDLM_Lead_Transaction::run(static function() use ($claim,$key,$rule,$outcome) {
            self::engines();
            IDLM_Lead_Transaction::required(IDLM_DB::log_audience_sync($rule['platform'],$rule['audience_id'],count($claim['leads']),$outcome['payload'],$outcome['response'],$outcome['status']==='sent' ? 'success' : 'failed') > 0);
            $retry=false;
            foreach ($claim['leads'] as $lead) {
                $work=self::work((int)$lead->id); $state=$work['states'][$key] ?? null; IDLM_Lead_Transaction::required($state && $state['status']==='processing');
                $state['status']=$outcome['status'];
                if ($state['status']==='failed') {
                    if ($state['attempts']>=self::MAX_ATTEMPTS) { $state['status']='blocked'; }
                    else { $state['next_at']=time()+min(3600,max(60,(int)$outcome['retry_after'],60*$state['attempts'])); $retry=true; }
                }
                $work['states'][$key]=$state; self::save(self::PREFIX.$lead->id,$work);
            }
            return array('retry'=>$retry);
        });
        if (is_wp_error($finished)) { $summary['uncertain']+=count($claim['leads']); return false; }
        $field=$outcome['status']==='sent' ? ($rule['platform']==='google' ? 'mapped' : 'accepted') : ($outcome['status']==='uncertain' ? 'uncertain' : 'failed'); $summary[$field]+=count($claim['leads']);
        return $claim['retry'] || $finished['retry'];
    }
    /** Caller owns erasure transaction. No external request can be recalled. */
    public static function erase_leads($ids) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0518: erase_leads(): fixed prefixed options/lead/log tables; rule_conditions prepares values for fixed stage/call/quality fields; cursor/limit/IDs bound; exclusion/claim checks are live.
        $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$wpdb->options));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->last_error || strtoupper((string)$engine)!=='INNODB') { return false; }
        try {
            $queue=self::queue();
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0519: erase_leads(): fixed prefixed options/lead/log tables; rule_conditions prepares values for fixed stage/call/quality fields; cursor/limit/IDs bound; exclusion/claim checks are live.
            foreach ($ids as $id) { if (false===$wpdb->delete($wpdb->options,array('option_name'=>self::PREFIX.absint($id)))) { return false; } wp_cache_delete(self::PREFIX.absint($id),'options'); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            $remaining=array_values(array_diff($queue,array_map('intval',$ids)));
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0520: erase_leads(): fixed prefixed options/lead/log tables; rule_conditions prepares values for fixed stage/call/quality fields; cursor/limit/IDs bound; exclusion/claim checks are live.
            if ($queue!==$remaining && false===$wpdb->update($wpdb->options,array('option_value'=>serialize($remaining)),array('option_name'=>self::QUEUE))) { return false; }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            wp_cache_delete(self::QUEUE,'options'); wp_cache_delete('alloptions','options'); return true;
        } catch (Throwable $error) { return false; }
    }
}
