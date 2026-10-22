<?php
/** Publisher provisioning and retention. Builder: Shashi. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/Service.php';
try {
    $service=new \IDLM\Facebook\Service(require dirname(__DIR__).'/config.php');
    if(($argv[1]??'')==='provision' && isset($argv[2])){
        $result=$service->provision($argv[2]);
        echo "Add these installation-specific values to this customer's protected wp-config.php. Store them securely; do not include them in a public build or logs.\n";
        foreach(['IDLM_FACEBOOK_SERVICE_URL'=>$result['service_url'],'IDLM_FACEBOOK_SITE_ID'=>$result['site_id'],'IDLM_FACEBOOK_SITE_KEY'=>$result['key']] as $name=>$value){echo 'define('.var_export($name,true).', '.var_export($value,true).");\n";}
    }elseif(($argv[1]??'')==='disable' && isset($argv[2])){$service->disable_site($argv[2]);echo "Installation disabled.\n";}
    elseif(($argv[1]??'')==='prune'){$service->prune();echo "Expired temporary records pruned.\n";}
    else{fwrite(STDERR,"Usage: php bin/manage.php provision https://customer.example | disable SITE_ID | prune\n");exit(2);}
}catch(Throwable $error){fwrite(STDERR,"Service operation failed. Check protected server configuration.\n");exit(1);}
