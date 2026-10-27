<?php
/** Native private-file migration/schema/rollback/recovery assertions. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

function idlm_native_private_fixture(bool $variants = false): array {
    global $wpdb,$administrator_id;
    wp_set_current_user($administrator_id);
    $now=current_time('mysql');$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'PRIVATE-NATIVE-SYNTHETIC','assigned_rep_id'=>$administrator_id,'created_at'=>$now,'updated_at'=>$now,'stage'=>'New'));$lead=(int)$wpdb->insert_id;
    $bytes="%PDF-1.4\n% PRIVATE-NATIVE-SYNTHETIC\n%%EOF\n"; $uploaded=wp_upload_bits('synthetic-native-'.wp_generate_uuid4().'.pdf',null,$bytes);
    idlm_integration_assert_true(empty($uploaded['error']),'Legacy fixture upload failed');
    $attachment=wp_insert_attachment(array('post_title'=>'Synthetic old file','post_mime_type'=>'application/pdf','post_status'=>'inherit'),$uploaded['file'],0,true);
    idlm_integration_assert_true(!is_wp_error($attachment));update_post_meta($attachment,'_idlm_attachment_lead_id',$lead);
    $paths=array($uploaded['file']);
    if($variants){$uploads=wp_upload_dir();$relative=substr($uploaded['file'],strlen($uploads['basedir'])+1);$thumb=dirname($uploaded['file']).'/thumb-'.wp_generate_uuid4().'.pdf';$original=dirname($uploaded['file']).'/original-'.wp_generate_uuid4().'.pdf';file_put_contents($thumb,$bytes.'thumb');file_put_contents($original,$bytes.'original');wp_update_attachment_metadata($attachment,array('file'=>$relative,'sizes'=>array('synthetic'=>array('file'=>basename($thumb))), 'original_image'=>basename($original)));$paths[]=$thumb;$paths[]=$original;}
    $wpdb->update($wpdb->prefix.'idlm_leads',array('floor_plan_file'=>$uploaded['url'],'floor_plan_files'=>wp_json_encode(array($uploaded['url']))),array('id'=>$lead));
    return array('lead'=>$lead,'attachment'=>(int)$attachment,'url'=>$uploaded['url'],'bytes'=>$bytes,'paths'=>$paths);
}
function idlm_native_private_bytes(int $file, int $variant=0): string {
    global $wpdb;$rows=$wpdb->get_col($wpdb->prepare("SELECT data FROM {$wpdb->prefix}idlm_private_file_chunks WHERE file_id = %d AND variant = %d ORDER BY part",$file,$variant));return implode('',array_map(static function($data){return base64_decode($data,true);},$rows));
}

idlm_integration_test('private file installer verifies schema/engine and retries a failed new-table installation',static function():void{
    global $wpdb;
    idlm_integration_assert_true(IDLM_Private_Files::ready());
    idlm_integration_assert_true(in_array('attachment_id',idlm_integration_index_names($wpdb->prefix.'idlm_private_files'),true));
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_private_files DROP INDEX attachment_id");idlm_integration_assert_true(!IDLM_Private_Files::ready());idlm_integration_assert_true(IDLM_Private_Files::install());
    $wpdb->query("DROP TABLE {$wpdb->prefix}idlm_private_file_chunks");delete_option('idlm_private_file_schema_version');
    $fail=static function($sql){return strpos($sql,'CREATE TABLE '.$GLOBALS['wpdb']->prefix.'idlm_private_file_chunks')===0?'SELECT 1':$sql;};add_filter('query',$fail);
    try{idlm_integration_assert_true(!IDLM_Private_Files::install());idlm_integration_assert_same(false,get_option('idlm_private_file_schema_version',false));}finally{remove_filter('query',$fail);}
    idlm_integration_assert_true(IDLM_Private_Files::install());idlm_integration_assert_same(IDLM_Private_Files::VERSION,get_option('idlm_private_file_schema_version'));
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_private_file_chunks ENGINE=MyISAM");idlm_integration_assert_true(!IDLM_Private_Files::ready());$wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_private_file_chunks ENGINE=InnoDB");idlm_integration_assert_true(IDLM_Private_Files::ready());
});
idlm_integration_test('legacy private migration verifies all original/variant bytes before resumable public-copy cleanup',static function():void{
    global $wpdb;
    $f=idlm_native_private_fixture(true);IDLM_Private_Files::init();$expected=array_map('file_get_contents',$f['paths']);
    idlm_integration_assert_true(IDLM_Private_Files::migrate_lead($f['lead']));$file=(int)get_post_meta($f['attachment'],'_idlm_private_file_id',true);
    foreach($expected as $variant=>$bytes){idlm_integration_assert_same($bytes,idlm_native_private_bytes($file,$variant));}
    idlm_integration_assert_same(IDLM_Private_Files::url($f['attachment']),$wpdb->get_var($wpdb->prepare("SELECT floor_plan_file FROM {$wpdb->prefix}idlm_leads WHERE id = %d",$f['lead'])));
    $fail=static function($path){return '';};add_filter('wp_delete_file',$fail);
    try{idlm_integration_assert_true(IDLM_Private_Files::cleanup()>0);foreach($f['paths'] as $path){idlm_integration_assert_true(is_file($path),'Protected copy failure removed public bytes');}}finally{remove_filter('wp_delete_file',$fail);}
    IDLM_Private_Files::cleanup();foreach($f['paths'] as $path){idlm_integration_assert_true(!file_exists($path),'Public main/variant remains');}
    foreach($expected as $variant=>$bytes){idlm_integration_assert_same($bytes,idlm_native_private_bytes($file,$variant));}
    $before=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_private_file_chunks");idlm_integration_assert_true(IDLM_Private_Files::migrate_lead($f['lead']));idlm_integration_assert_same($before,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_private_file_chunks"));
    $restore=new ReflectionMethod('IDLM_Backup','restore_payload');if (PHP_VERSION_ID < 80100) { $restore->setAccessible(true); }$lead_count=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads");idlm_integration_assert_true(is_wp_error($restore->invoke(null,array('tables'=>array(),'options'=>array()))));idlm_integration_assert_same($lead_count,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads"));
    idlm_integration_assert_true(!is_wp_error(IDLM_Lead_Deletion_Service::delete($f['lead'])));idlm_integration_assert_same('',idlm_native_private_bytes($file));idlm_integration_assert_true(!get_post($f['attachment']));
});
idlm_integration_test('private migration rejects missing/shared/traversal inputs and failed chunk writes without losing source',static function():void{
    global $wpdb;
    $f=idlm_native_private_fixture();$before=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_private_files");
    $fail=static function($sql){return strpos($sql,'INSERT INTO `'.$GLOBALS['wpdb']->prefix.'idlm_private_file_chunks`')===0?'INSERT INTO idlm_nonexistent_failure_table VALUES (1)':$sql;};$old=$wpdb->suppress_errors(true);add_filter('query',$fail);
    try{idlm_integration_assert_true(!IDLM_Private_Files::migrate_lead($f['lead']));}finally{remove_filter('query',$fail);$wpdb->suppress_errors($old);}
    idlm_integration_assert_same($before,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_private_files"));idlm_integration_assert_same($f['bytes'],file_get_contents($f['paths'][0]));idlm_integration_assert_same($f['url'],$wpdb->get_var($wpdb->prepare("SELECT floor_plan_file FROM {$wpdb->prefix}idlm_leads WHERE id = %d",$f['lead'])));
    $now=current_time('mysql');$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'Synthetic shared','floor_plan_file'=>$f['url'],'created_at'=>$now,'updated_at'=>$now));$shared=(int)$wpdb->insert_id;
    idlm_integration_assert_true(!IDLM_Private_Files::migrate_lead($f['lead']));idlm_integration_assert_true(is_wp_error(IDLM_Lead_Deletion_Service::delete($f['lead'])));$wpdb->delete($wpdb->prefix.'idlm_leads',array('id'=>$shared));
    $path=new ReflectionMethod('IDLM_Private_Files','local_path');if (PHP_VERSION_ID < 80100) { $path->setAccessible(true); }foreach(array('../outside.pdf','/tmp/outside.pdf',"bad\0file",'https://example.test/file') as $value){idlm_integration_assert_same(false,$path->invoke(null,$value));}
    unlink($f['paths'][0]);idlm_integration_assert_true(!IDLM_Private_Files::migrate_lead($f['lead']));$wpdb->delete($wpdb->prefix.'idlm_leads',array('id'=>$f['lead']));wp_delete_attachment($f['attachment'],true);
});
idlm_integration_test('file erasure rolls back with lead deletion failure and CRM reset removes private and legacy copies',static function():void{
    global $wpdb;
    $private=idlm_native_private_fixture();idlm_integration_assert_true(IDLM_Private_Files::migrate_lead($private['lead']));IDLM_Private_Files::cleanup();$file=(int)get_post_meta($private['attachment'],'_idlm_private_file_id',true);$before=idlm_native_private_bytes($file);
    $fail=static function($sql){return strpos($sql,'DELETE FROM `'.$GLOBALS['wpdb']->prefix.'idlm_leads`')===0?'DELETE FROM idlm_nonexistent_failure_table':$sql;};$old=$wpdb->suppress_errors(true);add_filter('query',$fail);
    try{idlm_integration_assert_true(is_wp_error(IDLM_Lead_Deletion_Service::delete($private['lead'])));}finally{remove_filter('query',$fail);$wpdb->suppress_errors($old);}
    idlm_integration_assert_same($before,idlm_native_private_bytes($file));idlm_integration_assert_true((bool)get_post($private['attachment']));
    $legacy=idlm_native_private_fixture(true);$count=(string)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads");$result=IDLM_Lead_Deletion_Service::reset_all($count);idlm_integration_assert_true(!is_wp_error($result),'File reset failed');
    idlm_integration_assert_same(0,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_private_file_chunks"));idlm_integration_assert_same(0,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_private_files"));foreach($legacy['paths'] as $path){idlm_integration_assert_true(!file_exists($path));}idlm_integration_assert_true(!get_post($legacy['attachment']));
});

idlm_integration_test('tagged unsaved legacy uploads migrate without binding and erase with their lead',static function():void{
    global $wpdb;
    $f=idlm_native_private_fixture(true);$wpdb->update($wpdb->prefix.'idlm_leads',array('floor_plan_file'=>'','floor_plan_files'=>null),array('id'=>$f['lead']));
    idlm_integration_assert_true(IDLM_Private_Files::migrate_lead($f['lead']));IDLM_Private_Files::cleanup();
    $id=(int)get_post_meta($f['attachment'],'_idlm_private_file_id',true);idlm_integration_assert_same($f['bytes'],idlm_native_private_bytes($id));
    idlm_integration_assert_same('uploaded',$wpdb->get_var($wpdb->prepare("SELECT state FROM {$wpdb->prefix}idlm_private_files WHERE id = %d",$id)));
    idlm_integration_assert_same('',(string)$wpdb->get_var($wpdb->prepare("SELECT floor_plan_file FROM {$wpdb->prefix}idlm_leads WHERE id = %d",$f['lead'])));
    foreach($f['paths'] as $path){idlm_integration_assert_true(!file_exists($path));}
    idlm_integration_assert_true(!is_wp_error(IDLM_Lead_Deletion_Service::delete($f['lead'])));idlm_integration_assert_same('',idlm_native_private_bytes($id));
    $old=idlm_native_private_fixture(true);$wpdb->update($wpdb->prefix.'idlm_leads',array('floor_plan_file'=>'','floor_plan_files'=>null),array('id'=>$old['lead']));
    idlm_integration_assert_true(!is_wp_error(IDLM_Lead_Deletion_Service::delete($old['lead'])));foreach($old['paths'] as $path){idlm_integration_assert_true(!file_exists($path));}idlm_integration_assert_true(!get_post($old['attachment']));
});

idlm_integration_test('cleanup commit failure retains verified bytes and journal for safe retry after public unlink',static function():void{
    global $wpdb;
    $f=idlm_native_private_fixture(true);idlm_integration_assert_true(IDLM_Private_Files::migrate_lead($f['lead']));$id=(int)get_post_meta($f['attachment'],'_idlm_private_file_id',true);
    $fail=static function($sql){return $sql==='COMMIT'?'COMMIT idlm_injected_failure':$sql;};$old=$wpdb->suppress_errors(true);add_filter('query',$fail);
    try{idlm_integration_assert_true(IDLM_Private_Files::cleanup()>0);}finally{remove_filter('query',$fail);$wpdb->suppress_errors($old);}
    idlm_integration_assert_same('pending',$wpdb->get_var($wpdb->prepare("SELECT state FROM {$wpdb->prefix}idlm_private_files WHERE id = %d",$id)));idlm_integration_assert_same($f['bytes'],idlm_native_private_bytes($id));
    IDLM_Private_Files::cleanup();idlm_integration_assert_same('active',$wpdb->get_var($wpdb->prepare("SELECT state FROM {$wpdb->prefix}idlm_private_files WHERE id = %d",$id)));foreach($f['paths'] as $path){idlm_integration_assert_true(!file_exists($path));}idlm_integration_assert_same($f['bytes'],idlm_native_private_bytes($id));IDLM_Lead_Deletion_Service::delete($f['lead']);
});

idlm_integration_test('managed upload history is excluded from legacy batch limits and deletion erases all journals',static function():void{
    global $wpdb,$administrator_id;
    $now=current_time('mysql');$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'SYNTHETIC-UPLOAD-HISTORY','assigned_rep_id'=>$administrator_id,'created_at'=>$now,'updated_at'=>$now));$lead=(int)$wpdb->insert_id;
    for($i=0;$i<101;$i++){
        $a=wp_insert_attachment(array('post_title'=>'Protected lead file','post_status'=>'private','post_mime_type'=>'application/pdf'),'',0,true,false);idlm_integration_assert_true(!is_wp_error($a));
        $wpdb->insert($wpdb->prefix.'idlm_private_files',array('lead_id'=>$lead,'attachment_id'=>$a,'purpose'=>'floor_plan','filename'=>'synthetic.pdf','mime'=>'application/pdf','byte_size'=>1,'sha256'=>hash('sha256','x'),'state'=>'uploaded','archive'=>'[]','created_at'=>$now));update_post_meta($a,'_idlm_private_file_id',$wpdb->insert_id);update_post_meta($a,'_idlm_attachment_lead_id',$lead);
    }
    idlm_integration_assert_true(IDLM_Private_Files::migrate_lead($lead),'Private history incorrectly exceeded legacy batch limit');idlm_integration_assert_true(!is_wp_error(IDLM_Lead_Deletion_Service::delete($lead)),'Private history blocked lead deletion');
    for($i=0;$i<3;$i++){IDLM_Private_Files::cleanup(50);}idlm_integration_assert_same(0,(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_private_files WHERE lead_id = %d",$lead)));
});
