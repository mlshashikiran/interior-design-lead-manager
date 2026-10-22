<?php
/** Separate-process crypto/environment fixtures. Builder: Shashi. */
$mode=$argv[1]??'';
if($mode==='environment'){define('IDLM_META_ACCESS_TOKEN','synthetic-environment-token');}
if($mode==='no-key') {
    define('ABSPATH',__DIR__);function sanitize_key($value){return $value;}
    require dirname(__DIR__).'/includes/class-secret-store.php';
    if(IDLM_Secret_Store::set('idlm_meta_access_token','synthetic')!==false){exit(1);}
    echo "PASS INT010 no-key\n";exit(0);
}
require __DIR__.'/bootstrap.php';
idlm_test_reset_environment();
if($mode==='environment'){
    update_option('idlm_meta_access_token','synthetic-legacy');
    if(IDLM_Secret_Store::get('idlm_meta_access_token')!=='synthetic-environment-token'||IDLM_Secret_Store::set('idlm_meta_access_token','replacement')!==false||!IDLM_Secret_Store::migrate_platforms()||strpos(get_option('idlm_meta_access_token'),IDLM_Secret_Store::PREFIX)!==0){exit(1);}
}elseif($mode==='salt-rotation'){
    if(!IDLM_Secret_Store::set('idlm_meta_access_token','synthetic-salt-token')){exit(1);}
    $before=get_option('idlm_meta_access_token');$GLOBALS['idlm_test_salt']='rotated-salt-';
    if(IDLM_Secret_Store::get('idlm_meta_access_token')!==''||IDLM_Secret_Store::migrate_platforms()!==false||get_option('idlm_meta_access_token')!==$before){exit(1);}
}elseif($mode==='no-crypto'){
    update_option('idlm_meta_access_token','synthetic-legacy');
    if(IDLM_Secret_Store::set('idlm_meta_access_token','replacement')!==false||IDLM_Secret_Store::get('idlm_meta_access_token')!==''||get_option('idlm_meta_access_token')!=='synthetic-legacy'){exit(1);}
}else{exit(2);}
echo "PASS INT010 $mode\n";
