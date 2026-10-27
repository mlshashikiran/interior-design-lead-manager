<?php
/** Publisher-hosted Facebook connection service. Builder: Shashi. PHP 8.1+. */
declare(strict_types=1);
namespace IDLM\Facebook;

final class Failure extends \RuntimeException {
    public function __construct(string $message, public int $status = 400) { parent::__construct($message); }
}

final class Service {
    private \PDO $db;
    private $transport;
    private $clock;
    private array $config;
    public function __construct(array $config, ?callable $transport = null, ?callable $clock = null) {
        foreach (array('app_id','app_secret','config_id','verify_token','base_url','database','encryption_key') as $key) {
            if (!is_string($config[$key] ?? null) || $config[$key] === '') { throw new Failure('Connection service is not configured.',503); }
        }
        if (!self::id($config['app_id']) || !self::id($config['config_id']) || strlen($config['encryption_key']) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES || !self::https($config['base_url'])) { throw new Failure('Invalid service configuration.',503); }
        $this->config=$config;$this->transport=$transport;$this->clock=$clock ?? static fn()=>time();
        $this->db=new \PDO('sqlite:'.$config['database'],null,null,array(\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC));
        $this->db->exec('PRAGMA busy_timeout=5000');$this->db->exec('PRAGMA journal_mode=WAL');
        $this->db->exec('CREATE TABLE IF NOT EXISTS sites (id TEXT PRIMARY KEY, origin TEXT NOT NULL, secret TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 1, epoch INTEGER NOT NULL DEFAULT 0)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS sessions (id TEXT PRIMARY KEY, site TEXT NOT NULL, state TEXT UNIQUE NOT NULL, claim TEXT NOT NULL, return_url TEXT NOT NULL, expires INTEGER NOT NULL, status TEXT NOT NULL, data TEXT NOT NULL, user_id TEXT NOT NULL DEFAULT \'\', epoch INTEGER NOT NULL DEFAULT 0)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS connections (site TEXT PRIMARY KEY, page TEXT UNIQUE NOT NULL, connection TEXT NOT NULL, user_id TEXT NOT NULL, data TEXT NOT NULL)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS events (id INTEGER PRIMARY KEY AUTOINCREMENT, site TEXT NOT NULL, connection TEXT NOT NULL, lead TEXT NOT NULL, form TEXT NOT NULL, page TEXT NOT NULL, created INTEGER NOT NULL, UNIQUE(site,connection,lead))');
        $this->db->exec('CREATE TABLE IF NOT EXISTS deletions (code TEXT PRIMARY KEY, expires INTEGER NOT NULL)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS nonces (site TEXT NOT NULL, nonce TEXT NOT NULL, expires INTEGER NOT NULL, PRIMARY KEY(site,nonce))');
    }
    private function now(): int { return (int)call_user_func($this->clock); }
    public static function id($value): string { return is_string($value) && preg_match('/^[1-9][0-9]{0,31}$/D',$value) ? $value : ''; }
    public static function https(string $url): bool { $p=parse_url($url);return !preg_match('/[\x00-\x20\x7f\\\\]/',$url) && is_array($p) && ($p['scheme']??'')==='https' && !empty($p['host']) && !isset($p['user']) && !isset($p['pass']) && !isset($p['fragment']); }
    private function seal(array $data): string { $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);return base64_encode($nonce.sodium_crypto_secretbox(json_encode($data,JSON_THROW_ON_ERROR),$nonce,$this->config['encryption_key'])); }
    private function open(string $value): array {
        $bytes=base64_decode($value,true);if ($bytes===false || strlen($bytes)<SODIUM_CRYPTO_SECRETBOX_NONCEBYTES+SODIUM_CRYPTO_SECRETBOX_MACBYTES) { throw new Failure('Stored connection is unreadable.',503); }
        $plain=sodium_crypto_secretbox_open(substr($bytes,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($bytes,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$this->config['encryption_key']);
        if ($plain===false) { throw new Failure('Stored connection is unreadable.',503); }
        return json_decode($plain,true,32,JSON_THROW_ON_ERROR);
    }
    private function sql(string $sql,array $args=[]): \PDOStatement { $q=$this->db->prepare($sql);$q->execute($args);return $q; }
    private function transaction(callable $work) { $this->db->exec('BEGIN IMMEDIATE');try{$r=$work();$this->db->exec('COMMIT');return $r;}catch(\Throwable $e){$this->db->exec('ROLLBACK');throw $e;} }
    /** Publisher provisioning, never a public registration API. Returns this installation's key once. */
    public function provision(string $origin): array {
        if (!self::https($origin) || isset(parse_url($origin)['query'])) { throw new Failure('Register a canonical HTTPS CRM URL.'); }
        $id=bin2hex(random_bytes(16));$secret=bin2hex(random_bytes(32));
        $this->sql('INSERT INTO sites(id,origin,secret) VALUES(?,?,?)',[$id,rtrim($origin,'/'),$this->seal(['key'=>$secret])]);
        return ['site_id'=>$id,'key'=>$secret,'service_url'=>rtrim($this->config['base_url'],'/')];
    }
    public function disable_site(string $id): void {
        $this->transaction(function()use($id){$this->sql('UPDATE sites SET enabled=0 WHERE id=?',[$id]);$this->sql('DELETE FROM sessions WHERE site=?',[$id]);$this->sql('DELETE FROM connections WHERE site=?',[$id]);$this->sql('DELETE FROM events WHERE site=?',[$id]);});
    }
    private function site(string $id): array { $row=$this->sql('SELECT * FROM sites WHERE id=? AND enabled=1',[$id])->fetch();if(!$row){throw new Failure('Installation is not authorized.',401);}return $row; }
    private function meta(string $path,array $query=[],string $token='',string $method='GET'): array {
        if (!preg_match('#^(?:oauth/access_token|me(?:/accounts)?|[1-9][0-9]{0,31}(?:/(?:leadgen_forms|leads|subscribed_apps))?)$#D',$path)) { throw new Failure('Unsupported Meta resource.'); }
        if ($token!=='') { $query['appsecret_proof']=hash_hmac('sha256',$token,$this->config['app_secret']); }
        if ($this->transport) { $result=call_user_func($this->transport,$path,$query,$token,$method); }
        else {
            $url='https://graph.facebook.com/v25.0/'.$path;
            $headers=['Accept: application/json'];if($token!==''){$headers[]='Authorization: Bearer '.$token;}
            if($method==='GET' && $query){$url.='?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);}
            $ch=curl_init($url);$body='';
            curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>$headers,CURLOPT_WRITEFUNCTION=>static function($curl,$part)use(&$body){if(strlen($body)+strlen($part)>1048576){return 0;}$body.=$part;return strlen($part);}]);
            if($method!=='GET'){curl_setopt($ch,CURLOPT_CUSTOMREQUEST,$method);curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($query,'','&',PHP_QUERY_RFC3986));}
            $ok=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
            $result=$ok!==false && $code>=200 && $code<300 ? json_decode($body,true,32) : null;
        }
        if(!is_array($result) || isset($result['error'])){throw new Failure('Facebook access failed. Reconnect or check the granted permissions.',502);}return $result;
    }
    private function respond(int $status,array $data,array $headers=[]): array { return ['status'=>$status,'headers'=>array_merge(['Content-Type'=>'application/json','Cache-Control'=>'no-store','Referrer-Policy'=>'no-referrer'],$headers),'body'=>json_encode($data,JSON_THROW_ON_ERROR)]; }
    public function handle(string $method,string $path,array $headers,array $query,string $body): array {
        try {
            if(strlen($body)>262144){throw new Failure('Request too large.',413);}
            if($path==='/api' && $method==='POST'){return $this->api(array_change_key_case($headers,CASE_LOWER),$body);}
            if($path==='/oauth/callback' && $method==='GET'){return $this->callback($query);}
            if($path==='/meta/webhook'){return $this->webhook($method,array_change_key_case($headers,CASE_LOWER),$query,$body);}
            if(in_array($path,['/meta/deauthorize','/meta/delete-data'],true) && $method==='POST'){return $this->revoked($path,$body);}
            if($path==='/deletion/status' && $method==='GET'){
                $code=$query['code']??'';
                if(!is_string($code)||!preg_match('/^[a-f0-9]{32}$/D',$code)||!$this->sql('SELECT code FROM deletions WHERE code=? AND expires>=?',[hash('sha256',$code),$this->now()])->fetch()){throw new Failure('Deletion receipt not found.',404);}
                return $this->respond(200,['status'=>'User connection data has been removed.']);
            }
            throw new Failure('Not found.',404);
        }catch(Failure $e){return $this->respond($e->status,['error'=>$e->getMessage()]);}
        catch(\Throwable $e){return $this->respond(503,['error'=>'Connection service is temporarily unavailable.']);}
    }
    private function api(array $headers,string $body): array {
        $id=$headers['x-idlm-site']??'';$stamp=$headers['x-idlm-time']??'';$nonce=$headers['x-idlm-nonce']??'';$signature=$headers['x-idlm-signature']??'';
        if(!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D',$id) || !ctype_digit($stamp) || abs($this->now()-(int)$stamp)>300 || !preg_match('/^[a-f0-9]{32}$/D',$nonce)){throw new Failure('Invalid request authentication.',401);}
        $site=$this->site($id);$secret=$this->open($site['secret'])['key'];
        if(!hash_equals(hash_hmac('sha256',$stamp."\n".$nonce."\n".$body,$secret),$signature)){throw new Failure('Invalid request signature.',401);}
        try{$this->transaction(function()use($id,$nonce){$this->sql('DELETE FROM nonces WHERE expires<?',[$this->now()]);$this->sql('INSERT INTO nonces VALUES(?,?,?)',[$id,$nonce,$this->now()+600]);});}catch(\PDOException $e){throw new Failure('Request was already used.',409);}
        try{
            $request=json_decode($body,true,32,JSON_THROW_ON_ERROR);if(!is_array($request)||!is_string($request['action']??null)||!is_array($request['data']??null)){throw new Failure('Invalid request.');}
            $result=$this->action($site,$request['action'],$request['data']);$response=$this->respond(200,$result);
        }catch(Failure $e){$response=$this->respond($e->status,['error'=>$e->getMessage()]);}
        catch(\Throwable $e){$response=$this->respond(503,['error'=>'Connection service is temporarily unavailable.']);}
        $response['headers']['X-IDLM-Signature']=hash_hmac('sha256',$nonce."\n".$response['status']."\n".$response['body'],$secret);
        return $response;
    }
    private function session(array $site,array $data,bool $ready=true): array {
        $id=$data['session']??'';$claim=$data['claim']??'';
        if(!is_string($id)||!is_string($claim)){throw new Failure('Invalid connection session.',403);}
        $row=$this->sql('SELECT * FROM sessions WHERE id=? AND site=?',[$id,$site['id']])->fetch();
        if(!$row || (int)$row['expires']<$this->now() || !hash_equals($row['claim'],hash('sha256',$claim))){throw new Failure('Connection session expired. Connect Facebook again.',403);}
        if($ready && $row['status']!=='ready'){throw new Failure($row['status']==='denied'?'Facebook permission was declined. Connect again.':'Facebook authorization is not complete.',409);}
        $row['decoded']=$this->open($row['data']);return $row;
    }
    private function persist_session(array $row): void { $this->sql('UPDATE sessions SET data=? WHERE id=?',[$this->seal($row['decoded']),$row['id']]); }
    private function cursor(array $data): array { $cursor=$data['after']??'';if(!is_string($cursor)||strlen($cursor)>2048){throw new Failure('Invalid page cursor.');}return $cursor!==''?['after'=>$cursor]:[]; }
    private function next(array $result): string { $after=$result['paging']['cursors']['after']??'';return !empty($result['paging']['next']) && is_string($after) && strlen($after)<=2048 ? $after : ''; }
    private function selection(array $connection,array $saved): array {
        return ['connected'=>true,'connection'=>$connection['connection'],'page_id'=>$connection['page'],'page_name'=>$saved['page_name'],'forms'=>$saved['forms'],'form_names'=>$saved['form_names'],'app_id'=>$this->config['app_id'],'started_at'=>$saved['started_at']];
    }
    private function action(array $site,string $action,array $data): array {
        if($action==='begin'){
            $id=$data['session']??'';$claim=$data['claim']??'';$return=$data['return_url']??'';
            if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id)||!is_string($claim)||!preg_match('/^[a-f0-9]{64}$/D',$claim)||!is_string($return)||!self::https($return)||!self::same_origin($site['origin'],$return)){throw new Failure('Invalid connection request.');}
            $state=bin2hex(random_bytes(32));
            $this->sql('DELETE FROM sessions WHERE expires<?',[$this->now()]);
            $this->transaction(function()use($id,$site,$state,$claim,$return){
                $current=$this->site($site['id']);$epoch=(int)$current['epoch']+1;
                $this->sql('UPDATE sites SET epoch=? WHERE id=?',[$epoch,$site['id']]);
                $this->sql('DELETE FROM sessions WHERE site=?',[$site['id']]);
                $this->sql('INSERT INTO sessions(id,site,state,claim,return_url,expires,status,data,epoch) VALUES(?,?,?,?,?,?,?,?,?)',[$id,$site['id'],hash('sha256',$state),hash('sha256',$claim),$return,$this->now()+900,'pending',$this->seal([]),$epoch]);
            });
            $url='https://www.facebook.com/v25.0/dialog/oauth?'.http_build_query(['client_id'=>$this->config['app_id'],'redirect_uri'=>rtrim($this->config['base_url'],'/').'/oauth/callback','config_id'=>$this->config['config_id'],'response_type'=>'code','state'=>$state],'','&',PHP_QUERY_RFC3986);
            return ['authorization_url'=>$url];
        }
        if($action==='pages'){
            $row=$this->session($site,$data);$decoded=&$row['decoded'];
            $result=$this->meta('me/accounts',array_merge(['fields'=>'id,name,access_token','limit'=>50],$this->cursor($data)),$decoded['user_token']);$pages=[];
            foreach($result['data']??[] as $page){if(!self::id($page['id']??null)||!is_string($page['name']??null)||!is_string($page['access_token']??null)){continue;}$decoded['pages'][$page['id']]=['name'=>$page['name'],'token'=>$page['access_token']];$pages[]=['id'=>$page['id'],'name'=>$page['name']];}
            if(count($decoded['pages']??[])>500){throw new Failure('Too many Pages. Narrow the Pages granted in Facebook.');}$this->persist_session($row);return ['pages'=>$pages,'after'=>$this->next($result)];
        }
        if($action==='forms'){
            $row=$this->session($site,$data);$page=self::id($data['page_id']??null);$saved=$row['decoded']['pages'][$page]??null;if(!$saved){throw new Failure('Choose a Page from this connection.',403);}
            $result=$this->meta($page.'/leadgen_forms',array_merge(['fields'=>'id,name,status','limit'=>50],$this->cursor($data)),$saved['token']);$forms=[];
            foreach($result['data']??[] as $form){if(!self::id($form['id']??null)||!is_string($form['name']??null)||($form['status']??'')!=='ACTIVE'){continue;}$row['decoded']['forms'][$page][$form['id']]=$form['name'];$forms[]=['id'=>$form['id'],'name'=>$form['name']];}
            if(count($row['decoded']['forms'][$page]??[])>1000){throw new Failure('Too many forms. Contact the service owner.');}$this->persist_session($row);return ['forms'=>$forms,'after'=>$this->next($result)];
        }
        if($action==='select'){
            $previous=$this->sql('SELECT * FROM connections WHERE site=?',[$site['id']])->fetch();
            if($previous){
                $prior=$this->open($previous['data']);
                if(($prior['selected_session']??'')===($data['session']??'') && is_string($data['claim']??null) && hash_equals($prior['selected_claim']??'',hash('sha256',$data['claim']))) {
                    if(($previous['page']??'')!==($data['page_id']??'') || $prior['forms']!==($data['forms']??null)){throw new Failure('Selection changed. Connect again.',409);}
                    return $this->selection($previous,$prior);
                }
            }
            $row=$this->session($site,$data);$page=self::id($data['page_id']??null);$forms=$data['forms']??null;
            if(!$page||!is_array($forms)||count($forms)<1||count($forms)>20||array_keys($forms)!==range(0,count($forms)-1)||count(array_unique($forms,SORT_REGULAR))!==count($forms)){throw new Failure('Select between one and twenty individual forms.');}
            $page_data=$row['decoded']['pages'][$page]??null;$names=[];
            foreach($forms as $form){if(!self::id($form)||!isset($row['decoded']['forms'][$page][$form])){throw new Failure('Choose forms returned for this Page.',403);}$names[$form]=$row['decoded']['forms'][$page][$form];}
            if(!$page_data){throw new Failure('Page is not authorized.',403);}
            // Check ownership before mutating the Page subscription; transaction rechecks after HTTP.
            $owner=$this->sql('SELECT site FROM connections WHERE page=?',[$page])->fetchColumn();if($owner && $owner!==$site['id']){throw new Failure('This Page is already connected to another CRM installation.',409);}
            $test=$this->meta($forms[0].'/leads',['fields'=>'id','limit'=>1],$page_data['token']);
            $subscription=$this->meta($page.'/subscribed_apps',['subscribed_fields'=>'leadgen'],$page_data['token'],'POST');if(($subscription['success']??false)!==true){throw new Failure('Facebook did not confirm the Page subscription.',502);}
            $connection=bin2hex(random_bytes(16));$saved=['token'=>$page_data['token'],'page_name'=>$page_data['name'],'forms'=>$forms,'form_names'=>$names,'started_at'=>$this->now(),'selected_session'=>$row['id'],'selected_claim'=>$row['claim']];
            $committed=$this->transaction(function()use($site,$page,$row,$connection,$saved){
                $live=$this->site($site['id']);if((int)$live['epoch']!==(int)$row['epoch']){throw new Failure('Connection was cancelled or replaced. Connect again.',409);}
                $current=$this->sql('SELECT * FROM connections WHERE site=?',[$site['id']])->fetch();
                if($current){$decoded=$this->open($current['data']);if(($decoded['selected_session']??'')===$row['id']){return $this->selection($current,$decoded);}}
                $owner=$this->sql('SELECT site FROM connections WHERE page=?',[$page])->fetchColumn();if($owner && $owner!==$site['id']){throw new Failure('Page is already connected to another CRM.',409);}
                $this->sql('INSERT INTO connections VALUES(?,?,?,?,?) ON CONFLICT(site) DO UPDATE SET page=excluded.page,connection=excluded.connection,user_id=excluded.user_id,data=excluded.data',[$site['id'],$page,$connection,$row['decoded']['user_id'],$this->seal($saved)]);
                $this->sql('DELETE FROM events WHERE site=?',[$site['id']]);$this->sql('DELETE FROM sessions WHERE id=?',[$row['id']]);
                return $this->selection(['connection'=>$connection,'page'=>$page],$saved);
            });
            return $committed;
        }
        if($action==='disconnect'){
            $this->transaction(function()use($site){$this->sql('UPDATE sites SET epoch=epoch+1 WHERE id=?',[$site['id']]);$this->sql('DELETE FROM connections WHERE site=?',[$site['id']]);$this->sql('DELETE FROM sessions WHERE site=?',[$site['id']]);$this->sql('DELETE FROM events WHERE site=?',[$site['id']]);});return ['disconnected'=>true];
        }
        $connection=$this->sql('SELECT * FROM connections WHERE site=?',[$site['id']])->fetch();
        if(!$connection){throw new Failure('Facebook is disconnected. Connect again.',409);}
        $saved=$this->open($connection['data']);
        if($action==='status'){return $this->selection($connection,$saved);}
        if(!is_string($data['connection']??null)||!hash_equals($connection['connection'],$data['connection'])){throw new Failure('Connection changed. Reload the settings page.',409);}
        if($action==='check'){
            $this->meta($saved['forms'][0].'/leads',['fields'=>'id','limit'=>1],$saved['token']);
            $subscriptions=$this->meta($connection['page'].'/subscribed_apps',['fields'=>'id,subscribed_fields','limit'=>100],$saved['token']);
            foreach($subscriptions['data']??[] as $app){if(($app['id']??'')===$this->config['app_id'] && in_array('leadgen',$app['subscribed_fields']??[],true)){return ['verified'=>true];}}
            throw new Failure('Facebook Page subscription was removed. Connect again.',409);
        }
        if($action==='events'){
            $events=$this->sql('SELECT id,lead,form,page FROM events WHERE site=? AND connection=? ORDER BY id LIMIT 25',[$site['id'],$connection['connection']])->fetchAll();return ['events'=>$events];
        }
        if($action==='ack'){
            $ids=$data['ids']??[];if(!is_array($ids)||count($ids)>25){throw new Failure('Invalid acknowledgment.');}
            foreach($ids as $id){if(!is_int($id)||$id<1){throw new Failure('Invalid event ID.');}}
            $this->transaction(function()use($ids,$site,$connection){foreach($ids as $id){$this->sql('DELETE FROM events WHERE id=? AND site=? AND connection=?',[$id,$site['id'],$connection['connection']]);}});return ['acknowledged'=>count($ids)];
        }
        if($action==='graph'){
            $path=$data['path']??'';$query=$data['query']??[];
            if(!is_string($path)||!is_array($query)||array_diff(array_keys($query),['fields','limit','filtering','after'])){throw new Failure('Unsupported lead query.');}
            if(isset($query['after'])&&(!is_string($query['after'])||strlen($query['after'])>2048)){throw new Failure('Invalid cursor.');}
            if(in_array($path,array_map(static fn($form)=>$form.'/leads',$saved['forms']),true)){
                if(($query['fields']??'')!=='id'||(int)($query['limit']??0)>25||(int)($query['limit']??0)<1){throw new Failure('Unsupported lead-list query.');}
                $result=$this->meta($path,$query,$saved['token']);$leads=[];
                foreach($result['data']??[] as $lead){if(self::id($lead['id']??null)){$leads[]=['id'=>$lead['id']];}}
                $after=$this->next($result);$response=['data'=>$leads];
                // Meta pagination URLs can contain tokens. Return only a cursor and a boolean.
                if($after!==''){$response['paging']=['cursors'=>['after'=>$after],'next'=>true];}
                return $response;
            }
            $lead_fields=['id,created_time,form_id,ad_id,field_data','id,created_time,form_id,ad_id,field_data,ad_name,adset_id,adset_name,campaign_id,campaign_name,is_organic,platform,partner_name','id,created_time,form_id,ad_id,field_data,ad_name,adset_id,adset_name,campaign_id,campaign_name,is_organic,platform,partner_name,custom_disclaimer_responses,home_listing,post,post_submission_check_result,retailer_item_id,vehicle'];
            if(self::id($path) && in_array($query['fields']??'',$lead_fields,true)){
                $lead=$this->meta($path,$query,$saved['token']);if(($lead['id']??'')!==$path||!in_array($lead['form_id']??'',$saved['forms'],true)){throw new Failure('Lead does not belong to the selected forms.',403);}return array_intersect_key($lead,array_flip(explode(',',$query['fields'])));
            }
            throw new Failure('Resource is outside the connected forms.',403);
        }
        throw new Failure('Unknown action.');
    }
    private static function same_origin(string $a,string $b): bool { $x=parse_url($a);$y=parse_url($b);return strtolower($x['host']??'')===strtolower($y['host']??'') && ($x['scheme']??'')===($y['scheme']??'') && ($x['port']??443)===($y['port']??443) && str_starts_with($y['path']??'/',rtrim($x['path']??'','/').'/'); }
    private function callback(array $query): array {
        $state=$query['state']??'';if(!is_string($state)||!preg_match('/^[a-f0-9]{64}$/D',$state)){throw new Failure('Invalid authorization state.',403);}
        $row=$this->transaction(function()use($state){$row=$this->sql('SELECT * FROM sessions WHERE state=?',[hash('sha256',$state)])->fetch();if(!$row||$row['status']!=='pending'||(int)$row['expires']<$this->now()){throw new Failure('Authorization state expired or was already used.',403);}$this->site($row['site']);$this->sql("UPDATE sessions SET status='processing' WHERE id=?",[$row['id']]);return $row;});
        if(isset($query['error'])){$this->sql("UPDATE sessions SET status='denied' WHERE id=?",[$row['id']]);return ['status'=>303,'headers'=>['Location'=>$row['return_url'],'Cache-Control'=>'no-store','Referrer-Policy'=>'no-referrer'],'body'=>''];}
        $code=$query['code']??'';if(!is_string($code)||$code===''||strlen($code)>4096){throw new Failure('Missing authorization code.');}
        try{
            $result=$this->meta('oauth/access_token',['client_id'=>$this->config['app_id'],'client_secret'=>$this->config['app_secret'],'redirect_uri'=>rtrim($this->config['base_url'],'/').'/oauth/callback','code'=>$code],'','POST');
            if(!is_string($result['access_token']??null)||$result['access_token']===''){throw new Failure('Facebook did not return an access token.',502);}
            $extended=$this->meta('oauth/access_token',['grant_type'=>'fb_exchange_token','client_id'=>$this->config['app_id'],'client_secret'=>$this->config['app_secret'],'fb_exchange_token'=>$result['access_token']],'','POST');
            if(!is_string($extended['access_token']??null)||$extended['access_token']===''){throw new Failure('Facebook token extension failed.',502);}
            $result=$extended;
            $identity=$this->meta('me',['fields'=>'id'],$result['access_token']);if(!self::id($identity['id']??null)){throw new Failure('Facebook identity could not be verified.',502);}
            $this->sql("UPDATE sessions SET status='ready',data=?,user_id=? WHERE id=?",[$this->seal(['user_token'=>$result['access_token'],'user_id'=>$identity['id'],'pages'=>[],'forms'=>[]]),$identity['id'],$row['id']]);
        }catch(\Throwable $e){$this->sql("UPDATE sessions SET status='failed' WHERE id=?",[$row['id']]);throw new Failure('Facebook authorization failed. Return to the CRM and connect again.',502);}
        return ['status'=>303,'headers'=>['Location'=>$row['return_url'],'Cache-Control'=>'no-store','Referrer-Policy'=>'no-referrer'],'body'=>''];
    }
    private function webhook(string $method,array $headers,array $query,string $body): array {
        if($method==='GET'){
            $mode=$query['hub_mode']??($query['hub.mode']??'');$token=$query['hub_verify_token']??($query['hub.verify_token']??'');$challenge=$query['hub_challenge']??($query['hub.challenge']??'');
            if(!is_string($mode)||$mode!=='subscribe'||!is_string($token)||!hash_equals($this->config['verify_token'],$token)||!is_string($challenge)||!ctype_digit($challenge)){throw new Failure('Forbidden.',403);}return ['status'=>200,'headers'=>['Content-Type'=>'text/plain','Cache-Control'=>'no-store'],'body'=>$challenge];
        }
        if($method!=='POST'||!hash_equals('sha256='.hash_hmac('sha256',$body,$this->config['app_secret']),$headers['x-hub-signature-256']??'')){throw new Failure('Invalid webhook signature.',401);}
        $data=json_decode($body,true,32);if(!is_array($data)||($data['object']??'')!=='page'||!is_array($data['entry']??null)){throw new Failure('Invalid webhook.');}$events=[];
        foreach($data['entry'] as $entry){if(!is_array($entry)||!is_array($entry['changes']??null)){throw new Failure('Invalid entry.');}foreach($entry['changes'] as $change){if(($change['field']??'')!=='leadgen'){continue;}$v=$change['value']??[];if(!self::id($v['page_id']??null)||($entry['id']??'')!==$v['page_id']||!self::id($v['form_id']??null)||!self::id($v['leadgen_id']??null)){throw new Failure('Invalid lead identity.');}$events[]=$v;if(count($events)>100){throw new Failure('Batch too large.',413);}}}
        $this->transaction(function()use($events){foreach($events as $v){$row=$this->sql('SELECT * FROM connections WHERE page=?',[$v['page_id']])->fetch();if(!$row){continue;}$saved=$this->open($row['data']);if(!in_array($v['form_id'],$saved['forms'],true)){continue;}$this->sql('INSERT OR IGNORE INTO events(site,connection,lead,form,page,created) VALUES(?,?,?,?,?,?)',[$row['site'],$row['connection'],$v['leadgen_id'],$v['form_id'],$v['page_id'],$this->now()]);}});return $this->respond(200,['accepted'=>true]);
    }
    private function revoked(string $path,string $body): array {
        parse_str($body,$input);$signed=$input['signed_request']??'';if(!is_string($signed)||substr_count($signed,'.')!==1){throw new Failure('Invalid signed request.',401);}[$sig,$payload]=explode('.',$signed,2);
        $decode=static fn($x)=>base64_decode(strtr($x,'-_','+/'),true);$signature=$decode($sig);$data=json_decode($decode($payload)?:'',true);
        if(!is_array($data)||($data['algorithm']??'')!=='HMAC-SHA256'||!is_string($signature)||!hash_equals(hash_hmac('sha256',$payload,$this->config['app_secret'],true),$signature)||!self::id($data['user_id']??null)){throw new Failure('Invalid signed request.',401);}
        $this->transaction(function()use($data){$sites=$this->sql('SELECT site FROM connections WHERE user_id=?',[$data['user_id']])->fetchAll(\PDO::FETCH_COLUMN);foreach($sites as $site){$this->sql('DELETE FROM events WHERE site=?',[$site]);}$this->sql('DELETE FROM connections WHERE user_id=?',[$data['user_id']]);$this->sql('DELETE FROM sessions WHERE user_id=?',[$data['user_id']]);});
        $code=bin2hex(random_bytes(16));
        if($path==='/meta/delete-data'){$this->sql('INSERT INTO deletions(code,expires) VALUES(?,?)',[hash('sha256',$code),$this->now()+30*86400]);}
        return $this->respond(200,$path==='/meta/delete-data'?['url'=>rtrim($this->config['base_url'],'/').'/deletion/status?code='.$code,'confirmation_code'=>$code]:['removed'=>true]);
    }
    public function prune(): void { $this->sql('DELETE FROM deletions WHERE expires<?',[$this->now()]);$this->sql('DELETE FROM sessions WHERE expires<?',[$this->now()]);$this->sql('DELETE FROM nonces WHERE expires<?',[$this->now()]);$this->sql('DELETE FROM events WHERE created<?',[$this->now()-7*86400]); }
}
