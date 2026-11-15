<?php
declare(strict_types=1);
// API JSON de l'application mobile DOSSIERUrba. Documentation : docs/api.md
// Appel : /api/<route> (réécrit par .htaccess) ou api.php?r=<route>
define('API',true);
require __DIR__.'/lib.php';

function out($data,int $code=200){http_response_code($code);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function fail(string $msg,int $code=400,string $err=''){out(['ok'=>false,'error'=>$msg]+($err?['code'=>$err]:[]),$code);}
set_exception_handler(function($x){error_log((string)$x);fail('Erreur interne. Réessayez dans un instant.',500);});

// CORS : l'application Capacitor s'exécute depuis https://localhost (Android) ou capacitor://localhost (iPhone)
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
$allowed=array_merge(['https://localhost','http://localhost','capacitor://localhost','ionic://localhost'],array_filter(array_map('trim',explode(',',setting('api_origins','')))));
if($origin!==''&&in_array($origin,$allowed,true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Headers: Authorization, Content-Type');header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');header('Access-Control-Expose-Headers: Content-Disposition');header('Access-Control-Max-Age: 86400');}
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}

function body():array{static $b=null;if($b===null){$ct=(string)($_SERVER['CONTENT_TYPE']??'');$b=str_contains($ct,'application/json')?(json_decode((string)file_get_contents('php://input'),true)?:[]):$_POST;}return $b;}
function in(string $k,$def=''){$v=body()[$k]??$def;return is_scalar($v)?$v:$def;}
function yes(string $k):bool{$v=body()[$k]??false;return $v===true||$v==='1'||$v===1||$v==='true'||$v==='on';}

/* ---------- Jetons ---------- */
function newToken(int $uid,string $device):string{$t=bin2hex(random_bytes(32));
 db()->prepare('INSERT INTO api_tokens(user_id,hash,device,created_at,last_used,expires_at) VALUES(?,?,?,NOW(),NOW(),DATE_ADD(NOW(),INTERVAL 90 DAY))')->execute([$uid,hash('sha256',$t),mb_substr($device,0,100)]);return $t;}
function bearer():string{$h=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
 if($h===''&&function_exists('getallheaders')){$hs=array_change_key_case(getallheaders());$h=(string)($hs['authorization']??'');}
 return preg_match('/^Bearer\s+([a-f0-9]{64})$/i',trim($h),$m)?strtolower($m[1]):'';}
// Utilisateur connecté. $consent : refuse l'accès tant que les conditions en vigueur ne sont pas acceptées.
function auth(bool $consent=true):array{$t=bearer();if($t==='')fail('Authentification requise.',401,'auth_required');
 $s=db()->prepare('SELECT * FROM api_tokens WHERE hash=? AND expires_at>NOW()');$s->execute([hash('sha256',$t)]);$k=$s->fetch();if(!$k)fail('Session expirée : reconnectez-vous.',401,'auth_required');
 db()->prepare('UPDATE api_tokens SET last_used=NOW(),expires_at=DATE_ADD(NOW(),INTERVAL 90 DAY) WHERE id=?')->execute([$k['id']]);
 $GLOBALS['API_UID']=(int)$k['user_id'];$GLOBALS['API_TOKEN']=(int)$k['id'];$u=me();if(!$u)fail('Compte introuvable.',401,'auth_required');
 if($consent&&needsConsent($u))fail('Vous devez accepter les conditions d’utilisation en vigueur.',403,'consent_required');
 return $u;}

/* ---------- Mise en forme des réponses ---------- */
function userOut(array $u):array{return ['id'=>(int)$u['id'],'name'=>$u['name'],'email'=>$u['email'],'phone'=>$u['phone']??null,'role'=>$u['role'],'created_at'=>$u['created_at'],
 'consent'=>['version'=>$u['consent_version'],'current'=>legalVersion(),'required'=>needsConsent($u),'analytics'=>(bool)$u['opt_analytics'],'marketing'=>(bool)$u['opt_marketing']],'access'=>access($u)];}
