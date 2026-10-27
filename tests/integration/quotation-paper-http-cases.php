<?php
/** QUO-009 actual settings, print and parsed PDF downloads. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }
function idlm_paper_tool(string $tool, array $args): string {
    $command = implode(' ', array_map('escapeshellarg', array_merge(array($tool), $args)));
    $process = proc_open($command, array(1=>array('pipe','w'),2=>array('pipe','w')), $pipes);
    $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
    idlm_http_check($exit === 0 && trim($errors) === '', $tool . ' failed: ' . $errors);
    return $output;
}
$paper_old_settings = get_option('idlm_quote_settings');
try {
    update_option('idlm_quote_settings', array('cover_page_enabled'=>1,'cover_page_html'=>'<h1>Paper test contractual cover</h1>','after_quote_enabled'=>1,'after_quote_html'=>'<p>Paper test contractual closing</p>[idlm_payment_structure]','pdf_footer'=>'Paper test contractual footer','payment_structure'=>'[{"name":"Paper test deposit","percentage":50},{"name":"Paper test completion","percentage":50}]'), false);
    $now=current_time('mysql');
    idlm_http_check(1===$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'Synthetic paper fixture','assigned_rep_id'=>$users['owner'],'created_at'=>$now,'updated_at'=>$now)),'Paper fixture lead failed');
    $paper_lead=(int)$wpdb->insert_id;
    idlm_http_check(idlm_http_save(idlm_http_payload($paper_lead))['status']===302,'Initial paper fixture save failed');
    $paper_quote=IDLM_Quotation_Repository::get_quote_for_lead($paper_lead);
    idlm_http_check((bool)$paper_quote,'Missing paper fixture quotation');
    // Exercise pagination with saved long labels and the existing contractual cover/closing.
    $payload = idlm_http_payload((int)$paper_quote->lead_id, (int)$paper_quote->id, (int)$paper_quote->current_revision_id);
    $payload['items'] = array();
    for ($i=1; $i<=60; $i++) {
        $row = idlm_http_payload((int)$paper_quote->lead_id)['items'][0];
        $row['description'] = sprintf('PAPER-ROW-%03d Custom fitted cabinetry with durable finishes and detailed installation specifications', $i);
        $payload['items'][] = $row;
    }
    idlm_http_check(idlm_http_save($payload)['status'] === 302, 'Multipage quotation save failed');
    $paper_quote = IDLM_Quotation_Repository::get_quote($paper_quote->id);
    foreach (array('A4'=>array(595.28,841.89),'Letter'=>array(612,792),'A3'=>array(841.89,1190.55),'Legal'=>array(612,1008)) as $paper_name=>$points) {
        idlm_http_case('T12-H-QUO009-settings-print-multipage-PDF-'.$paper_name, static function() use($paper_quote,$paper_name,$points): void {
            global $config,$sessions;
            $r = idlm_http_request('/wp-admin/admin-post.php', array('action'=>'idlm_save_quote_settings','paper_size'=>$paper_name,'_wpnonce'=>$sessions['admin']['quote_settings']), 'admin');
            idlm_http_check($r['status'] === 302, 'Settings save failed'); wp_cache_flush();
            idlm_http_check(get_option('idlm_quote_settings')['paper_size'] === $paper_name, 'Stored paper differs');
            foreach (array('/admin-workspace','/workspace') as $path) {
                $ui = idlm_http_request($path, array('workspace'=>'settings'), 'admin', false);
                idlm_http_check((bool)preg_match('/<option value="'. $paper_name .'"\s+selected=/', $ui['body']), 'Selected paper missing in '.$path);
            }
            idlm_paper_http_download($paper_quote, $paper_name, $points, strtolower($paper_name));
        });
    }
    foreach (array('missing'=>false,'empty'=>array(),'null'=>array('paper_size'=>null),'malformed'=>array('paper_size'=>array('Letter')),'lowercase'=>array('paper_size'=>'letter'),'invalid'=>array('paper_size'=>'A5')) as $case=>$option) {
        idlm_http_case('T12-H-QUO009-default-and-legacy-'.$case, static function() use($paper_quote,$case,$option): void {
            if ($option === false) { delete_option('idlm_quote_settings'); } else { update_option('idlm_quote_settings', $option, false); }
            $letter = $case === 'lowercase';
            idlm_paper_http_download($paper_quote, $letter ? 'Letter' : 'A4', $letter ? array(612,792) : array(595.28,841.89), $case);
            wp_cache_flush(); idlm_http_check(get_option('idlm_quote_settings') === $option, 'Reading migrated stored option');
        });
    }
    idlm_http_case('T12-H-QUO009-settings-deny-actor-and-invalid-nonce', static function(): void {
        global $sessions;
        $old = get_option('idlm_quote_settings');
        foreach (array(array('owner',$sessions['owner']['quote_settings']),array('admin','invalid')) as $request) {
            $r=idlm_http_request('/wp-admin/admin-post.php', array('action'=>'idlm_save_quote_settings','paper_size'=>'Letter','_wpnonce'=>$request[1]), $request[0]);
            idlm_http_check($r['status'] === 403, 'Unauthorized settings save allowed');
            wp_cache_flush(); idlm_http_check(get_option('idlm_quote_settings') === $old, 'Denied save changed settings');
        }
    });
} finally { update_option('idlm_quote_settings', $paper_old_settings, false); }
function idlm_paper_http_download(object $quote, string $paper, array $points, string $tag): void {
    global $sessions,$config;
    $before = idlm_http_snapshot();
    $args = array('quote_id'=>$quote->id,'revision_id'=>$quote->current_revision_id);
    $printed = idlm_http_request('/wp-admin/admin-post.php', $args + array('action'=>'idlm_print_quote','_wpnonce'=>$sessions['owner']['print']), 'owner', false);
    idlm_http_check($printed['status']===200 && strpos($printed['body'],'@page{size:'.$paper.';') !== false, 'Print paper mismatch');
    $pdf = idlm_http_request('/wp-admin/admin-post.php', $args + array('action'=>'idlm_download_quote_pdf','_wpnonce'=>$sessions['owner']['pdf']), 'owner', false);
    idlm_http_check($pdf['status']===200 && strpos($pdf['body'],'%PDF-') === 0 && stripos($pdf['headers'],'Content-Type: application/pdf') !== false, 'Invalid PDF download');
    idlm_http_check((bool)preg_match('/Content-Disposition: attachment;.*\.pdf/i', $pdf['headers']), 'PDF filename/header missing');
    $file=$config['content_dir'].'/paper-'.$tag.'.pdf'; file_put_contents($file,$pdf['body']);
    try {
        $info=idlm_paper_tool(getenv('IDLM_TEST_PDFINFO') ?: 'pdfinfo', array('-f','1','-l','1000',$file));
        preg_match('/Pages:\s+(\d+)/',$info,$pages); idlm_http_check((int)($pages[1] ?? 0)>=4, 'Expected cover, summary, multipage items and closing');
        preg_match_all('/(?:Page\s+\d+\s+size|Page size):\s+([\d.]+) x ([\d.]+) pts/',$info,$sizes,PREG_SET_ORDER);
        idlm_http_check(count($sizes)===(int)$pages[1], 'Missing page dimensions');
        foreach($sizes as $size){idlm_http_check(abs((float)$size[1]-$points[0])<.1 && abs((float)$size[2]-$points[1])<.1,'PDF page has wrong paper: '.$size[0]);}
        $text=idlm_paper_tool(getenv('IDLM_TEST_PDFTOTEXT') ?: 'pdftotext', array('-layout',$file,'-'));
        for($i=1;$i<=60;$i++){idlm_http_check(strpos($text,sprintf('PAPER-ROW-%03d',$i))!==false,'Missing line '.$i);}
        foreach(array('Paper test contractual cover','Paper test contractual closing','Paper test contractual footer','Paper test deposit') as $token){idlm_http_check(strpos($text,$token)!==false,'Missing saved term '.$token);}
        $csv=idlm_http_request('/wp-admin/admin-post.php',$args+array('action'=>'idlm_download_quote_csv','_wpnonce'=>$sessions['owner']['csv']),'owner',false);
        idlm_http_check($csv['status']===200 && substr($csv['body'],0,3)==="\xEF\xBB\xBF" && strpos($csv['body'],'PAPER-ROW-060')!==false,'Invalid CSV download');
        $stream=fopen('php://temp','r+');fwrite($stream,substr($csv['body'],3));rewind($stream);
        idlm_http_check(fgetcsv($stream,0,',','"','')===array('Line items'),'CSV header did not parse');fclose($stream);
        $xlsx=idlm_http_request('/wp-admin/admin-post.php',$args+array('action'=>'idlm_download_quote_xlsx','_wpnonce'=>$sessions['owner']['xlsx']),'owner',false);
        idlm_http_check($xlsx['status']===200 && substr($xlsx['body'],0,2)==='PK','Invalid XLSX download');
        $errors=is_file($config['content_dir'].'/php-errors.log') ? file_get_contents($config['content_dir'].'/php-errors.log') : '';
        idlm_http_check(trim($errors)==='', 'Download diagnostics: '.$errors);
        $evidence=getenv('IDLM_TEST_PAPER_EVIDENCE_DIR');
        if($evidence){file_put_contents($evidence.'/paper-'.$tag.'.pdf',$pdf['body']);file_put_contents($evidence.'/paper-'.$tag.'.html',$printed['body']);file_put_contents($evidence.'/paper-'.$tag.'.info',$info);file_put_contents($evidence.'/paper-'.$tag.'.txt',$text);}
    } finally { unlink($file); }
    idlm_http_check($before===idlm_http_snapshot(),'Exports changed quotation state');
}
