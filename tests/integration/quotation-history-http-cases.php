<?php
/** QUO-010 actual historical reads, denial, stale forms and recovery. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }

function idlm_history_http_fixture(): object {
    global $wpdb,$users;
    $now=current_time('mysql');
    idlm_http_check(1===$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'Synthetic commercial history','assigned_rep_id'=>$users['owner'],'created_at'=>$now,'updated_at'=>$now)),'Fixture lead failed');
    $lead=(int)$wpdb->insert_id;
    idlm_http_check(idlm_http_save(idlm_http_payload($lead))['status']===302,'Initial draft save failed');
    $quote=IDLM_Quotation_Repository::get_quote_for_lead($lead);
    idlm_http_check((bool)$quote,'Missing initial quote');return $quote;
}
function idlm_history_http_denials(object $quote): void {
    $payload=idlm_http_payload((int)$quote->lead_id,(int)$quote->id,(int)$quote->current_revision_id);
    foreach (array('owner','admin') as $actor) {
        $before=idlm_http_snapshot();$r=idlm_http_save($payload,$actor);
        idlm_http_check($r['status']===302 && strpos(urldecode($r['headers']),'Only the current draft')!==false,'Historical save allowed: '.$r['headers']);
        idlm_http_check($before===idlm_http_snapshot(),'Historical save changed durable state');
        idlm_http_denied(static function() use ($payload,$actor){return idlm_http_autosave($payload,$actor);},array(409));
        idlm_http_denied(static function() use ($payload,$actor){return idlm_http_autosave($payload,$actor,true);},array(404));
        $r=idlm_http_request('/workspace',array('action'=>'view','quote_id'=>$quote->id),$actor,false);
        idlm_http_check($r['status']===200 && strpos($r['body'],'SYNTHETIC-ITEM-ORIGINAL')!==false && strpos($r['body'],'Edit Quotation')===false,'Historical view missing or exposes editor');
        $r=idlm_http_request('/workspace',array('action'=>'edit','quote_id'=>$quote->id),$actor,false);
        idlm_http_check($r['status']===302 && strpos($r['headers'],'read-only')!==false,'Historical editor opened');
        idlm_http_check($before===idlm_http_snapshot(),'Historical UI requests changed state');
    }
}
foreach (array('Shared','Accepted','Rejected','Cancelled','Canceled','Finalized','Legacy custom') as $state) {
    idlm_http_case('T10-T17-H-QUO010-preserve-read-and-deny-mutation-'.$state,static function() use ($state):void {
        global $wpdb,$sessions;
        $quote=idlm_history_http_fixture();
        $wpdb->update($wpdb->prefix.'idlm_quotes',array('status'=>$state,'accepted_revision_id'=>$quote->current_revision_id,'shared_at'=>'2025-01-01 01:02:03','accepted_at'=>'2025-01-02 02:03:04','rejected_at'=>'2025-01-03 03:04:05'),array('id'=>$quote->id));
        $wpdb->update($wpdb->prefix.'idlm_quote_revisions',array('status'=>$state,'finalized_at'=>'2025-01-04 04:05:06'),array('id'=>$quote->current_revision_id));
        $before=idlm_http_snapshot();update_option('idlm_quotation_schema_version','2.5.2',false);update_option('idlm_quotation_revision_lifecycle_migration_version','1.0.0',false);
        $r=idlm_http_request('/migration-notices',array(),'admin',false);wp_cache_flush();
        idlm_http_check($r['status']===200 && IDLM_Quotation_Schema::is_current(),'Historical boot failed');
        idlm_http_check($before===idlm_http_snapshot(),'Boot erased commercial fields');
        $quote=IDLM_Quotation_Repository::get_quote($quote->id);idlm_history_http_denials($quote);
        $r=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_download_quote_csv','quote_id'=>$quote->id,'revision_id'=>$quote->current_revision_id,'_wpnonce'=>$sessions['owner']['csv']),'owner',false);
        idlm_http_check($r['status']===200 && strpos($r['body'],'SYNTHETIC-ITEM-ORIGINAL')!==false,'Historical CSV unavailable');
        idlm_http_check($before===idlm_http_snapshot(),'Historical CSV changed state');
    });
}
idlm_http_case('T10-H-QUO010-erased-status-with-commercial-history-and-finalized-Draft-remains-read-only',static function(): void {
    global $wpdb;
    $quote=idlm_history_http_fixture();
    IDLM_Quotation_Repository::insert_history($quote->id,$quote->current_revision_id,'Shared','Accepted','Synthetic remaining legacy evidence');
    idlm_history_http_denials($quote);
    $wpdb->delete($wpdb->prefix.'idlm_quote_status_history',array('quote_id'=>$quote->id));
    $wpdb->update($wpdb->prefix.'idlm_quote_revisions',array('finalized_at'=>'2025-01-01 01:02:03'),array('id'=>$quote->current_revision_id));
    idlm_history_http_denials($quote);
});

idlm_http_case('T10-H-QUO010-ordinary-drafts-save-repeated-revisions-and-retain-old-content',static function(): void {
    $quote=idlm_history_http_fixture();$before=IDLM_Quotation_Repository::get_quote_bundle($quote->id);
    for($i=0;$i<2;$i++) {
        $q=IDLM_Quotation_Repository::get_quote($quote->id);$p=idlm_http_payload((int)$q->lead_id,(int)$q->id,(int)$q->current_revision_id);
        idlm_http_check(idlm_http_autosave($p)['status']===200,'Ordinary draft autosave denied');
        idlm_http_check(idlm_http_save($p)['status']===302,'Ordinary draft revision denied');
    }
    $after=IDLM_Quotation_Repository::get_quote_bundle($quote->id,(int)$before->revision->id);
    idlm_http_check($before->items==$after->items && $before->revision->grand_total===$after->revision->grand_total,'Ordinary edits changed prior snapshot');
    idlm_http_check($after->revision->status==='Superseded' && count($after->revisions)===3,'Ordinary revision lifecycle changed');
});

idlm_http_case('T10-C-QUO010-stale-save-autosave-and-discard-observe-finalization-before-lock',static function(): void {
    global $wpdb,$config,$sessions;
    foreach(array('save','autosave','discard') as $operation) {
        $quote=idlm_history_http_fixture();$barrier=$config['content_dir'].'/barrier';file_put_contents($barrier,'history');
        $p=idlm_http_payload((int)$quote->lead_id,(int)$quote->id,(int)$quote->current_revision_id);
        $p[$operation==='save'?'_wpnonce':'nonce']=$sessions['owner'][$operation==='save'?'save':'autosave'];
        if($operation!=='save') {$p['action']=$operation==='autosave'?'idlm_autosave_quote':'idlm_discard_quote_autosave';}
        $url=$config['http_url'].'/wp-admin/'.($operation==='save'?'admin-post.php':'admin-ajax.php');
        $args=array('curl','--silent','--show-error','--max-time','15','--cookie',$config['content_dir'].'/owner.cookies','--data',http_build_query($p),'--output',$barrier.'.body','--write-out','%{http_code}',$url);
        $process=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
        try {
            $deadline=microtime(true)+10;while(!is_file($barrier.'.ready') && microtime(true)<$deadline){usleep(10000);}
            idlm_http_check(is_file($barrier.'.ready'),'Worker did not reach locking check');
            $wpdb->update($wpdb->prefix.'idlm_quotes',array('status'=>'Accepted','accepted_revision_id'=>$quote->current_revision_id,'accepted_at'=>'2025-01-01 01:02:03'),array('id'=>$quote->id));
            $wpdb->update($wpdb->prefix.'idlm_quote_revisions',array('status'=>'Accepted','finalized_at'=>'2025-01-01 01:02:03'),array('id'=>$quote->current_revision_id));
            $before=idlm_http_snapshot();file_put_contents($barrier.'.release','go');
            $status=(int)stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);
            idlm_http_check($status===($operation==='save'?302:($operation==='autosave'?409:404)) && $before===idlm_http_snapshot(),'Stale request changed finalized data: '.$status.' '.$error);
        } finally {
            file_put_contents($barrier.'.release','go');
            if(is_resource($process)){proc_terminate($process);fclose($pipes[1]);fclose($pipes[2]);proc_close($process);}
            foreach(glob($barrier.'*') as $file){unlink($file);}
        }
    }
});

idlm_http_case('T16-H-QUO010-actual-2.5.2-backup-roundtrip-preserves-commercial-history',static function(): void {
    $payload=json_decode(idlm_backup_export()['body'],true);idlm_http_check(is_array($payload),'History backup failed');$payload['schema']['quotation']='2.5.2';
    foreach(array(1,2) as $repeat) {
        $r=idlm_backup_upload($payload);
        idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_backup_notice=restored')!==false,'History restore failed');
        idlm_http_check($payload['tables']===json_decode(idlm_backup_export()['body'],true)['tables'],'History restore changed original rows');
    }
});