const STATUS_LABELS=['brouillon'=>'Brouillon','en_attente'=>'En attente de paiement','paye'=>'Débloqué'];
function dossierOut(array $d,bool $full=false):array{$ty=ty($d);$p=pieces((int)$d['id']);$req=reqImg($ty);
 $o=['id'=>(int)$d['id'],'type'=>$ty,'type_label'=>TYPES[$ty][0],'titre'=>$d['titre'],'adresse'=>$d['adresse'],'parcelle'=>$d['parcelle'],'status'=>$d['status'],'status_label'=>STATUS_LABELS[$d['status']]??$d['status'],
  'unlocked_via'=>$d['unlocked_via']??null,'created_at'=>$d['created_at'],'progress'=>['done'=>count(array_filter($req,fn($c)=>!empty($p[$c]['sheet']))),'required'=>count($req)]];
 if(!$full)return $o;
 $o['description']=(string)$d['description'];$o['cerfa']=['numero'=>CERFA[$ty][0],'url'=>CERFA[$ty][1]];$o['head']=HEAD[$ty];$o['cover']=['code'=>$ty.'0','done'=>!empty($p[$ty.'0']['sheet'])];
 $o['pieces']=[];foreach(steps($ty) as $c=>[$t,$k,$h,$opt])$o['pieces'][]=['code'=>$c,'title'=>$t,'kind'=>$k,'help'=>$h,'optional'=>$opt,'done'=>!empty($p[$c]['sheet']),'has_base'=>!empty($p[$c]['file'])];
 $o['missing']=array_values(array_diff(missing($d,$p),['PDF']));$o['price']=dossierPrice($d);return $o;}
function apiDossier(array $u,int $id):array{$s=db()->prepare('SELECT * FROM dossiers WHERE id=?');$s->execute([$id]);$d=$s->fetch();
 if(!$d||($d['user_id']!=$u['id']&&!in_array($u['role'],['gestionnaire','admin'],true)))fail('Dossier introuvable.',404);return $d;}

/* ---------- Routes ---------- */
$m=$_SERVER['REQUEST_METHOD'];$r=trim((string)($_GET['r']??''),'/');$seg=$r===''?[]:explode('/',$r);$route=$m.' '.preg_replace('#/\d+(?=/|$)#','/{id}',$r);
$route=preg_replace('#^(\w+ dossiers/\{id\}/pieces)/[^/]+(/(sheet|base))?$#','$1/{code}',$route);

