<?php
/** Actual multipart catalogue preview/import and denial tests. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }
function idlm_catalogue_http_upload($file, $mode, $actor = 'admin', $nonce = null): array {
    global $config,$sessions;
    $body=tempnam($config['content_dir'],'catalogue-body-'); $headers=$body.'.headers';
    $args=array('curl','--silent','--show-error','--max-time','20','--cookie',$config['content_dir'].'/'.$actor.'.cookies','--dump-header',$headers,'--output',$body,'--write-out','%{http_code}', '-F','action=idlm_import_catalogue_csv','-F','import_mode='.$mode,'-F','_wpnonce='.($nonce ?? $sessions[$actor]['catalogue_import'] ?? ''),'-F','catalogue_csv=@'.$file.';type=text/csv',$config['http_url'].'/wp-admin/admin-ajax.php');
    $process=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
    $status=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    $raw=(string)file_get_contents($body);$result=array('status'=>(int)$status,'json'=>json_decode($raw,true),'raw'=>$raw);
    unlink($body);unlink($headers);idlm_http_check($exit===0,'Upload transport failed: '.$error);return $result;
}
$catalogue_file=$config['content_dir'].'/catalogue-preview.csv';
$catalogue_code='CSV-'.wp_generate_uuid4();
$stream=fopen($catalogue_file,'w');
fputcsv($stream,array('item_code','category','item_name','unit','default_rate','status'),',','"','');
foreach(array(array($catalogue_code,'CSV Kitchen','Cabinet','Sq Ft','1,790.00','active'),array($catalogue_code,'CSV Kitchen','Duplicate','Sq Ft','10','active'),array('','CSV Kitchen','Invalid rate','pcs','1,79.00','active'),array('','CSV Kitchen','New item','pcs','750','inactive')) as $row){fputcsv($stream,$row,',','"','');}fclose($stream);
idlm_http_case('CatalogueCSV-H-preview-uses-real-upload-and-does-not-write',static function()use($catalogue_file):void{
    // Warm normal runtime setup before comparing the read-only preview request.
    idlm_catalogue_http_upload($catalogue_file,'preview');
    $before=idlm_http_snapshot();$r=idlm_catalogue_http_upload($catalogue_file,'preview');
    idlm_http_check($r['status']===200 && ($r['json']['success']??false),'Preview failed: '.$r['raw']);$d=$r['json']['data'];
    idlm_http_check($d['valid']===2 && $d['invalid']===1 && $d['duplicates']===1,'Incorrect preview counts');
    idlm_http_check($d['rows'][2]['row']===4 && strpos(implode(' ',$d['rows'][2]['messages']),'grouping')!==false,'Specific row error missing');
    idlm_http_check($before===idlm_http_snapshot(),'Preview changed database');
});
idlm_http_case('CatalogueCSV-H-permission-nonce-mode-and-upload-denials-write-nothing',static function()use($catalogue_file):void{
    $before=idlm_http_snapshot();
    foreach(array(array('preview','owner',null),array('preview','admin','invalid'),array('arbitrary','admin',null)) as $v){$r=idlm_catalogue_http_upload($catalogue_file,$v[0],$v[1],$v[2]);idlm_http_check(in_array($r['status'],array(400,403),true),'Unauthorized/invalid request accepted');}
    $r=idlm_http_request('/wp-admin/admin-ajax.php',array('action'=>'idlm_import_catalogue_csv','import_mode'=>'preview','_wpnonce'=>$GLOBALS['sessions']['admin']['catalogue_import']),'admin');
    idlm_http_check($r['status']===400 && strpos($r['body'],'Choose a CSV')!==false,'Missing upload error not returned inline');
    idlm_http_check($before===idlm_http_snapshot(),'Denied import changed rows');
});
idlm_http_case('CatalogueCSV-H-commit-failure-rolls-back-and-valid-import-persists',static function()use($catalogue_file):void{
    global $config,$wpdb;
    $before=idlm_http_snapshot();file_put_contents($config['content_dir'].'/fail-quotation-write','COMMIT');
    try{$r=idlm_catalogue_http_upload($catalogue_file,'import');idlm_http_check($r['status']===400 && strpos($r['raw'],'no items were imported')!==false,'Commit failure missing');idlm_http_check($before===idlm_http_snapshot(),'Commit failure left partial rows');}
    finally{unlink($config['content_dir'].'/fail-quotation-write');}
    $r=idlm_catalogue_http_upload($catalogue_file,'import');idlm_http_check($r['status']===200 && ($r['json']['data']['imported']??0)===2,'Valid import failed: '.$r['raw']);
    $rows=$wpdb->get_results("SELECT item_code,default_rate,status FROM {$wpdb->prefix}idlm_quote_catalogue_items WHERE category='CSV Kitchen' ORDER BY id",ARRAY_A);
    idlm_http_check(count($rows)===2 && $rows[0]['default_rate']==='1790.00' && $rows[1]['status']==='inactive' && strpos($rows[1]['item_code'],'ITEM-')===0,'Stored import differs from preview');
    $r=idlm_catalogue_http_upload($catalogue_file,'preview');idlm_http_check($r['json']['data']['valid']===1 && $r['json']['data']['duplicates']===2,'Existing duplicate codes not detected');
});
idlm_http_case('CatalogueCSV-H-no-unexpected-PHP-diagnostics',static function():void{
    global $config;$errors=is_file($config['content_dir'].'/php-errors.log')?file_get_contents($config['content_dir'].'/php-errors.log'):'';
    idlm_http_check(trim($errors)==='','Unexpected PHP diagnostics: '.$errors);
});
