<?php
/** INT-010 protected platform credentials. Builder: Shashi. */
idlm_test('INT010 failed plaintext migration refuses credential use and preserves value',static function():void {
    update_option('idlm_meta_access_token','synthetic-secret-010');
    $GLOBALS['idlm_test_option_write_failures']=array('idlm_meta_access_token');
    try { assert_same('',IDLM_Secret_Store::get('idlm_meta_access_token'));assert_same('synthetic-secret-010',get_option('idlm_meta_access_token')); }
    finally { $GLOBALS['idlm_test_option_write_failures']=array(); }
});
idlm_test('INT010 all platform values migrate and authenticated empty clear round-trips',static function():void {
    foreach(IDLM_Secret_Store::platform_options() as $name) {
        update_option($name,'synthetic-'.$name);
        assert_same('synthetic-'.$name,IDLM_Secret_Store::get($name,'',true));
        assert_true(strpos(get_option($name),IDLM_Secret_Store::PREFIX)===0);
        assert_false(strpos(get_option($name),'synthetic-')!==false);
        assert_true(IDLM_Secret_Store::set($name,''));assert_same('',IDLM_Secret_Store::get($name,'default',true));
    }
    assert_true(IDLM_Secret_Store::migrate_platforms());
});
idlm_test('INT010 ciphertext damage is refused and never replaced',static function():void {
    assert_true(IDLM_Secret_Store::set('idlm_meta_access_token','synthetic-damage-marker'));
    $damaged=IDLM_Secret_Store::PREFIX.base64_encode(str_repeat('x',48));update_option('idlm_meta_access_token',$damaged);
    assert_same('',IDLM_Secret_Store::get('idlm_meta_access_token','',true));assert_same($damaged,get_option('idlm_meta_access_token'));assert_false(IDLM_Secret_Store::migrate_platforms());
});
idlm_test('INT010 raw URL encoded Basic and JSON provider echoes are scrubbed',static function():void {
    $secret='synthetic&key/010+';
    foreach(array($secret,rawurlencode($secret),urlencode($secret),base64_encode($secret.':'),substr(json_encode($secret),1,-1)) as $echo){assert_false(strpos(IDLM_Secret_Store::redact('Echo '.$echo,array($secret)),$echo)!==false);}
    $result=IDLM_Data_Retention::redact(array('error'=>'https://www.google-analytics.com/mp/collect?api_secret=synthetic-secret&measurement_id=G-TEST','nested'=>array('authorization'=>'Bearer synthetic-token')));
    assert_false(strpos(json_encode($result),'synthetic-secret')!==false);assert_false(strpos(json_encode($result),'synthetic-token')!==false);
});
idlm_test('INT010 blank settings credentials stay hidden from administrators',static function():void {
    idlm_test_login(1,array('administrator'),array('manage_options'));
    foreach(IDLM_Secret_Store::platform_options() as $name){update_option($name,'synthetic-html');assert_same('',IDLM_Admin_Settings_Renderer::credential_value(substr($name,5)));}
});

idlm_test('INT010 crypto absence environment ownership salt rotation and missing key fail safely',static function():void {
    foreach(array('environment','no-crypto','salt-rotation','no-key') as $mode){
        $args=array(PHP_BINARY);if($mode==='no-crypto'){$args[]='-d';$args[]='disable_functions=openssl_encrypt,openssl_decrypt';}$args[]=__DIR__.'/secret-store-runtime.php';$args[]=$mode;
        $process=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);assert_true(is_resource($process));$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);assert_same(0,proc_close($process),$mode);assert_same('',$stderr);assert_contains('PASS INT010 '.$mode,$stdout);
    }
});
