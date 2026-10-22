<?php
/** Public service front controller. Builder: Shashi. */
declare(strict_types=1);
require dirname(__DIR__).'/src/Service.php';
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
try {
    $config=require dirname(__DIR__).'/config.php';
    $database=$config['database'];$parent=realpath(dirname($database));$webroot=realpath(__DIR__);
    if (!$parent || !$webroot || str_starts_with($parent.DIRECTORY_SEPARATOR,$webroot.DIRECTORY_SEPARATOR) || str_starts_with($database,__DIR__.DIRECTORY_SEPARATOR)) { throw new RuntimeException('Private database directory is required.'); }
    $body=file_get_contents('php://input',false,null,0,262145);
    $base=parse_url($config['base_url'],PHP_URL_PATH) ?: '';$path=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/';
    if($base!=='' && str_starts_with($path,rtrim($base,'/').'/')){$path=substr($path,strlen(rtrim($base,'/')));}
    $service=new \IDLM\Facebook\Service($config);
    $response=$service->handle($_SERVER['REQUEST_METHOD'] ?? '',$path,function_exists('getallheaders') ? getallheaders() : [],$_GET,is_string($body)?$body:'');
    http_response_code($response['status']);foreach($response['headers'] as $name=>$value){header($name.': '.$value);}echo $response['body'];
} catch (Throwable $error) { http_response_code(503);header('Content-Type: application/json');echo '{"error":"Connection service is not configured or is unavailable."}'; }
