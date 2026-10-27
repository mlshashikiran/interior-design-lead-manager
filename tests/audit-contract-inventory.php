<?php
/** Audit-only contract discovery with WP doubles. Author: Shashi. */
require __DIR__ . '/bootstrap.php';
foreach (array('backup','capi','audiences','telemetry') as $file) require_once dirname(__DIR__) . '/includes/class-' . $file . '.php';
foreach (array('IDLM_Admin','IDLM_Auth','IDLM_Backup','IDLM_Integrations','IDLM_CAPI','IDLM_Interakt','IDLM_Scheduler','IDLM_Data_Retention','IDLM_Quotations','IDLM_Interakt_Batch') as $class) {
 if (is_callable(array($class,'init'))) $class::init();
}
IDLM_Integrations::register_webhook_routes();
IDLM_Interakt::register_webhook_route();
IDLM_Admin::register_admin_menus();
function audit_callback($callback) {
 $r = is_array($callback) ? new ReflectionMethod($callback[0],$callback[1]) : new ReflectionFunction($callback);
 return array('name'=>is_array($callback)?implode('::',$callback):(is_string($callback)?$callback:'closure'),'callable'=>is_callable($callback),'file'=>str_replace(dirname(__DIR__).'/','',$r->getFileName()),'line'=>$r->getStartLine());
}
$hooks = array();
foreach($GLOBALS['idlm_test_hooks'] as $h) { $h['callback']=audit_callback($h['callback']); $hooks[]=$h; }
$routes=array();
foreach($GLOBALS['idlm_test_routes'] as $route=>$handlers) {
 if(isset($handlers['callback'])) $handlers=array($handlers);
 foreach($handlers as $h) $routes[]=array('route'=>$route,'methods'=>$h['methods'],'callback'=>audit_callback($h['callback']),'permission'=>audit_callback($h['permission_callback']));
}
$menus=array();
foreach($GLOBALS['idlm_test_menu_calls'] as $m) {$a=$m['args'];$i=$m['type']==='menu'?4:5;$menus[]=array('type'=>$m['type'],'slug'=>$a[$m['type']==='menu'?3:4],'capability'=>$a[$m['type']==='menu'?2:3],'callback'=>audit_callback($a[$i]));}
echo json_encode(array('author'=>'Shashi','environment'=>'WordPress doubles; init callbacks registered but not dispatched','hooks'=>$hooks,'routes'=>$routes,'menus'=>$menus),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