switch($route){
// Configuration publique : types de dossiers, pièces, Cerfa, formule d'accès, versions minimales
case 'GET config':
 $types=[];foreach(TYPES as $k=>[$l,$ok,$why])$types[]=['code'=>$k,'label'=>$l,'available'=>$ok,'note'=>$why,'head'=>HEAD[$k]??null,'cerfa'=>isset(CERFA[$k])?['numero'=>CERFA[$k][0],'url'=>CERFA[$k][1]]:null,
  'pieces'=>isset(STEPS[$k])?array_map(fn($c,$s)=>['code'=>$c,'title'=>$s[0],'kind'=>$s[1],'help'=>$s[2],'optional'=>$s[3]],array_keys(STEPS[$k]),STEPS[$k]):[]];
 $base=(empty($_SERVER['HTTPS'])?'http':'https').'://'.($_SERVER['HTTP_HOST']??'').rtrim(dirname($_SERVER['SCRIPT_NAME']??'/'),'/\\').'/';
 out(['ok'=>true,'app'=>APP,'zone_url'=>ZONE,'types'=>$types,'emojis'=>emojis(),'access'=>['mode'=>accMode(),'label'=>MODES[accMode()]],
  'legal'=>['version'=>legalVersion(),'cgu_url'=>$base.'index.php?a=cgu','privacy_url'=>$base.'index.php?a=privacy','delete_url'=>$base.'index.php?a=suppression'],
  'app_min'=>['android'=>acc('app_min_android'),'ios'=>acc('app_min_ios')],'message'=>acc('app_message')]);
case 'GET legal/cgu':case 'GET legal/privacy':$k=$seg[1];out(['ok'=>true,'version'=>legalVersion(),'title'=>LEGAL_TITLES[$k],'text'=>legalText($k),'html'=>legalHtml(legalText($k))]);

// Compte
case 'POST auth/register':
 $rk='reg|'.($_SERVER['REMOTE_ADDR']??'');failed($rk);if(throttled($rk))fail('Trop de tentatives : réessayez dans 15 minutes.',429);
 $em=strtolower(trim((string)in('email')));$nm=trim((string)in('name'));$pw=(string)in('password');
 if(!filter_var($em,FILTER_VALIDATE_EMAIL)||strlen($pw)<10||$nm==='')fail('E-mail, nom et mot de passe (10 caractères minimum) requis.');
 if(!yes('cgu'))fail('Vous devez accepter les conditions d’utilisation et la politique de confidentialité.',400,'consent_required');
 try{db()->prepare("INSERT INTO users(email,pass_hash,name,role,created_at) VALUES(?,?,?,'declarant',NOW())")->execute([$em,password_hash($pw,PASSWORD_DEFAULT),mb_substr($nm,0,120)]);}catch(PDOException){fail('Cette adresse e-mail est déjà utilisée.',409);}
 $id=(int)db()->lastInsertId();recordConsent($id,yes('analytics'),yes('marketing'),'mobile');notifyStaff('Nouvel utilisateur inscrit (mobile) : '.mb_substr($nm,0,60),'a=users');
 $GLOBALS['API_UID']=$id;out(['ok'=>true,'token'=>newToken($id,(string)in('device','mobile')),'user'=>userOut(me())],201);
case 'POST auth/forgot':startReset((string)in('email'));out(['ok'=>true,'message'=>'Si un compte existe avec cette adresse, un e-mail vient de lui être envoyé avec un lien valable 1 heure.']);
case 'POST auth/login':
 $em=strtolower(trim((string)in('email')));$tk='login|'.($_SERVER['REMOTE_ADDR']??'').'|'.$em;if(throttled($tk))fail('Trop de tentatives : réessayez dans 15 minutes.',429);
 $s=db()->prepare('SELECT * FROM users WHERE email=?');$s->execute([$em]);$x=$s->fetch();
 if(!$x||!password_verify((string)in('password'),$x['pass_hash'])){usleep(500000);failed($tk);fail('Identifiants invalides.',401);}
 $GLOBALS['API_UID']=(int)$x['id'];out(['ok'=>true,'token'=>newToken((int)$x['id'],(string)in('device','mobile')),'user'=>userOut(me())]);
case 'POST auth/logout':auth(false);db()->prepare('DELETE FROM api_tokens WHERE id=?')->execute([$GLOBALS['API_TOKEN']]);out(['ok'=>true]);
case 'GET me':out(['ok'=>true,'user'=>userOut(auth(false))]);
case 'PUT me':case 'POST me':$u=auth();$nm=mb_substr(trim((string)in('name',$u['name'])),0,120);$em=strtolower(trim((string)in('email',$u['email'])));$ph=mb_substr(trim((string)in('phone',(string)($u['phone']??''))),0,30);
 if($nm===''||!filter_var($em,FILTER_VALIDATE_EMAIL))fail('Un nom et une adresse e-mail valides sont requis.');
 try{db()->prepare('UPDATE users SET name=?,email=?,phone=? WHERE id=?')->execute([$nm,$em,$ph?:null,$u['id']]);}catch(PDOException){fail('Cette adresse e-mail est déjà utilisée.',409);}
 $s=db()->prepare('SELECT * FROM users WHERE id=?');$s->execute([$u['id']]);out(['ok'=>true,'user'=>userOut($s->fetch())]);
case 'POST me/password':$u=auth();if(!password_verify((string)in('current'),$u['pass_hash']))fail('Le mot de passe actuel est incorrect.',403);if(strlen((string)in('new'))<10)fail('Nouveau mot de passe : 10 caractères minimum.');
 db()->prepare('UPDATE users SET pass_hash=? WHERE id=?')->execute([password_hash((string)in('new'),PASSWORD_DEFAULT),$u['id']]);db()->prepare('DELETE FROM api_tokens WHERE user_id=? AND id<>?')->execute([$u['id'],$GLOBALS['API_TOKEN']]);out(['ok'=>true]);
case 'POST me/consent':$u=auth(false);if(!yes('cgu'))fail('L’acceptation des conditions est obligatoire pour utiliser '.APP.'.',400,'consent_required');
 recordConsent((int)$u['id'],yes('analytics'),yes('marketing'),'mobile');$s=db()->prepare('SELECT * FROM users WHERE id=?');$s->execute([$u['id']]);out(['ok'=>true,'user'=>userOut($s->fetch())]);
case 'GET me/export':out(['ok'=>true]+exportData(auth(false)));
case 'POST me/delete':case 'DELETE me':$u=auth(false);if(!password_verify((string)in('password'),$u['pass_hash']))fail('Le mot de passe est incorrect : compte non supprimé.',403);
 if($er=deleteAccount($u))fail($er,409);out(['ok'=>true]);

// Dossiers
case 'GET dossiers':$u=auth();$s=db()->prepare('SELECT * FROM dossiers WHERE user_id=? ORDER BY id DESC');$s->execute([$u['id']]);out(['ok'=>true,'access'=>access($u),'dossiers'=>array_map('dossierOut',$s->fetchAll())]);
case 'POST dossiers':$u=auth();$tp=(string)in('type','DP');if(!isset(STEPS[$tp]))fail('Type de dossier indisponible.');
 if(trim((string)in('titre'))===''||trim((string)in('adresse'))===''||trim((string)in('parcelle'))==='')fail('Intitulé, adresse et numéro de parcelle requis.');
 $id=createDossier($u,$tp,(string)in('titre'),(string)in('adresse'),(string)in('parcelle'));out(['ok'=>true,'dossier'=>dossierOut(apiDossier($u,$id),true)],201);
case 'GET dossiers/{id}':$u=auth();out(['ok'=>true,'access'=>access($u),'dossier'=>dossierOut(apiDossier($u,(int)$seg[1]),true)]);
case 'PUT dossiers/{id}/description':case 'POST dossiers/{id}/description':$u=auth();$d=apiDossier($u,(int)$seg[1]);if($u['id']!=$d['user_id']||$d['status']!=='brouillon')fail('Dossier non modifiable.',403);
 setDescription($d,(string)in('description'));out(['ok'=>true,'dossier'=>dossierOut(apiDossier($u,(int)$d['id']),true)]);
case 'POST dossiers/{id}/submit':$u=auth();[$ok,$msg]=submitDossier($u,apiDossier($u,(int)$seg[1]));if(!$ok)fail($msg,409);out(['ok'=>true,'message'=>$msg,'dossier'=>dossierOut(apiDossier($u,(int)$seg[1]),true)]);
case 'POST dossiers/{id}/reopen':$u=auth();if(!reopenDossier($u,apiDossier($u,(int)$seg[1])))fail('Action impossible sur ce dossier.',409);out(['ok'=>true,'dossier'=>dossierOut(apiDossier($u,(int)$seg[1]),true)]);
case 'DELETE dossiers/{id}':$u=auth();if(!deleteDossier($u,apiDossier($u,(int)$seg[1])))fail('Un dossier débloqué ne peut pas être supprimé.',409);out(['ok'=>true]);
case 'POST dossiers/{id}/pieces/{code}':$u=auth();$d=apiDossier($u,(int)$seg[1]);$c=urldecode((string)$seg[3]);
 if($er=savePiece($u,$d,$c,$_FILES['sheet']??null,$_FILES['base']??null,(string)in('data','{}'),array_key_exists('desc',body())?(string)in('desc'):null))fail($er,$er==='refusé'?403:400);
 out(['ok'=>true,'dossier'=>dossierOut(apiDossier($u,(int)$d['id']),true)]);
case 'GET dossiers/{id}/pieces/{code}':$u=auth();$d=apiDossier($u,(int)$seg[1]);$c=urldecode((string)$seg[3]);$p=pieces((int)$d['id'])[$c]??null;
 $kind=$seg[4]??'';if($kind==='sheet'||$kind==='base'){if(!sheetOut($d,$c,$kind==='base'?'file':'sheet'))fail('Image introuvable.',404);exit;}
 out(['ok'=>true,'code'=>$c,'done'=>!empty($p['sheet']),'has_base'=>!empty($p['file']),'data'=>json_decode((string)($p['data']??'null'))]);
case 'GET dossiers/{id}/pdf':$u=auth();try{[$pdf,$fn]=dossierPdf(apiDossier($u,(int)$seg[1]));}catch(Exception $x){fail($x->getMessage(),409);}
 header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$fn.'"');header('Cache-Control: private, no-store');echo $pdf;exit;

