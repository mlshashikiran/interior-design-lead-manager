<?php
/**
 * Isolated worker for integration paths that redirect/exit or require concurrency.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $config = idlm_integration_config(true);
    idlm_integration_boot_wordpress($config, false);
    idlm_integration_load_plugin($config);

    $mode = $argv[1] ?? '';
    if (in_array($mode,array('audience-crash','audience-race'),true)) {
        if ($mode==='audience-race') {
            $barrier=WP_CONTENT_DIR.'/audience-barrier';file_put_contents($barrier,getmypid()."\n",FILE_APPEND|LOCK_EX);$deadline=microtime(true)+10;
            while(count(file($barrier,FILE_IGNORE_NEW_LINES))<2){if(microtime(true)>$deadline){throw new RuntimeException('Audience barrier timed out');}usleep(10000);}
        }
        add_filter('pre_http_request',static function($pre,$args,$url)use($mode){
            if(strpos($url,'/users')===false){return $pre;}
            file_put_contents(WP_CONTENT_DIR.'/audience-provider.log',"synthetic accepted\n",FILE_APPEND|LOCK_EX);
            if($mode==='audience-crash'){exit(77);}usleep(300000);return array('response'=>array('code'=>200),'body'=>'{}','headers'=>array());
        },PHP_INT_MAX,3);
        $r=IDLM_Audiences::run_batch_sync(true);echo is_wp_error($r)?'busy':wp_json_encode($r);exit(0);
    }

    if (in_array($mode,array('wa-beforecommit','wa-aftercommit','wa-race'),true)) {
        if ($mode==='wa-race') { $barrier=WP_CONTENT_DIR.'/wa-barrier';file_put_contents($barrier,getmypid()."\n",FILE_APPEND|LOCK_EX);$deadline=microtime(true)+10;while(count(file($barrier,FILE_IGNORE_NEW_LINES))<2){if(microtime(true)>$deadline){throw new RuntimeException('WhatsApp barrier timeout');}usleep(10000);} }
        if ($mode==='wa-beforecommit') { add_filter('query',static function($sql){if(strpos($sql,'INSERT INTO `wp_options`')===0 && strpos($sql,'idlm_whatsapp_receipt_')!==false){exit(77);}return $sql;}); }
        $message=IDLM_WhatsApp_Inbox::message('meta',$argv[2],$argv[3],'Synthetic '.$argv[2]);
        for($attempt=0;$attempt<30;$attempt++){ $result=IDLM_WhatsApp_Inbox::record($message);if(!is_wp_error($result)){break;}usleep(50000); }
        if(is_wp_error($result)){throw new RuntimeException('WhatsApp handling failed');}
        if($mode==='wa-aftercommit'){exit(77);}echo wp_json_encode($result);exit(0);
    }

    if (in_array($mode,array('capi-retry-crash','capi-normal-crash','capi-retry-race','capi-normal-race','capi-prune-race'),true)) {
        if (!in_array($mode,array('capi-retry-crash','capi-normal-crash'),true)) {
            $barrier=WP_CONTENT_DIR.'/capi-barrier';file_put_contents($barrier,getmypid()."\n",FILE_APPEND|LOCK_EX);$deadline=microtime(true)+10;
            while(count(file($barrier,FILE_IGNORE_NEW_LINES))<2){if(microtime(true)>$deadline){throw new RuntimeException('CAPI barrier timed out');}usleep(10000);}
        }
        add_filter('pre_http_request',static function($pre,$args,$url)use($mode){
            if(!preg_match('~graph.facebook.com|google-analytics.com|api.linkedin.com~',$url)){return $pre;}
            file_put_contents(WP_CONTENT_DIR.'/capi-provider.log',"synthetic accepted\n",FILE_APPEND|LOCK_EX);
            if(in_array($mode,array('capi-retry-crash','capi-normal-crash'),true)){exit(77);}usleep(300000);return array('response'=>array('code'=>200),'body'=>'{"events_received":1}','headers'=>array());
        },PHP_INT_MAX,3);
        if($mode==='capi-prune-race'){for($i=0;$i<30;$i++){$r=IDLM_Data_Retention::prune();if(is_array($r)&&$r['processed']>0){exit(0);}usleep(50000);}throw new RuntimeException('Retention race did not converge');}
        if(in_array($mode,array('capi-normal-race','capi-normal-crash'),true)){IDLM_CAPI::process_capi_event((int)$argv[2],'Qualified');}else{IDLM_CAPI::retry_capi_log((int)$argv[2]);}exit(0);
    }

    if ('interakt-dispatch-crash' === $mode || 'interakt-dispatch-race' === $mode) {
        if ('interakt-dispatch-race' === $mode) {
            $barrier=WP_CONTENT_DIR.'/interakt-barrier';file_put_contents($barrier,getmypid()."\n",FILE_APPEND|LOCK_EX);$deadline=microtime(true)+10;
            while(count(file($barrier,FILE_IGNORE_NEW_LINES))<2){if(microtime(true)>$deadline){throw new RuntimeException('Interakt barrier timed out');}usleep(10000);}
        }
        add_filter('pre_http_request',static function($pre,$args,$url) use($mode) {
            if(strpos($url,'api.interakt.ai/')===false){return $pre;}
            file_put_contents(WP_CONTENT_DIR.'/interakt-provider.log',"synthetic accepted\n",FILE_APPEND|LOCK_EX);
            if($mode==='interakt-dispatch-crash'){exit(77);}
            usleep(300000);return array('response'=>array('code'=>200),'body'=>'{}','headers'=>array());
        },PHP_INT_MAX,3);
        IDLM_Interakt::process_async_track_event($argv[2]);exit(0);
    }

    if ('quotation-schema-interrupt' === $mode) {
        $publishing=false;
        add_filter('query', static function($sql) use (&$publishing) {
            if(strpos($sql,'UPDATE')===0 && strpos($sql,'idlm_quotation_schema_version')!==false){$publishing=true;}
            if($publishing && $sql==='COMMIT'){exit(77);}
            return $sql;
        });
        IDLM_Quotations::install(); exit(2);
    }
    if ('quotation-migration-interrupt' === $mode) {
        add_filter('query', static function($sql) { if ($sql === 'COMMIT') { exit(77); } return $sql; });
        IDLM_Quotation_Schema::backfill_revision_snapshots_and_sections(); exit(2);
    }
    if ('quotation-migration-race' === $mode) {
        $barrier=WP_CONTENT_DIR.'/lead-barrier';file_put_contents($barrier,getmypid()."\n",FILE_APPEND|LOCK_EX);$deadline=microtime(true)+10;
        while(count(file($barrier,FILE_IGNORE_NEW_LINES))<2){if(microtime(true)>$deadline){throw new RuntimeException('Quotation migration barrier timed out');}usleep(10000);}
        for($attempt=0;$attempt<100;$attempt++) {
            $result=IDLM_Quotations::install();
            if(true===$result){echo 'complete';exit(0);}
            if(!is_wp_error($result) || $result->get_error_code()!=='idlm_schema_busy'){throw new RuntimeException('Quotation migration failed.');}
            usleep(20000);
        }
        throw new RuntimeException('Quotation migration lock retry timed out');
    }
    if ('retention-interrupt' === $mode) {
        add_filter('query', static function($sql) { if ($sql === 'COMMIT') { exit(77); } return $sql; });
        IDLM_Data_Retention::prune(); exit(2);
    }
    if ('review-race' === $mode) {
        wp_set_current_user(absint($argv[3]));$barrier=WP_CONTENT_DIR.'/lead-barrier';file_put_contents($barrier,getmypid()."\n",FILE_APPEND|LOCK_EX);$deadline=microtime(true)+10;
        while(count(file($barrier,FILE_IGNORE_NEW_LINES))<2){if(microtime(true)>$deadline){throw new RuntimeException('Review barrier timed out');}usleep(10000);}
        $r=IDLM_Lead_Lifecycle_Service::resolve_inbound_review(absint($argv[2]),'separate',0,'Synthetic concurrent decision');echo is_wp_error($r)?'refused':'resolved';exit(0);
    }
    if ('capacity-race' === $mode || 'capacity-interrupt' === $mode) {
        global $wpdb;
        $operation=$argv[2];$lead=absint($argv[3]);$target=absint($argv[4]);
        if ($mode==='capacity-race') {
            if (!IDLM_Integrations::rep_has_capacity($target)) { throw new RuntimeException('Concurrent fixture did not observe the final free slot.'); }
            $barrier=WP_CONTENT_DIR.'/lead-barrier';file_put_contents($barrier,getmypid()."\n",FILE_APPEND|LOCK_EX);$deadline=microtime(true)+10;
            while(count(file($barrier,FILE_IGNORE_NEW_LINES))<2){if(microtime(true)>$deadline){throw new RuntimeException('Capacity barrier timed out');}usleep(10000);}
        } else { add_filter('query',static function($sql){if($sql==='COMMIT'){exit(77);}return $sql;}); }
        $ok=false;
        for($attempt=0;$attempt<30 && !$ok;$attempt++) {
            if($operation==='manual'){$ok=IDLM_DB::update_lead($lead,array('assigned_rep_id'=>$target));}
            elseif($operation==='reopen'){$ok=IDLM_DB::update_lead($lead,array('stage'=>'Contacted'));}
            elseif($operation==='insert'){$ok=IDLM_DB::insert_lead(array('first_name'=>'Synthetic capacity intake','email'=>'capacity-'.getmypid().'@example.test','assigned_rep_id'=>$target));}
            else {$m=new ReflectionMethod('IDLM_Integrations','assign_lead_atomically');if (PHP_VERSION_ID < 80100) { $m->setAccessible(true); }$ok=$m->invokeArgs(null,array($lead,0,$target,'Synthetic concurrent capacity admission',$operation==='qualified',$operation==='qualified'?'qualified_general':($operation==='intake'?'intake':'initial')));}
            if(!$ok){usleep(30000);}
        }
        echo $ok?'admitted':'refused';exit(0);
    }
    if ('lead-disconnect' === $mode) {
        global $wpdb;
        do_action('template_redirect'); // Make core return failure instead of its HTML dead_db exit.
        $killed = false;
        $kill = static function($sql) use (&$killed, $config) {
            if (!$killed && strpos($sql, 'INSERT INTO `wp_idlm_lead_state_events`') === 0) {
                $killed = true;
                $connection = (int) $GLOBALS['wpdb']->get_var('SELECT CONNECTION_ID()');
                $other = new wpdb($config['db_user'],$config['db_password'],$config['db_name'],$config['db_host']);
                $other->query('KILL CONNECTION ' . $connection); $other->close();
            }
            return $sql;
        };
        add_filter('query', $kill);
        $result = IDLM_DB::update_lead(absint($argv[2]), array('stage'=>'Qualified'));
        remove_filter('query', $kill);
        if (!$killed || false !== $result) { throw new RuntimeException('Lost connection was acknowledged.'); }
        echo 'refused'; exit(0);
    }
    if ('lead-interrupt' === $mode) {
        $lead = absint($argv[2]); $when = $argv[3];
        add_filter('query', static function($sql) use ($when) {
            if (($when === 'before' && $sql === 'COMMIT') || ($when === 'after' && strpos($sql, 'SELECT * FROM wp_idlm_lead_outbox WHERE id') === 0)) { exit(77); }
            return $sql;
        });
        IDLM_DB::update_lead($lead, array('stage'=>'Qualified')); exit(1);
    }
    if (in_array($mode, array('lead-intake-race','lead-update-race','lead-outbox-race','task-plan-race'), true)) {
        $barrier = WP_CONTENT_DIR . '/lead-barrier';
        file_put_contents($barrier, getmypid() . "\n", FILE_APPEND | LOCK_EX);
        $deadline = microtime(true) + 10;
        while (count(file($barrier, FILE_IGNORE_NEW_LINES)) < 2) {
            if (microtime(true) > $deadline) { throw new RuntimeException('Lead barrier timed out.'); }
            usleep(10000);
        }
        if ($mode === 'task-plan-race') {
            // Recovery admission can return busy; retry the failed command without assuming success.
            $ok = false;
            for ($attempt = 0; $attempt < 20 && !$ok; $attempt++) {
                $ok = IDLM_DB::create_presales_followup_tasks(absint($argv[2]),absint($argv[3]));
                if (!$ok) { usleep(50000); }
            }
            if (!$ok) { throw new RuntimeException('Task-plan repair failed after bounded retry.'); }
            echo 'complete';
        } elseif ($mode === 'lead-intake-race') {
            $result = IDLM_DB::upsert_inbound_lead(array('email'=>$argv[2], 'fb_lead_id'=>$argv[3]), 'meta');
            if (!$result) { throw new RuntimeException(IDLM_DB::get_last_validation_error()); }
            echo wp_json_encode($result);
        } elseif ($mode === 'lead-update-race') {
            $ok = false;
            for ($attempt = 0; $attempt < 20 && !$ok; $attempt++) {
                $ok = IDLM_DB::update_lead(absint($argv[2]), array('stage'=>$argv[3], 'lost_reason'=>'Synthetic close'));
                if (!$ok) { usleep(50000); }
            }
            if (!$ok) { throw new RuntimeException(IDLM_DB::get_last_validation_error()); }
            echo 'complete';
        } else {
            add_action('idlm_lead_stage_converted', static function($lead) {
                usleep(300000);
                if (!IDLM_DB::log_activity($lead, 'outbox_probe', 'Synthetic worker handoff')) { throw new RuntimeException('Probe insert failed.'); }
            }, 1, 3);
            echo IDLM_Lead_Outbox::dispatch(absint($argv[2])) ? 'complete' : 'busy';
        }
        exit(0);
    }
    if ('schema-interrupt' === $mode) {
        add_filter('query', static function ($sql) {
            if (strpos($sql, 'SHOW COLUMNS FROM `wp_idlm_leads`') === 0) { exit(77); }
            return $sql;
        });
        IDLM_DB::create_tables();
        exit(1);
    }
    if ('schema-race' === $mode) {
        $barrier = WP_CONTENT_DIR . '/schema-barrier';
        file_put_contents($barrier, getmypid() . "\n", FILE_APPEND | LOCK_EX);
        $deadline = microtime(true) + 10;
        while (count(file($barrier, FILE_IGNORE_NEW_LINES)) < 2) {
            if (microtime(true) > $deadline) { throw new RuntimeException('Schema barrier timed out.'); }
            usleep(10000);
        }
        add_filter('dbdelta_queries', static function ($queries) { usleep(300000); return $queries; });
        $result = IDLM_DB::init();
        if (is_wp_error($result) && $result->get_error_code() !== 'idlm_schema_busy') { throw new RuntimeException($result->get_error_message()); }
        echo is_wp_error($result) ? 'busy' : 'complete';
        exit(0);
    }
    if ('quote-number' === $mode) {
        $method = new ReflectionMethod('IDLM_Quotations', 'next_quote_number');
        if (PHP_VERSION_ID < 80100) { $method->setAccessible(true); }
        $number = $method->invoke(null);
        if (is_wp_error($number)) {
            throw new RuntimeException($number->get_error_message());
        }
        echo wp_json_encode(array('quote_number' => $number));
        exit(0);
    }

    if ('quote-save' === $mode) {
        $lead_id = absint($argv[2] ?? 0);
        $user_id = absint($argv[3] ?? 0);
        if (!$lead_id || !$user_id) {
            throw new RuntimeException('quote-save requires a lead ID and user ID.');
        }
        wp_set_current_user($user_id);
        if (!current_user_can('idlm_create_quotes')) {
            throw new RuntimeException('Quotation worker user lacks idlm_create_quotes.');
        }
        add_filter('wp_die_handler', static function() {
            return static function($message): void {
                throw new RuntimeException('wp_die: ' . wp_strip_all_tags((string) $message));
            };
        });
        add_filter('wp_redirect', static function($location) {
            fwrite(STDERR, 'REDIRECT ' . (string) $location . "\n");
            return $location;
        });
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = array(
            '_wpnonce' => wp_create_nonce('idlm_save_quote'),
            'quote_id' => 0,
            'revision_id' => 0,
            'lead_id' => $lead_id,
            'title' => 'Integration Proposal',
            'save_request_key' => '12345678-1234-4123-8123-123456789abc',
            'currency_code' => 'INR',
            'wooden_discount_percent' => '0',
            'additional_discount_amount' => '0',
            'sections' => array(
                array('key' => 'general', 'section_name' => 'General'),
            ),
            'items' => array(
                array(
                    'section_key' => 'general',
                    'description' => 'Interior design services',
                    'quantity' => '2',
                    'unit_rate' => '1000',
                ),
            ),
        );
        $_REQUEST = $_POST;
        IDLM_Quotations::handle_save();
        throw new RuntimeException('Quotation save returned without redirecting.');
    }

    throw new RuntimeException('Unknown integration worker mode.');
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
