<?php
/** QUO-006 actual saved revisions and print/PDF/CSV/XLSX consistency. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }
function idlm_document_http_settings(string $tag='Original',string $currency='INR'): array {
    return array('paper_size'=>'A4','currency_code'=>$currency,'cover_page_enabled'=>1,'cover_page_html'=>'<h1>'.$tag.' contractual cover</h1>','after_quote_enabled'=>1,'after_quote_html'=>'<p>'.$tag.' contractual closing</p>[idlm_payment_structure]','pdf_footer'=>$tag.' contractual footer','payment_structure'=>wp_json_encode(array(array('name'=>$tag.' deposit','percentage'=>'50'),array('name'=>$tag.' completion','percentage'=>'50'))));
}
function idlm_document_http_summary(string $csv): array {
    $stream=fopen('php://temp','r+');fwrite($stream,$csv);rewind($stream);$rows=array();
    while(($row=fgetcsv($stream,0,',','"',''))!==false){if(count($row)===2){$rows[$row[0]]=$row[1];}}
    fclose($stream);return $rows;
}
function idlm_document_http_formats(object $quote,int $revision,string $tag,string $currency,string $actor='owner',bool $pdf=false): void {
    global $config,$sessions;
    $before=idlm_http_snapshot();$printed=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_print_quote','quote_id'=>$quote->id,'revision_id'=>$revision,'_wpnonce'=>$sessions[$actor]['print']),$actor,false);
    idlm_http_check($printed['status']===200,'Print request failed');foreach(array($tag.' contractual cover',$tag.' contractual closing',$tag.' contractual footer',$tag.' deposit') as $text){idlm_http_check(strpos($printed['body'],$text)!==false,'Print missing '.$text);}
    $symbol=$currency==='INR'?'₹':'$';idlm_http_check(strpos($printed['body'],$symbol.'118.00')!==false && strpos($printed['body'],$symbol.'59.00')!==false,'Print currency or milestone amount drift');
    $detail=idlm_http_request('/workspace',array('action'=>'view','quote_id'=>$quote->id,'revision_id'=>$revision),$actor,false);
    idlm_http_check($detail['status']===200 && strpos($detail['body'],'<span>Grand total</span><strong>'.$symbol.'118.00</strong>')!==false,'Detail grand total substituted current currency');
    $csv=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_download_quote_csv','quote_id'=>$quote->id,'revision_id'=>$revision,'_wpnonce'=>$sessions[$actor]['csv']),$actor,false);$summary=idlm_document_http_summary($csv['body']);
    idlm_http_check($csv['status']===200 && $summary['Currency']===$currency && $summary['Footer terms']===$tag.' contractual footer' && $summary['Payment percentage: '.$tag.' deposit']==='50.00%' && (float)$summary['Payment amount: '.$tag.' deposit']===59.0,'CSV contract mismatch');
    $xlsx=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_download_quote_xlsx','quote_id'=>$quote->id,'revision_id'=>$revision,'_wpnonce'=>$sessions[$actor]['xlsx']),$actor,false);$file=$config['content_dir'].'/document.xlsx';file_put_contents($file,$xlsx['body']);$zip=new ZipArchive();idlm_http_check($xlsx['status']===200 && $zip->open($file)===true,'Invalid XLSX');
    $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');$styles=$zip->getFromName('xl/styles.xml');$zip->close();unlink($file);
    idlm_http_check(strpos($sheet,$tag.' contractual footer')!==false && strpos($sheet,'Payment amount: '.$tag.' deposit')!==false && strpos($styles,'[$'.$currency.']')!==false,'XLSX terms/currency mismatch');
    if($pdf){
        $download=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_download_quote_pdf','quote_id'=>$quote->id,'revision_id'=>$revision,'_wpnonce'=>$sessions[$actor]['pdf']),$actor,false);
        idlm_http_check($download['status']===200 && strncmp($download['body'],'%PDF-',5)===0,'PDF failed: '.$download['headers']);
        $file=$config['content_dir'].'/document.pdf';file_put_contents($file,$download['body']);$command=escapeshellarg(getenv('IDLM_TEST_PDFTOTEXT') ?: 'pdftotext').' -layout '.escapeshellarg($file).' -';$process=proc_open($command,array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$text=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);unlink($file);
        idlm_http_check($exit===0,'PDF extraction failed: '.$errors);foreach(array($tag.' contractual cover',$tag.' contractual closing',$tag.' contractual footer',$tag.' deposit',$symbol.'118.00',$symbol.'59.00') as $token){idlm_http_check(strpos($text,$token)!==false,'PDF missing '.$token);}
        $evidence=getenv('IDLM_TEST_DOCUMENT_EVIDENCE_DIR');if($evidence){file_put_contents($evidence.'/historical-document.pdf',$download['body']);file_put_contents($evidence.'/historical-document.html',$printed['body']);file_put_contents($evidence.'/historical-document.csv',$csv['body']);file_put_contents($evidence.'/historical-document.xlsx',$xlsx['body']);file_put_contents($evidence.'/historical-document.txt',$text);}
    }
    idlm_http_check($before===idlm_http_snapshot(),'Document reads rewrote aggregate/history/working-copy state');
}
function idlm_document_http_fault(string $needle, callable $callback): void {
    global $config;
    $file=$config['content_dir'].'/fail-quotation-write';file_put_contents($file,$needle);
    try { $callback(); } finally {
        unlink($file);$log=$config['content_dir'].'/expected-quotation-faults.log';
        if (is_file($log)) {
            $messages=file_get_contents($log);
            foreach(explode("\n",trim($messages)) as $message) { idlm_http_check((bool)preg_match('/^\[[^\]]+\] \[IDLM Quotations\] Current revision pointer update failed: Table .*idlm_absent_table.*doesn.t exist$/',$message),'Unexpected document fault diagnostic: '.$message); }
            $evidence=getenv('IDLM_TEST_DOCUMENT_EVIDENCE_DIR');if($evidence){file_put_contents($evidence.'/expected-http-fault-diagnostics.log',$messages,FILE_APPEND);}unlink($log);
        }
    }
}
idlm_http_case('T10-T12-H-QUO006-historical-print-PDF-CSV-XLSX-and-repeat-reads-retain-saved-contract',static function():void {
    $old=get_option('idlm_quote_settings');
    try{
        update_option('idlm_quote_settings',idlm_document_http_settings(),false);$quote=idlm_history_http_fixture();$revision=(int)$quote->current_revision_id;
        update_option('idlm_quote_settings',idlm_document_http_settings('Changed','USD'),false);
        foreach(array('owner','admin') as $actor){idlm_document_http_formats($quote,$revision,'Original','INR',$actor,$actor==='owner');}
        idlm_document_http_formats($quote,$revision,'Original','INR');
        // Fixture for manual browser checks; retain only synthetic IDs in the evidence.
        $evidence=getenv('IDLM_TEST_DOCUMENT_EVIDENCE_DIR');if($evidence){file_put_contents($evidence.'/browser-fixture.json',wp_json_encode(array('url'=>$GLOBALS['config']['http_url'],'quote_id'=>(int)$quote->id,'revision_id'=>$revision)));}
    }finally{update_option('idlm_quote_settings',$old,false);}
});
idlm_http_case('T10-T12-H-QUO006-revision-and-autosave-retain-original-currency-and-save-current-terms-only-to-new-revision',static function():void {
    global $wpdb;$old=get_option('idlm_quote_settings');
    try{
        update_option('idlm_quote_settings',idlm_document_http_settings(),false);$quote=idlm_history_http_fixture();$original=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_quote_revisions WHERE id=%d",$quote->current_revision_id),ARRAY_A);
        update_option('idlm_quote_settings',idlm_document_http_settings('Updated','USD'),false);$p=idlm_http_payload((int)$quote->lead_id,(int)$quote->id,(int)$quote->current_revision_id);$p['currency_code']='EUR';
        $r=idlm_http_autosave($p);idlm_http_check($r['status']===200,'Autosave failed');$working=get_transient('idlm_quote_autosave_'.$GLOBALS['users']['owner'].'_'.$quote->current_revision_id);idlm_http_check($working['revision']['currency_code']==='INR' && strpos($working['revision']['document_terms'],'Updated deposit')!==false,'Working-copy contract mismatch');
        idlm_http_check(idlm_http_save($p)['status']===302,'Revision failed');$next=IDLM_Quotation_Repository::get_quote($quote->id);idlm_document_http_formats($next,(int)$next->current_revision_id,'Updated','INR');
        $saved=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_quote_revisions WHERE id=%d",$original['id']),ARRAY_A);idlm_http_check($saved['status']==='Superseded' && !empty($saved['finalized_at']),'Previous revision was not superseded');unset($saved['status'],$saved['updated_at'],$saved['finalized_at']);unset($original['status'],$original['updated_at'],$original['finalized_at']);idlm_http_check($original===$saved,'Old revision contract fields changed');idlm_document_http_formats($next,(int)$original['id'],'Original','INR');
    }finally{wp_cache_flush();update_option('idlm_quote_settings',$old,false);}
});
idlm_http_case('T10-T12-H-QUO006-new-quotes-use-current-currency-and-contract-while-existing-quotes-retain-their-own',static function():void {
    $old=get_option('idlm_quote_settings');try{update_option('idlm_quote_settings',idlm_document_http_settings('USD new','USD'),false);$q=idlm_history_http_fixture();update_option('idlm_quote_settings',idlm_document_http_settings('Later','INR'),false);idlm_document_http_formats($q,(int)$q->current_revision_id,'USD new','USD');}finally{update_option('idlm_quote_settings',$old,false);}
});
idlm_http_case('T10-H-QUO006-invalid-or-excessive-configured-terms-refuse-save-and-autosave-without-partial-write',static function():void {
    $old=get_option('idlm_quote_settings');try{foreach(array(array('payment_structure'=>'bad'),array('payment_structure'=>'[{"name":"Bad","percentage":"1e309"}]'),array('after_quote_html'=>str_repeat('x',32001))) as $bad){update_option('idlm_quote_settings',idlm_document_http_settings(),false);idlm_numeric_http_denials(static function($p){return $p;},static function()use($bad){update_option('idlm_quote_settings',array_merge(idlm_document_http_settings(),$bad),false);});}}finally{update_option('idlm_quote_settings',$old,false);}
});
idlm_http_case('T10-H-QUO006-snapshot-insert-failure-rolls-back-number-aggregate-and-working-copy-and-retry-saves-once',static function():void {
    global $wpdb;$old=get_option('idlm_quote_settings');try{update_option('idlm_quote_settings',idlm_document_http_settings(),false);$q=idlm_history_http_fixture();$p=idlm_http_payload((int)$q->lead_id,(int)$q->id,(int)$q->current_revision_id);idlm_http_check(idlm_http_autosave($p)['status']===200,'Seed autosave failed');
        idlm_document_http_fault('INSERT INTO `'.$wpdb->prefix.'idlm_quote_revisions`',static function()use($p){$before=idlm_http_snapshot();$r=idlm_http_save($p);idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_error=')!==false && $before===idlm_http_snapshot(),'Failed snapshot persisted partial state');});
        idlm_http_check(idlm_http_save($p)['status']===302,'Retry failed');$next=IDLM_Quotation_Repository::get_quote($q->id);$revision=IDLM_Quotation_Repository::get_revision($next->current_revision_id);idlm_http_check((int)$revision->revision_number===2 && is_array(IDLM_Quotation_Document::read($revision)),'Retry lost snapshot or duplicated revision');$before=idlm_http_snapshot();idlm_http_save($p);idlm_http_check($before===idlm_http_snapshot(),'UUID retry changed stored contract');
    }finally{update_option('idlm_quote_settings',$old,false);}
});
idlm_http_case('T10-T12-H-QUO006-legacy-and-corrupt-terms-are-explicit-without-live-template-or-currency-substitution',static function():void {
    global $wpdb,$sessions;$old=get_option('idlm_quote_settings');try{update_option('idlm_quote_settings',idlm_document_http_settings(),false);$q=idlm_history_http_fixture();update_option('idlm_quote_settings',idlm_document_http_settings('Live forbidden','USD'),false);
        foreach(array(null,'{"version":2}') as $terms){$wpdb->update($wpdb->prefix.'idlm_quote_revisions',array('document_terms'=>$terms),array('id'=>$q->current_revision_id));$before=idlm_http_snapshot();$r=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_print_quote','quote_id'=>$q->id,'revision_id'=>$q->current_revision_id,'_wpnonce'=>$sessions['owner']['print']),'owner',false);idlm_http_check($r['status']===200 && strpos($r['body'],'Live forbidden')===false && strpos($r['body'],'₹118.00')!==false && strpos($r['body'],$terms===null?'Historical document terms were not captured':'Saved document terms are invalid')!==false,'Unknown contract hidden or substituted');
            $csv=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_download_quote_csv','quote_id'=>$q->id,'revision_id'=>$q->current_revision_id,'_wpnonce'=>$sessions['owner']['csv']),'owner',false);$summary=idlm_document_http_summary($csv['body']);idlm_http_check($summary['Currency']==='INR' && !isset($summary['Footer terms']) && $before===idlm_http_snapshot(),'Legacy export invented terms or rewrote data');}
    }finally{update_option('idlm_quote_settings',$old,false);}
});