// Notifications
case 'GET notifications':$u=auth();$s=db()->prepare('SELECT id,msg,link,read_at,created_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50');$s->execute([$u['id']]);out(['ok'=>true,'unread'=>unread(),'notifications'=>$s->fetchAll()]);
case 'POST notifications/read':$u=auth();db()->prepare('UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL')->execute([$u['id']]);out(['ok'=>true]);

// Bibliothèque d'objets
case 'GET library':$u=auth();$s=db()->prepare('SELECT id,name,wcm,hcm,shared,(user_id=?) AS mine FROM library WHERE user_id=? OR shared=1 ORDER BY id DESC');$s->execute([$u['id'],$u['id']]);out(['ok'=>true,'items'=>$s->fetchAll()]);
case 'GET library/{id}/image':$u=auth();$s=db()->prepare('SELECT * FROM library WHERE id=? AND (user_id=? OR shared=1)');$s->execute([(int)$seg[1],$u['id']]);$x=$s->fetch();if(!$x||!is_file(DATA.'/lib/'.basename($x['file'])))fail('Image introuvable.',404);
 header('Content-Type: image/png');header('Cache-Control: private, max-age=86400');readfile(DATA.'/lib/'.basename($x['file']));exit;
case 'DELETE library/{id}':$u=auth();$s=db()->prepare('SELECT * FROM library WHERE id=?');$s->execute([(int)$seg[1]]);$x=$s->fetch();
 if(!$x||($x['user_id']!=$u['id']&&$u['role']!=='admin'))fail('Objet introuvable.',404);@unlink(DATA.'/lib/'.basename($x['file']));db()->prepare('DELETE FROM library WHERE id=?')->execute([$x['id']]);out(['ok'=>true]);
}
fail('Route inconnue : '.$m.' '.$r,404,'not_found');
