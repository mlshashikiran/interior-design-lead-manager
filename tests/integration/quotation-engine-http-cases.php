<?php
/** QUO-007 real requests, environment denial and rollback. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }

function idlm_engine_http_convert(string $table,string $engine): void {
    global $wpdb;$catalogue=$table===$wpdb->prefix.'idlm_quote_catalogue_items';
    if($catalogue && $engine==='MyISAM'){idlm_http_check(false!==$wpdb->query("ALTER TABLE {$table} DROP INDEX status_name, ADD INDEX status_name (status, item_name(100))"),'Catalogue fixture index adjustment failed');}
    idlm_http_check(false!==$wpdb->query("ALTER TABLE {$table} ENGINE={$engine}"),'Fixture engine conversion failed');
    if($catalogue && $engine==='InnoDB'){idlm_http_check(false!==$wpdb->query("ALTER TABLE {$table} DROP INDEX status_name, ADD INDEX status_name (status, item_name)"),'Catalogue fixture index restoration failed');}
}
function idlm_engine_http_save_denied(array $payload,string $actor='owner'): void {
    $before=idlm_http_snapshot();$r=idlm_http_save($payload,$actor);
    idlm_http_check($r['status']===302 && strpos(urldecode($r['headers']),'verified InnoDB tables')!==false,'Missing actionable engine refusal: '.$r['headers']);
    idlm_http_check($before===idlm_http_snapshot(),'Rejected save changed rows, numbering, working copies or jobs');
}
function idlm_engine_http_fault(string $needle,callable $callback): void {
    global $config;$file=$config['content_dir'].'/fail-quotation-write';file_put_contents($file,$needle);
    try { $callback(); } finally {
        unlink($file);
        $log=$config['content_dir'].'/expected-quotation-faults.log';
        if(is_file($log)) {
            $messages=file_get_contents($log);
            foreach(explode("\n",trim($messages)) as $message) {
                idlm_http_check((bool)preg_match('/^\[[^\]]+\] \[IDLM Quotations\] (Revision item insert failed|Quotation history insert failed|Quotation transaction commit failed): Table .*idlm_absent_table.*doesn.t exist$/',$message),'Unexpected fault-request diagnostic: '.$message);
            }
            file_put_contents(__DIR__.'/../../docs/remediation-2026-10-07/quotation-engine-slice/expected-http-fault-diagnostics.log',$messages,FILE_APPEND);
            unlink($log);
        }
    }
}
$engine_tables=array('quotes','quote_revisions','quote_revision_items','quote_revision_sections','quote_payment_milestones','quote_status_history','number_sequences','quote_catalogue_items','quote_item_categories','quote_item_units','leads','lead_activity_log','options');
foreach($engine_tables as $suffix) {
    idlm_http_case('T10-T20-H-QUO007-engine-drift-denies-create-revision-'.$suffix,static function() use($suffix): void {
        global $wpdb;
        $quote=idlm_history_http_fixture();$p=idlm_http_payload((int)$quote->lead_id,(int)$quote->id,(int)$quote->current_revision_id);
        idlm_http_check(idlm_http_autosave($p)['status']===200,'Working copy fixture failed');
        global $users;
        $now=current_time('mysql');
        idlm_http_check(1===$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'Synthetic engine create','assigned_rep_id'=>$users['owner'],'created_at'=>$now,'updated_at'=>$now)),'New lead fixture failed');
        $new=(object)array('lead_id'=>$wpdb->insert_id);
        $table=$suffix==='options'?$wpdb->options:$wpdb->prefix.'idlm_'.$suffix;
        idlm_engine_http_convert($table,'MyISAM');
        try {
            idlm_http_check(IDLM_Quotation_Schema::is_current(),'Schema receipts changed during drift');
            foreach(array('owner','admin') as $actor) {
                idlm_engine_http_save_denied($p,$actor);idlm_engine_http_save_denied(idlm_http_payload((int)$new->lead_id),$actor);
                if(in_array($suffix,array('quotes','quote_revisions','quote_status_history','leads','options'),true)) {
                    foreach(array(false,true) as $discard) {
                        $before=idlm_http_snapshot();$r=idlm_http_autosave($p,$actor,$discard);
                        idlm_http_check($r['status']===503 && strpos($r['body'],'verified InnoDB tables')!==false,'Working-copy engine failure not reported');
                        idlm_http_check($before===idlm_http_snapshot(),'Engine denial changed working copies or rows');
                    }
                }
            }
            $before=idlm_http_snapshot();$r=idlm_http_request('/workspace',array('action'=>'view','quote_id'=>$quote->id),'owner',false);
            idlm_http_check($r['status']===200 && strpos($r['body'],'SYNTHETIC-ITEM-ORIGINAL')!==false,'Engine drift prevented authorized history read');
            idlm_http_check($before===idlm_http_snapshot(),'Historical read changed rows');
            idlm_http_check($wpdb->get_var("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}'")==='MyISAM','Request converted unsupported table');
        } finally { idlm_engine_http_convert($table,'InnoDB'); }
    });
}
foreach(array('idlm_quotation_engine_pin','idlm_quotation_write_engine') as $fault) {
    idlm_http_case('T10-H-QUO007-failed-'.$fault.'-denies-mutations-without-changes',static function() use($fault):void {
        $q=idlm_history_http_fixture();$p=idlm_http_payload((int)$q->lead_id,(int)$q->id,(int)$q->current_revision_id);
        idlm_engine_http_fault($fault,static function() use($p):void {
            idlm_engine_http_save_denied($p);
            foreach(array(false,true) as $discard){$before=idlm_http_snapshot();$r=idlm_http_autosave($p,'owner',$discard);idlm_http_check($r['status']===503 && $before===idlm_http_snapshot(),'Failed metadata check changed working copy');}
        });
    });
}
function idlm_engine_catalogue_upload(): array {
    global $config,$sessions;
    $file=$config['content_dir'].'/synthetic-engine-import.csv';file_put_contents($file,"item_code,item_name,category,unit,default_rate\n".wp_generate_uuid4().",Synthetic import,QUO007 Category,Nos,10\n");
    $body=$file.'.body';$headers=$file.'.headers';
    $args=array('curl','--silent','--show-error','--max-time','15','--cookie',$config['content_dir'].'/admin.cookies','--dump-header',$headers,'--output',$body,'--write-out','%{http_code}','-F','action=idlm_import_catalogue_csv','-F','_wpnonce='.$sessions['admin']['catalogue_import'],'-F','catalogue_csv=@'.$file.';type=text/csv',$config['http_url'].'/wp-admin/admin-post.php');
    $process=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
    $status=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    $result=array('status'=>(int)$status,'headers'=>(string)file_get_contents($headers));foreach(array($file,$body,$headers) as $path){unlink($path);}
    idlm_http_check($exit===0,'Catalogue transport failed: '.$error);return $result;
}
foreach(array('quote_catalogue_items','quote_item_categories','quote_item_units') as $suffix) {
    idlm_http_case('T11-H-QUO007-catalogue-import-denies-engine-drift-'.$suffix,static function() use($suffix):void {
        global $wpdb;$table=$wpdb->prefix.'idlm_'.$suffix;idlm_engine_http_convert($table,'MyISAM');
        try {$before=idlm_http_snapshot();$r=idlm_engine_catalogue_upload();idlm_http_check($r['status']===302 && strpos(urldecode($r['headers']),'verified InnoDB tables')!==false,'Catalogue engine error missing');idlm_http_check($before===idlm_http_snapshot(),'Rejected import left partial terms/items');}
        finally {idlm_engine_http_convert($table,'InnoDB');}
    });
}
foreach(array('quote_revision_items','quote_status_history','lead_projection','commit') as $fault) {
    idlm_http_case('T10-H-QUO007-verified-InnoDB-failed-'.$fault.'-rolls-back-and-retry-succeeds',static function() use($fault):void {
        global $wpdb;
        $q=idlm_history_http_fixture();$p=idlm_http_payload((int)$q->lead_id,(int)$q->id,(int)$q->current_revision_id);
        $needles=array('quote_revision_items'=>'INSERT INTO `'.$wpdb->prefix.'idlm_quote_revision_items`','quote_status_history'=>'INSERT INTO `'.$wpdb->prefix.'idlm_quote_status_history`','lead_projection'=>'UPDATE `'.$wpdb->prefix.'idlm_leads`','commit'=>'COMMIT');
        $before=idlm_http_snapshot();idlm_engine_http_fault($needles[$fault],static function() use($p,$before,$fault):void {$r=idlm_http_save($p);if($fault==='commit'){idlm_http_check(strpos(urldecode($r['headers']),'Unable to commit the quotation changes')!==false,'Commit fault did not reach COMMIT');}idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_error=')!==false,'Required fault acknowledged');idlm_http_check($before===idlm_http_snapshot(),'Failed verified transaction left partial aggregate');});
        $r=idlm_http_save($p);idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_notice=')!==false,'Repair retry failed');
        $after=IDLM_Quotation_Repository::get_quote($q->id);idlm_http_check((int)$after->current_revision_id!==(int)$q->current_revision_id,'Retry did not save one new revision');
        $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_quote_revisions WHERE quote_id=%d",$q->id));
        idlm_http_check($count===2,'Retry produced duplicate or missing revision');
    });
}
idlm_http_case('T10-T11-H-QUO007-repaired-engines-restore-create-autosave-discard-import-and-numbering',static function():void {
    $q=idlm_history_http_fixture();$p=idlm_http_payload((int)$q->lead_id,(int)$q->id,(int)$q->current_revision_id);
    idlm_http_check(idlm_http_autosave($p)['status']===200,'Repaired autosave failed');
    idlm_http_check(idlm_http_autosave($p,'owner',true)['status']===200,'Repaired discard failed');
    $r=idlm_engine_catalogue_upload();idlm_http_check($r['status']===302 && strpos(urldecode($r['headers']),'1 items imported')!==false,'Repaired import failed: '.$r['headers']);
});
