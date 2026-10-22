<?php
/** Actual disposable backup/restore requests. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }
function idlm_backup_upload(array $payload,string $actor='admin',?string $nonce=null,string $confirmation='RESTORE'):array{
    global $config,$sessions;
    $path=tempnam($config['content_dir'],'synthetic-backup-').'.json';file_put_contents($path,wp_json_encode($payload,JSON_UNESCAPED_SLASHES));$body=$path.'.body';$headers=$path.'.headers';
    $args=array('curl','--silent','--show-error','--max-time','30','--cookie',$config['content_dir'].'/'.$actor.'.cookies','--dump-header',$headers,'--output',$body,'--write-out','%{http_code}','-F','action=idlm_restore_backup','-F','_wpnonce='.($nonce??$sessions[$actor]['restore_backup']),'-F','idlm_restore_confirmation='.$confirmation,'-F','idlm_backup_file=@'.$path.';type=application/json',$config['http_url'].'/wp-admin/admin-post.php');
    $proc=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$status=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);$r=array('status'=>(int)$status,'body'=>(string)file_get_contents($body),'headers'=>(string)file_get_contents($headers));foreach(array($path,$body,$headers,substr($path,0,-5)) as $file){if(is_file($file)){unlink($file);}}idlm_http_check($exit===0,'Backup upload failed: '.$error);return $r;
}
function idlm_backup_export(string $actor='admin'):array{
    global $sessions;return idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_export_backup','_wpnonce'=>$sessions[$actor]['backup']),$actor,false);
}
$backup_response=idlm_backup_export();$backup_payload=json_decode($backup_response['body'],true);idlm_http_check($backup_response['status']===200 && is_array($backup_payload),'Baseline backup export failed');
idlm_http_case('T16-H-partial-backup-rejected-before-persistent-writes',static function() use($backup_payload):void{
    $partial=$backup_payload;$partial['tables']=array();$partial['options']=array();$before=idlm_http_snapshot();$r=idlm_backup_upload($partial);
    try{idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_backup_notice=restored')===false && $before===idlm_http_snapshot(),'Incomplete payload committed data/settings/job changes or success');}
    finally{if(!class_exists('IDLM_Backup_Validator')){$restore=new ReflectionMethod('IDLM_Backup','restore_payload');if (PHP_VERSION_ID < 80100) { $restore->setAccessible(true); }idlm_http_check(true===$restore->invoke(null,$backup_payload),'Unable to restore owned pre-fix fixture after characterization');}}
});
function idlm_backup_http_digest():string{global $wpdb;$settings=$wpdb->get_results("SELECT option_name,option_value,autoload FROM {$wpdb->options} WHERE option_name LIKE 'idlm_%' ORDER BY option_name",ARRAY_A);return hash('sha256',idlm_http_snapshot().wp_json_encode($settings));}
function idlm_backup_http_reject(array $payload,string $actor='admin',?string $nonce=null,string $confirm='RESTORE'):void{$before=idlm_backup_http_digest();$r=idlm_backup_upload($payload,$actor,$nonce,$confirm);idlm_http_check(($r['status']===302 && strpos($r['headers'],'idlm_backup_notice=restored')===false)||$r['status']===403,'Invalid recovery accepted');idlm_http_check($before===idlm_backup_http_digest(),'Rejected recovery changed CRM, settings, jobs or outbound attempts');foreach(array('PII-SYNTHETIC','PRIVATE-SYNTHETIC-NOTES','SYNTHETIC-ITEM-ORIGINAL') as $pii){idlm_http_check(strpos($r['body'].$r['headers'],$pii)===false,'Rejected recovery disclosed PII');}}
foreach(array('owner','other','readall','manager') as $actor){
    idlm_http_case('T16-H-deny-backup-and-restore-'.$actor,static function()use($backup_payload,$actor):void{$before=idlm_backup_http_digest();$r=idlm_backup_export($actor);idlm_http_check($r['status']===403 && strpos($r['body'],'PII-SYNTHETIC')===false,'Non-admin exported backup');idlm_backup_http_reject($backup_payload,$actor);idlm_http_check($before===idlm_backup_http_digest(),'Unauthorized backup mutated state');});
}
idlm_http_case('T16-H-wrong-nonce-and-confirmation',static function()use($backup_payload):void{idlm_backup_http_reject($backup_payload,'admin','invalid-nonce');idlm_backup_http_reject($backup_payload,'admin',null,'NO');});
$backup_bad=[];$x=$backup_payload;unset($x['tables']['idlm_tasks']);$backup_bad['missing-table']=$x;$x=$backup_payload;$x['tables']['foreign_table']=array();$backup_bad['foreign-table']=$x;$x=$backup_payload;$x['site_url']='http://foreign.example.test';$backup_bad['foreign-site']=$x;
$x=$backup_payload;$x['tables']['idlm_leads'][0]['unknown_column']='bad';$backup_bad['unknown-column']=$x;$x=$backup_payload;$x['tables']['idlm_leads'][0]['id']='1e2';$backup_bad['coercive-integer']=$x;$x=$backup_payload;$x['tables']['idlm_leads'][0]['created_at']='2026-02-30 00:00:00';$backup_bad['invalid-date']=$x;
$x=$backup_payload;$x['tables']['idlm_quotes'][0]['lead_id']='99999999';$backup_bad['orphan-quote']=$x;$x=$backup_payload;$x['tables']['idlm_quotes'][0]['current_revision_id']=$x['tables']['idlm_quote_revisions'][1]['id'];$backup_bad['foreign-revision']=$x;
$x=$backup_payload;$x['user_identities'][array_key_first($x['user_identities'])]='foreign_login';$backup_bad['foreign-user']=$x;$x=$backup_payload;$x['options'][]=array('option_name'=>'idlm_recovery_generation','option_value'=>'0','autoload'=>'off');$backup_bad['protected-generation']=$x;
$x=$backup_payload;$x['options'][]=array('option_name'=>'idlm_synthetic_object','option_value'=>'O:8:"stdClass":0:{}','autoload'=>'off');$backup_bad['serialized-object']=$x;
$x=$backup_payload;$duplicate=$x['tables']['idlm_quotes'][0];$duplicate['id']='99999999';$duplicate['quote_number']=strtolower($duplicate['quote_number']);$duplicate['current_revision_id']='0';$duplicate['accepted_revision_id']='0';$duplicate['save_request_key']=null;$x['tables']['idlm_quotes'][]=$duplicate;$backup_bad['native-collation-collision']=$x;
foreach($backup_bad as $id=>$bad){idlm_http_case('T16-H-reject-'.$id,static function()use($bad):void{idlm_backup_http_reject($bad);});}
idlm_http_case('T16-H-admin-capability-revocation',static function()use($backup_payload,$admin):void{$u=new WP_User($admin);$u->add_cap('manage_options',false);try{idlm_backup_http_reject($backup_payload);}finally{$u->remove_cap('manage_options');}});
idlm_http_case('T16-C-active-worker-blocks-actual-restore',static function()use($backup_payload):void{$socket=substr(DB_HOST,strpos(DB_HOST,':')+1);$worker=new mysqli('localhost',DB_USER,DB_PASSWORD,DB_NAME,0,$socket);$name=IDLM_Recovery_Guard::name('worker_1');$worker->query("SELECT GET_LOCK('$name',0)");try{idlm_backup_http_reject($backup_payload);}finally{$worker->close();}});
idlm_http_case('T16-H-authorized-complete-roundtrip-and-repeat',static function()use($backup_payload):void{
    global $wpdb;
    $epoch=IDLM_Recovery_Guard::generation();$r=idlm_backup_upload($backup_payload);idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_backup_notice=restored')!==false,'Complete authorized restore failed: '.$r['headers']);idlm_http_check(IDLM_Recovery_Guard::generation()===$epoch+1,'Restore did not commit recovery generation');$after=json_decode(idlm_backup_export()['body'],true);idlm_http_check($backup_payload['tables']===$after['tables'],'Roundtrip changed backup rows');
    $r=idlm_backup_upload($backup_payload);idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_backup_notice=restored')!==false,'Repeated valid restore failed');
});
idlm_http_case('T16-H-stale-queued-payload-no-writes-or-outbound',static function():void{
    $before=idlm_backup_http_digest();do_action('idlm_async_capi_upload_event',1,'New',0);IDLM_Interakt::process_async_track_event(array('traits'=>array('email'=>'stale@example.test')),'synthetic-key',1,0);idlm_http_check($before===idlm_backup_http_digest(),'Stale callbacks wrote logs, queued work or attempted HTTP');
});
