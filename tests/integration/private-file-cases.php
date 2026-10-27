<?php
/** CORE-002 actual multipart uploads/static/download lifecycle. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

function idlm_file_upload(int $lead, string $actor = 'owner', string $purpose = 'floor_plan', ?string $file = null, ?string $nonce = null): array {
    global $config, $sessions;
    $file = $file ?? $config['content_dir'] . '/synthetic-private.pdf';
    $body = tempnam($config['content_dir'],'file-response-'); $head = $body . '.headers';
    $args = array('curl','--silent','--show-error','--max-time','20','--cookie',$config['content_dir'].'/'.$actor.'.cookies','--dump-header',$head,'--output',$body,'--write-out','%{http_code}',
        '-F','action=idlm_upload_lead_file','-F','nonce='.($nonce ?? $sessions[$actor]['admin']),'-F','lead_id='.$lead,'-F','purpose='.$purpose,'-F','file=@'.$file.';type=application/pdf', $config['http_url'].'/wp-admin/admin-ajax.php');
    $proc=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
    $status=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($proc);
    $r=array('status'=>(int)$status,'body'=>(string)file_get_contents($body),'headers'=>(string)file_get_contents($head)); unlink($body); unlink($head); idlm_http_check($exit===0,'Multipart failed: '.$error); return $r;
}
function idlm_file_get(string $url, string $actor = 'owner'): array {
    global $config;
    idlm_http_check(strpos($url,$config['http_url'].'/')===0,'Fixture refuses non-local file URL');
    return idlm_http_request(substr($url,strlen($config['http_url'])),array(),$actor,false);
}
function idlm_file_upload_data(array $r): array {
    $data=json_decode($r['body'],true); idlm_http_check($r['status']===200 && !empty($data['success']) && !empty($data['data']['url']) && !empty($data['data']['attachment_id']),'Upload failed: '.$r['body']); return $data['data'];
}
function idlm_file_new_lead(string $actor = 'owner'): int {
    global $wpdb,$users;
    $now=current_time('mysql'); idlm_http_check(false!==$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'PRIVATE-FILE-PII-SYNTHETIC','email'=>'private-file@example.test','assigned_rep_id'=>$users[$actor],'created_at'=>$now,'updated_at'=>$now,'stage'=>'New')),'File lead insert failed'); return (int)$wpdb->insert_id;
}
$private_bytes="%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n% PRIVATE-FILE-PII-SYNTHETIC bytes\n%%EOF\n";
file_put_contents($config['content_dir'].'/synthetic-private.pdf',$private_bytes);
$private_lead=idlm_file_new_lead(); $private_upload=idlm_file_upload_data(idlm_file_upload($private_lead));
idlm_http_case('T13-H-private-upload-blocks-anonymous-copied-URL',static function() use($private_upload,$private_bytes): void {
    $r=idlm_file_get($private_upload['url'],'anonymous'); idlm_http_check(in_array($r['status'],array(403,404),true) && strpos($r['body'],$private_bytes)===false && strpos($r['body'],'PRIVATE-FILE-PII')===false,'Copied URL exposes raw file anonymously');
});
if (!class_exists('IDLM_Private_Files')) {
    IDLM_Lead_Deletion_Service::delete($private_lead);
    idlm_http_case('T13-H-deleted-lead-revokes-file-URL',static function() use($private_upload): void { $r=idlm_file_get($private_upload['url'],'anonymous'); idlm_http_check(in_array($r['status'],array(403,404),true),'Deleted lead still has public file'); });
    return;
}

function idlm_file_snapshot(): string {
    global $wpdb;
    return hash('sha256', idlm_http_snapshot() . wp_json_encode($wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_type = 'attachment' ORDER BY ID", ARRAY_A)) . wp_json_encode($wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE meta_key LIKE '_idlm_%' ORDER BY meta_id", ARRAY_A)));
}
function idlm_file_denied(callable $request, array $statuses = array(403,404)): void {
    $before=idlm_file_snapshot(); $r=$request();
    idlm_http_check(in_array($r['status'],$statuses,true),'Unexpected file denial status: '.$r['status'].' '.$r['body']);
    $json=json_decode($r['body'],true); if (is_array($json)) { idlm_http_check(empty($json['success']),'Denied file operation succeeded'); }
    idlm_http_check(strpos($r['body'].$r['headers'],'PRIVATE-FILE-PII')===false && strpos($r['body'],'private-file@example.test')===false && strpos($r['body'],'%PDF')===false,'File denial disclosed PII');
    idlm_http_check($before===idlm_file_snapshot(),'File denial changed business/file/attachment/meta rows, jobs or outbound attempts');
}
function idlm_file_bind(int $lead, string $url, string $actor='owner', string $field='floor_plan_file'): array {
    global $sessions;
    return idlm_http_request('/wp-admin/admin-ajax.php',array('action'=>'idlm_save_lead_details','nonce'=>$sessions[$actor]['admin'],'form_data'=>http_build_query(array('lead_id'=>$lead,$field=>$url))),$actor);
}
idlm_http_case('T13-H-owner-read-byte-hash-MIME-and-private-cache-headers',static function() use($private_upload,$private_bytes): void {
    $before=idlm_file_snapshot(); $r=idlm_file_get($private_upload['url']);
    idlm_http_check($r['status']===200 && $r['body']===$private_bytes && strpos($r['headers'],'application/pdf')!==false,'Owner file bytes/MIME differ');
    foreach(array('private, no-store','X-Content-Type-Options: nosniff','Content-Security-Policy: sandbox','Referrer-Policy: no-referrer') as $header){idlm_http_check(stripos($r['headers'],$header)!==false,'Missing private header '.$header);}
    idlm_http_check($before===idlm_file_snapshot(),'Download wrote file state/jobs/outbound');
});
foreach(array('other','anonymous') as $actor){idlm_http_case('T13-H-foreign-file-read-'.$actor,static function() use($private_upload,$actor):void{idlm_file_denied(static function() use($private_upload,$actor){return idlm_file_get($private_upload['url'],$actor);});});}
foreach(array('readall','manager','admin') as $actor){idlm_http_case('T13-H-authorized-file-read-'.$actor,static function() use($private_upload,$private_bytes,$actor):void{$r=idlm_file_get($private_upload['url'],$actor);idlm_http_check($r['status']===200 && $r['body']===$private_bytes,'Authorized scoped file read failed');});}
foreach(array('other','readall') as $actor){idlm_http_case('T13-H-foreign-file-upload-'.$actor,static function() use($private_lead,$actor):void{idlm_file_denied(static function() use($private_lead,$actor){return idlm_file_upload($private_lead,$actor);});});}
foreach(array('invalid-nonce'=>'invalid-synthetic','cross-user-nonce'=>$sessions['other']['admin']) as $kind=>$nonce){idlm_http_case('T13-H-upload-'.$kind,static function() use($private_lead,$nonce):void{idlm_file_denied(static function() use($private_lead,$nonce){return idlm_file_upload($private_lead,'owner','floor_plan',null,$nonce);});});}
idlm_http_case('T13-H-upload-invalid-purpose-extension-MIME-and-size',static function() use($private_lead,$config):void{
    idlm_file_denied(static function() use($private_lead){return idlm_file_upload($private_lead,'owner','unknown');},array(400));
    foreach(array('payload.php'=>"<?php echo 'not a document';",'payload.pdf'=>'Not PDF content','oversized.pdf'=>"%PDF-1.4\n".str_repeat('x',10485761)) as $name=>$bytes){$path=$config['content_dir'].'/'.$name;file_put_contents($path,$bytes);idlm_file_denied(static function() use($private_lead,$path){return idlm_file_upload($private_lead,'owner','floor_plan',$path);},array(400));unlink($path);}
});
idlm_http_case('T13-H-multi-chunk-upload-byte-preservation',static function() use($private_lead,$private_bytes,$config):void{
    $data=$private_bytes.str_repeat('SYNTHETIC-CHUNK-',10000);$path=$config['content_dir'].'/chunked.pdf';file_put_contents($path,$data);
    $uploaded=idlm_file_upload_data(idlm_file_upload($private_lead,'owner','floor_plan',$path));$r=idlm_file_get($uploaded['url']);idlm_http_check($r['body']===$data,'Multi-chunk bytes differ');unlink($path);
});
idlm_http_case('T13-H-bound-file-save-and-foreign-purpose-substitution',static function() use($private_lead,$private_upload):void{
    $r=idlm_file_bind($private_lead,$private_upload['url']);idlm_http_check(!empty(json_decode($r['body'],true)['success']),'Bound file save failed: '.$r['body']);
    idlm_file_denied(static function() use($private_lead,$private_upload){return idlm_file_bind($private_lead,$private_upload['url'],'owner','quote_file');},array(200));
    $foreign=idlm_file_new_lead();idlm_file_denied(static function() use($foreign,$private_upload){return idlm_file_bind($foreign,$private_upload['url']);},array(200));
});
idlm_http_case('T13-H-missing-corrupt-and-forged-file-identities',static function() use($private_upload):void{
    global $wpdb; $id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_private_files WHERE attachment_id = %d",$private_upload['attachment_id']));
    $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_private_file_chunks WHERE file_id = %d AND variant = 0 AND part = 1",$id),ARRAY_A);
    $wpdb->update($wpdb->prefix.'idlm_private_file_chunks',array('data'=>'corrupt'),array('file_id'=>$id,'variant'=>0,'part'=>1));
    idlm_file_denied(static function() use($private_upload){return idlm_file_get($private_upload['url']);});
    $wpdb->delete($wpdb->prefix.'idlm_private_file_chunks',array('file_id'=>$id,'variant'=>0,'part'=>1));idlm_file_denied(static function() use($private_upload){return idlm_file_get($private_upload['url']);});$wpdb->insert($wpdb->prefix.'idlm_private_file_chunks',$row);
    foreach(array('9999999','../1','1%20OR%201=1','attachment_id[]=1') as $value){$url=IDLM_Private_Files::url(9999999);$url=preg_replace('/attachment_id=\d+/',strpos($value,'attachment_id')===0?$value:'attachment_id='.$value,$url);idlm_file_denied(static function() use($url){return idlm_file_get($url);});}
});
idlm_http_case('T13-H-live-ownership-role-grant-and-suspension-revocation',static function() use($private_lead,$private_upload,$private_bytes,$users):void{
    global $wpdb;
    $wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>$users['other']),array('id'=>$private_lead));idlm_file_denied(static function() use($private_upload){return idlm_file_get($private_upload['url']);});idlm_http_check(idlm_file_get($private_upload['url'],'other')['body']===$private_bytes,'Reassigned owner cannot download');
    $wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>$users['owner']),array('id'=>$private_lead));
    $u=new WP_User($users['owner']);$u->add_cap('idlm_access_leads',false);idlm_file_denied(static function() use($private_upload){return idlm_file_get($private_upload['url']);});$u->add_cap('idlm_access_leads');
    update_user_meta($users['owner'],'idlm_crm_access_disabled','1');idlm_file_denied(static function() use($private_upload){return idlm_file_get($private_upload['url']);});delete_user_meta($users['owner'],'idlm_crm_access_disabled');
});
idlm_http_case('T13-C-upload-lock-rechecks-reassignment',static function() use($private_lead,$users,$sessions,$config):void{
    global $wpdb;
    $barrier=$config['content_dir'].'/barrier';file_put_contents($barrier,'file-reassignment');
    $args=array('curl','--silent','--show-error','--max-time','20','--cookie',$config['content_dir'].'/owner.cookies','--output',$barrier.'.body','--write-out','%{http_code}','-F','action=idlm_upload_lead_file','-F','nonce='.$sessions['owner']['admin'],'-F','lead_id='.$private_lead,'-F','purpose=floor_plan','-F','file=@'.$config['content_dir'].'/synthetic-private.pdf;type=application/pdf',$config['http_url'].'/wp-admin/admin-ajax.php');
    $proc=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
    try{$deadline=microtime(true)+10;while(!is_file($barrier.'.ready')&&microtime(true)<$deadline){usleep(10000);}idlm_http_check(is_file($barrier.'.ready'),'Upload did not reach lock barrier');$wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>$users['other']),array('id'=>$private_lead));$snapshot=idlm_file_snapshot();file_put_contents($barrier.'.release','go');$status=(int)stream_get_contents($pipes[1]);idlm_http_check($status===403 && $snapshot===idlm_file_snapshot() && strpos(file_get_contents($barrier.'.body'),'PRIVATE-FILE-PII')===false,'Stale upload authority wrote or disclosed data');}
    finally{file_put_contents($barrier.'.release','go');if(is_resource($proc)){proc_terminate($proc);fclose($pipes[1]);fclose($pipes[2]);proc_close($proc);}foreach(glob($barrier.'*') as $path){unlink($path);}$wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>$users['owner']),array('id'=>$private_lead));}
});
idlm_http_case('T13-H-lead-delete-erases-bytes-attachments-and-copied-URL',static function() use($private_lead,$private_upload):void{
    global $wpdb;
    idlm_http_check(!is_wp_error(IDLM_Lead_Deletion_Service::delete($private_lead)),'File-bearing deletion failed');idlm_file_denied(static function() use($private_upload){return idlm_file_get($private_upload['url']);});idlm_http_check(!get_post($private_upload['attachment_id']),'Attachment survived deletion');
    idlm_http_check((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_private_file_chunks")===0,'Private bytes survived deletion');
});

idlm_http_case('T13-H-explicit-manager-upload-and-grant-revocation',static function():void{
    global $users;
    $scopes=get_option('idlm_role_lead_scopes',array());$assigned=$scopes;$assigned['sales_manager']='assigned';update_option('idlm_role_lead_scopes',$assigned);
    $lead=idlm_file_new_lead();$f=idlm_file_upload_data(idlm_file_upload($lead,'manager'));idlm_http_check(!empty($f['url']),'Assigned-scope manager upload failed');
    idlm_file_denied(static function() use($f){return idlm_file_get($f['url'],'manager');});
    (new WP_User($users['manager']))->add_cap('idlm_assign_leads',false);
    try{idlm_file_denied(static function() use($lead){return idlm_file_upload($lead,'manager');});}finally{(new WP_User($users['manager']))->add_cap('idlm_assign_leads');update_option('idlm_role_lead_scopes',$scopes);}
    IDLM_Lead_Deletion_Service::delete($lead);
});
idlm_http_case('T13-H-pointer-removal-and-unsaved-expiry-revoke-and-erase',static function():void{
    global $wpdb;
    $lead=idlm_file_new_lead();$f=idlm_file_upload_data(idlm_file_upload($lead));$r=idlm_file_bind($lead,$f['url']);idlm_http_check(!empty(json_decode($r['body'],true)['success']),'Cannot bind removal fixture');
    idlm_http_check(false!==IDLM_DB::update_lead($lead,array('floor_plan_file'=>'','floor_plan_files'=>null)),'Pointer removal failed');idlm_file_denied(static function() use($f){return idlm_file_get($f['url']);});IDLM_Private_Files::cleanup();idlm_http_check(!get_post($f['attachment_id']),'Removed file attachment remains');
    $f=idlm_file_upload_data(idlm_file_upload($lead));$wpdb->update($wpdb->prefix.'idlm_private_files',array('created_at'=>gmdate('Y-m-d H:i:s',time()-8*DAY_IN_SECONDS)),array('attachment_id'=>$f['attachment_id']));idlm_file_denied(static function() use($f){return idlm_file_get($f['url']);});IDLM_Private_Files::reconcile_lead($lead);IDLM_Private_Files::cleanup();idlm_http_check(!get_post($f['attachment_id']),'Expired file remains');IDLM_Lead_Deletion_Service::delete($lead);
});
idlm_http_case('T13-H-legacy-public-URL-main-and-variant-revocation',static function():void{
    global $wpdb;
    $lead=idlm_file_new_lead();$bytes="%PDF-1.4\n% PRIVATE-FILE-PII-SYNTHETIC legacy\n%%EOF\n";$u=wp_upload_bits('http-legacy-'.wp_generate_uuid4().'.pdf',null,$bytes);idlm_http_check(empty($u['error']),'Legacy fixture failed');
    $a=wp_insert_attachment(array('post_title'=>'Synthetic','post_mime_type'=>'application/pdf'),$u['file']);update_post_meta($a,'_idlm_attachment_lead_id',$lead);
    $thumb=dirname($u['file']).'/variant-'.wp_generate_uuid4().'.pdf';file_put_contents($thumb,$bytes.'variant');wp_update_attachment_metadata($a,array('sizes'=>array('fixture'=>array('file'=>basename($thumb)))));$thumburl=dirname($u['url']).'/'.basename($thumb);
    $wpdb->update($wpdb->prefix.'idlm_leads',array('floor_plan_file'=>$u['url'],'floor_plan_files'=>wp_json_encode(array($u['url']))),array('id'=>$lead));
    foreach(array($u['url'],$thumburl) as $url){idlm_http_check(idlm_file_get($url,'anonymous')['status']===200,'Static baseline not reachable');}
    idlm_http_check(IDLM_Private_Files::migrate_lead($lead),'Legacy migration failed');IDLM_Private_Files::cleanup();
    foreach(array($u['url'],$thumburl) as $url){idlm_file_denied(static function() use($url){return idlm_file_get($url,'anonymous');});}
    idlm_http_check(idlm_file_get(IDLM_Private_Files::url($a))['body']===$bytes,'Protected legacy bytes changed');idlm_file_denied(static function() use($a){return idlm_file_get(IDLM_Private_Files::url($a),'other');});IDLM_Lead_Deletion_Service::delete($lead);
});

idlm_http_case('T13-H-backup-file-policy-and-stale-role-read-denial',static function():void{
    global $users,$sessions,$wpdb;
    $lead=idlm_file_new_lead();$f=idlm_file_upload_data(idlm_file_upload($lead));
    $r=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_export_backup','_wpnonce'=>$sessions['admin']['backup']),'admin',false);$payload=json_decode($r['body'],true);
    idlm_http_check($r['status']===200 && !empty($payload['file_policy']) && strpos($r['body'],'idlm_private_file_chunks')===false && strpos($r['body'],base64_encode("%PDF-1.4\n"))===false,'Backup private-file policy/payload incorrect: '.$r['body']);
    $role=get_role('author');$role->remove_cap('idlm_access_leads');$u=new WP_User($users['readall']);$u->remove_cap('idlm_access_leads');
    try{idlm_file_denied(static function() use($f){return idlm_file_get($f['url'],'readall');});}finally{$role->add_cap('idlm_access_leads');$u->add_cap('idlm_access_leads');}
    IDLM_Lead_Deletion_Service::delete($lead);
});
idlm_http_case('T13-H-upload-write-failure-rolls-back-and-retry-succeeds',static function():void{
    global $config;
    $lead=idlm_file_new_lead();file_put_contents($config['content_dir'].'/fail-private-chunk','fail');
    try{idlm_file_denied(static function() use($lead){return idlm_file_upload($lead);},array(500));}finally{unlink($config['content_dir'].'/fail-private-chunk');}
    $f=idlm_file_upload_data(idlm_file_upload($lead));idlm_http_check(idlm_file_get($f['url'])['status']===200,'Retry failed');IDLM_Lead_Deletion_Service::delete($lead);
});
foreach(array('ownership','capability') as $kind){
    idlm_http_case('T13-C-download-rechecks-live-'.$kind,static function() use($kind):void{
        global $wpdb,$config,$users;
        $lead=idlm_file_new_lead();$f=idlm_file_upload_data(idlm_file_upload($lead));$barrier=$config['content_dir'].'/read-barrier';file_put_contents($barrier,'files');
        $args=array('curl','--silent','--show-error','--max-time','20','--cookie',$config['content_dir'].'/owner.cookies','--output',$barrier.'.body','--write-out','%{http_code}',$f['url']);
        $proc=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
        try{
            $deadline=microtime(true)+10;while(!is_file($barrier.'.ready')&&microtime(true)<$deadline){usleep(10000);}idlm_http_check(is_file($barrier.'.ready'),'Download missed bytes barrier');
            if($kind==='ownership'){$wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>$users['other']),array('id'=>$lead));}else{(new WP_User($users['owner']))->add_cap('idlm_access_leads',false);}
            $before=idlm_file_snapshot();file_put_contents($barrier.'.release','go');$status=(int)stream_get_contents($pipes[1]);$body=file_get_contents($barrier.'.body');
            idlm_http_check($status===404 && strpos($body,'%PDF')===false && strpos($body,'PRIVATE-FILE-PII')===false && $before===idlm_file_snapshot(),'Stale read disclosed bytes or changed state');
        }finally{
            file_put_contents($barrier.'.release','go');if(is_resource($proc)){proc_terminate($proc);fclose($pipes[1]);fclose($pipes[2]);proc_close($proc);}foreach(glob($barrier.'*') as $path){unlink($path);}
            (new WP_User($users['owner']))->add_cap('idlm_access_leads');IDLM_Lead_Deletion_Service::delete($lead);
        }
    });
}
idlm_http_case('T13-H-authorized-owner-and-manager-quotation-purpose-upload',static function():void{
    global $private_bytes;
    $lead=idlm_file_new_lead();
    foreach(array('owner','manager') as $actor){$f=idlm_file_upload_data(idlm_file_upload($lead,$actor,'quote'));$r=idlm_file_bind($lead,$f['url'],'owner','quote_file');idlm_http_check(!empty(json_decode($r['body'],true)['success']),'Quotation file binding failed');idlm_http_check(idlm_file_get($f['url'])['body']===$private_bytes,'Quotation file bytes differ');idlm_file_denied(static function() use($f){return idlm_file_get($f['url'],'other');});}
    IDLM_Lead_Deletion_Service::delete($lead);
    $lead=idlm_file_new_lead('readall');$f=idlm_file_upload_data(idlm_file_upload($lead,'readall','quote'));$r=idlm_file_bind($lead,$f['url'],'readall','quote_file');idlm_http_check(!empty(json_decode($r['body'],true)['success']) && idlm_file_get($f['url'],'readall')['body']===$private_bytes,'Read-all current owner quotation-file workflow failed');IDLM_Lead_Deletion_Service::delete($lead);
});
// A valid image remains available only while the explicitly held browser fixture exists.
if(in_array('--hold',$argv,true)){
    $image=$config['content_dir'].'/synthetic-private.png';file_put_contents($image,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    $browserlead=idlm_file_new_lead();$browserfile=idlm_file_upload_data(idlm_file_upload($browserlead,'owner','floor_plan',$image));echo 'BROWSER FILE='.$browserfile['url']."\n";
}
