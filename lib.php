<?php
declare(strict_types=1);
if(!is_file(__DIR__.'/config.php')){header('Location: install.php');exit;}
$C=require __DIR__.'/config.php';
ini_set('display_errors','0');ini_set('log_errors','1');ini_set('error_log',__DIR__.'/storage/error.log');
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax']);
session_start();
if(isset($_SESSION['la'])&&time()-$_SESSION['la']>7200){session_unset();session_destroy();session_start();}$_SESSION['la']=time();
set_exception_handler(function($x){error_log((string)$x);http_response_code(500);echo 'Erreur interne. Réessayez dans un instant.';});
const PIECES=['DP0','DP1','DP4','DP5','DP6','DP7','DP8','DP11'];
const STEPS=[
 'DP1'=>['Plan de situation (cadastre / Google Maps)','img'],
 'DP4'=>['Façades : composants et dimensions','img'],
 'DP5'=>['Photo réelle de la maison','img'],
 'DP6'=>['Insertion paysagère (photomontage)','img'],
 'DP7'=>['Photo de loin, sans montage','img'],
 'DP8'=>['Photo rapprochée, sans montage','img'],
 'DP11'=>['Description du projet par le déclarant','txt'],
];
function db():PDO{static $p;global $C;return $p??=new PDO("mysql:host={$C['h']};dbname={$C['n']};charset=utf8mb4",$C['u'],$C['p'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
function e($s):string{return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function csrf():string{return $_SESSION['t']??=bin2hex(random_bytes(16));}
function chk():void{if(!hash_equals(csrf(),(string)($_POST['t']??'')))die('Jeton invalide');}
function me(){static $u;if(empty($_SESSION['uid']))return null;if($u===null){$s=db()->prepare('SELECT * FROM users WHERE id=?');$s->execute([$_SESSION['uid']]);$u=$s->fetch()?:null;}return $u;}
function staff():bool{$u=me();return $u&&in_array($u['role'],['gestionnaire','admin'],true);}
function go(string $a,string $q=''):void{header("Location: index.php?a=$a$q");exit;}
function need(bool $staff=false,bool $admin=false):array{$u=me();if(!$u)go('login');if(($staff&&!staff())||($admin&&$u['role']!=='admin')){http_response_code(403);exit('Accès refusé');}return $u;}
function dossier(int $id):array{$u=need();$s=db()->prepare('SELECT * FROM dossiers WHERE id=?');$s->execute([$id]);$d=$s->fetch();if(!$d||($d['user_id']!=$u['id']&&!staff())){http_response_code(404);exit('Introuvable');}return $d;}
function pieces(int $id):array{$s=db()->prepare('SELECT code,file,sheet,data FROM pieces WHERE dossier_id=?');$s->execute([$id]);return $s->fetchAll(PDO::FETCH_UNIQUE);}
function missing(array $d,array $p):array{$m=[];foreach(STEPS as $c=>[$t,$k])if($k==='img'?empty($p[$c]['sheet']):trim((string)$d['description'])==='')$m[]=$c;if(empty($p['DP0']['sheet'])||empty($p['DP11']['sheet']))$m[]='PDF';return $m;}
function complete(array $d,array $p):bool{return !missing($d,$p);}
function asset(string $p):string{return 'assets/'.$p.'?v='.(@filemtime(__DIR__.'/assets/'.$p)?:1);}
function flash(string $t,string $m):void{$_SESSION['flash']=[$t,$m];}
function icon(string $n,string $c=''):string{return '<svg class="i '.$c.'"><use href="#i-'.$n.'"/></svg>';}
function badge(string $s):string{$m=['brouillon'=>['Brouillon','b-mu'],'en_attente'=>['En attente','b-wa'],'paye'=>['Validé','b-ok']];return '<span class="badge '.$m[$s][1].'">'.$m[$s][0].'</span>';}
function pager(int $tot,int $per,int $p,string $base):string{$n=max(1,(int)ceil($tot/$per));if($n<2)return '';$h='<nav class="pager" aria-label="Pagination">';for($i=1;$i<=$n;$i++){if($i==1||$i==$n||abs($i-$p)<=1)$h.='<a class="'.($i==$p?'on':'').'" href="'.e($base).'&p='.$i.'">'.$i.'</a>';elseif(abs($i-$p)==2)$h.='<span>…</span>';}return $h.'</nav>';}
function stats(array $n):string{$t=array_sum($n);$f=fn($k)=>(int)($n[$k]??0);return '<div class="stats"><div><b>'.$t.'</b>Dossiers</div><div><b>'.$f('brouillon').'</b>Brouillons</div><div><b>'.$f('en_attente').'</b>En attente de validation</div><div><b>'.$f('paye').'</b>Validés</div></div>';}
function tools(string $a,string $q,string $st,string $ph,bool $status=true):string{$o='';if($status)foreach([''=>'Tous les statuts','brouillon'=>'Brouillons','en_attente'=>'En attente','paye'=>'Validés'] as $k=>$v)$o.='<option value="'.$k.'"'.($k===$st?' selected':'').'>'.$v.'</option>';
 return '<form method="get" class="tools"><input type="hidden" name="a" value="'.$a.'"><div class="sb">'.icon('search').'<input type="search" name="q" value="'.e($q).'" placeholder="'.e($ph).'" aria-label="Rechercher"></div>'.($status?'<select name="st" data-auto aria-label="Statut">'.$o.'</select>':'').'<button>'.icon('search').'Rechercher</button></form>';}
function page(string $t,string $b,string $js=''):void{$u=me();$cur=(string)($_GET['a']??'dash');if(in_array($cur,['dossier','edit'],true))$cur='dash';
 $sy=['home'=>'<path d="M3 11l9-8 9 8v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>','shield'=>'<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>','users'=>'<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 3-6 6.5-6s6.5 2.5 6.5 6M17 11a3 3 0 1 0 0-6M21.5 19c0-2.5-1.8-4.5-4-5"/>','log'=>'<path d="M5 4h14v16H5zM9 9h6M9 13h6M9 17h3"/>','out'=>'<path d="M9 4H5v16h4M16 8l4 4-4 4M20 12H9"/>','search'=>'<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.5-4.5"/>','plus'=>'<path d="M12 5v14M5 12h14"/>','file'=>'<path d="M6 3h8l4 4v14H6zM14 3v4h4"/>','check'=>'<path d="M5 12l5 5 9-10"/>','dl'=>'<path d="M12 4v11M7 11l5 5 5-5M5 20h14"/>','edit'=>'<path d="M4 20h4L19 9l-4-4L4 16zM14 6l4 4"/>','menu'=>'<path d="M4 7h16M4 12h16M4 17h16"/>','send'=>'<path d="M21 3L10 14M21 3l-7 18-4-7-7-4z"/>','sun'=>'<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>','moon'=>'<path d="M21 13A9 9 0 1 1 11 3a7 7 0 0 0 10 10z"/>','trash'=>'<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>','bell'=>'<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4zM10 21h4"/>','user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>','undo'=>'<path d="M9 14L4 9l5-5M4 9h10a6 6 0 0 1 0 12h-3"/>'];
 echo '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark"><meta name="theme-color" content="#1f4fd8"><link rel="manifest" href="manifest.webmanifest"><link rel="icon" href="assets/icon-192.png"><link rel="apple-touch-icon" href="assets/icon-192.png"><title>'.e($t).' – Dossier DP</title><link rel="stylesheet" href="'.asset('app.css').'"><script src="'.asset('theme.js').'"></script><svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>';
 foreach($sy as $k=>$v)echo '<symbol id="i-'.$k.'" viewBox="0 0 24 24">'.$v.'</symbol>';echo '</defs></svg><body>';
 $brand='<div class="brand"><b>DP</b><span>Dossier DP</span></div>';$th='<button type="button" class="ghost th" aria-label="Changer de thème" title="Thème clair / sombre">'.icon('sun','sun').icon('moon','moon').'</button>';
 if(!$u)echo '<main class="auth"><div style="position:absolute;top:14px;right:14px">'.$th.'</div><div class="c">'.$brand.$b.'</div></main>';
 else{$m=[['dash','home','Mes dossiers',1],['notifs','bell','Notifications',1],['admin','shield','Gestion des dossiers',staff()],['users','users','Utilisateurs',$u['role']==='admin'],['log','log','Journal d\'erreurs',$u['role']==='admin'],['profile','user','Mon profil',1]];$n='';
  foreach($m as [$k,$ic,$l,$ok])if($ok)$n.='<a href="index.php?a='.$k.'" class="'.($cur===$k?'on':'').'">'.icon($ic).$l.($k==='notifs'&&unread()?'<span class="badge b-wa" style="margin-left:auto">'.unread().'</span>':'').'</a>';
  echo '<div class="top"><button id="mb" class="ghost" aria-label="Menu">'.icon('menu').'</button><b>Dossier DP</b></div><div class="app"><aside class="side">'.$brand.'<nav class="nav">'.$n.'</nav><div class="me"><div style="display:flex;justify-content:space-between;align-items:center;gap:8px"><div><b>'.e($u['name']).'</b><small>'.e(['declarant'=>'Déclarant','gestionnaire'=>'Gestionnaire','admin'=>'Administrateur'][$u['role']]).'</small></div>'.$th.'</div><form method="post" action="index.php?a=logout"><input type="hidden" name="t" value="'.csrf().'"><button class="ghost w">'.icon('out').'Déconnexion</button></form></div></aside><main class="main">'.$b.'</main></div>';}
 $fl=$_SESSION['flash']??null;unset($_SESSION['flash']);if($fl)echo '<div id="flash" hidden data-type="'.e($fl[0]).'" data-msg="'.e($fl[1]).'"></div>';
 echo '<script src="'.asset('ui.js').'"></script>'.($js?'<script src="'.asset(basename($js)).'"></script>':'').'</body></html>';}
function statut(string $s):string{return ['brouillon'=>'Brouillon','en_attente'=>'En attente de validation du paiement','paye'=>'Payé / validé'][$s];}

/* ---------- Rendu des planches (GD) ---------- */
function big($im,string $s,int $x,int $y,int $k,int $col=0):void{$s=mb_convert_encoding($s,'ISO-8859-1','UTF-8');$w=max(1,strlen($s)*9);$t=imagecreatetruecolor($w,15);imagefill($t,0,0,0xFFFFFF);imagestring($t,5,0,0,$s,$col);imagecopyresized($im,$t,$x,$y,0,0,$w*$k,15*$k,$w,15);imagedestroy($t);}
function watermark($im,int $W,int $H):void{$s='APERCU - PAIEMENT NON VALIDE';
 $t=imagecreatetruecolor(270,16);imagealphablending($t,false);imagesavealpha($t,true);imagefill($t,0,0,imagecolorallocatealpha($t,255,255,255,127));imagestring($t,5,2,0,$s,imagecolorallocatealpha($t,200,0,0,60));
 $b=imagecreatetruecolor(1080,64);imagealphablending($b,false);imagesavealpha($b,true);imagefill($b,0,0,imagecolorallocatealpha($b,255,255,255,127));imagecopyresampled($b,$t,0,0,0,0,1080,64,270,16);
 $r=function_exists('imagerotate')?@imagerotate($b,30,imagecolorallocatealpha($b,255,255,255,127)):false;if(!$r)$r=$b;
 imagealphablending($r,false);imagesavealpha($r,true);imagealphablending($im,true);$rw=imagesx($r);$rh=imagesy($r);
 for($y=-100;$y<$H;$y+=380)for($x=-200;$x<$W;$x+=900)imagecopy($im,$r,$x,$y,0,0,$rw,$rh);}
function sheet(array $d,array $own,string $code,string $title,?string $img,string $text,bool $wm):string{
 $W=1654;$H=1169;$im=imagecreatetruecolor($W,$H);$bk=0;imagefill($im,0,0,0xFFFFFF);imagerectangle($im,40,40,$W-40,$H-40,$bk);imageline($im,40,1000,$W-40,1000,$bk);
 if($img&&is_file($img)){$s=imagecreatefromjpeg($img);$sw=imagesx($s);$sh=imagesy($s);$k=min(1500/$sw,910/$sh);$nw=(int)($sw*$k);$nh=(int)($sh*$k);imagecopyresampled($im,$s,(int)(($W-$nw)/2),60+(int)((930-$nh)/2),0,0,$nw,$nh,$sw,$sh);imagedestroy($s);}
 $y=70;foreach(explode("\n",wordwrap($text,72,"\n",true)) as $l){if($y>950)break;big($im,$l,80,$y,2);$y+=38;}
 big($im,'DECLARATION PREALABLE DE TRAVAUX',60,1015,2);big($im,'Les presents documents ne se substituent pas aux plans d\'execution.',60,1100,1);
 big($im,'Proprietaire : '.$own['name'],700,1010,1);big($im,'Parcelle n. '.$d['parcelle'],700,1040,1);big($im,'Adresse : '.mb_substr($d['adresse'],0,60),700,1070,1);
 big($im,$title,1150,1015,1);big($im,date('d/m/Y'),1150,1050,1);big($im,$code,1450,1075,3);
 if($wm)watermark($im,$W,$H);
 ob_start();imagejpeg($im,null,88);imagedestroy($im);return ob_get_clean();}
class MiniPdf{private array $p=[];
 function add(string $j,int $w,int $h):void{$this->p[]=[$j,$w,$h];}
 function out():string{$n=count($this->p);$s="%PDF-1.4\n";$o=[];$put=function(int $id,string $b)use(&$s,&$o){$o[$id]=strlen($s);$s.="$id 0 obj\n$b\nendobj\n";};
  $k='';for($i=0;$i<$n;$i++)$k.=(3+3*$i).' 0 R ';$put(1,'<</Type/Catalog/Pages 2 0 R>>');$put(2,"<</Type/Pages/Kids[$k]/Count $n>>");
  foreach($this->p as $i=>[$j,$w,$h]){$g=3+3*$i;$c='q 1190.55 0 0 841.89 0 0 cm /I0 Do Q';
   $put($g,"<</Type/Page/Parent 2 0 R/MediaBox[0 0 1190.55 841.89]/Contents ".($g+1)." 0 R/Resources<</XObject<</I0 ".($g+2)." 0 R>>>>>>");
   $put($g+1,'<</Length '.strlen($c).">>\nstream\n$c\nendstream");
   $put($g+2,"<</Type/XObject/Subtype/Image/Width $w/Height $h/ColorSpace/DeviceRGB/BitsPerComponent 8/Filter/DCTDecode/Length ".strlen($j).">>\nstream\n$j\nendstream");}
  $inf=3+3*$n;$put($inf,'<</Title(Declaration prealable de travaux)/Producer(Dossier DP)>>');$x=strlen($s);$cnt=3*$n+4;$s.="xref\n0 $cnt\n0000000000 65535 f \n";for($i=1;$i<$cnt;$i++)$s.=sprintf("%010d 00000 n \n",$o[$i]);
  return $s."trailer\n<</Size $cnt/Root 1 0 R/Info $inf 0 R>>\nstartxref\n$x\n%%EOF";}}

function savejpeg(?array $f,int $max):?string{if(!$f||$f['error']!==0||$f['size']>8*1048576)return null;$raw=file_get_contents($f['tmp_name']);if((new finfo(FILEINFO_MIME_TYPE))->buffer($raw)!=='image/jpeg')return null;$im=@imagecreatefromstring($raw);if(!$im)return null;$k=min(1,$max/max(imagesx($im),imagesy($im)));if($k<1){$n=imagecreatetruecolor((int)(imagesx($im)*$k),(int)(imagesy($im)*$k));imagecopyresampled($n,$im,0,0,0,0,imagesx($n),imagesy($n),imagesx($im),imagesy($im));$im=$n;}$n=bin2hex(random_bytes(16)).'.jpg';imagejpeg($im,__DIR__.'/storage/img/'.$n,90);return $n;}
function migrate():void{$f=__DIR__.'/storage/.v2';if(is_file($f))return;try{db()->query('SELECT sheet,data FROM pieces LIMIT 1');}catch(Throwable){db()->exec('ALTER TABLE pieces ADD sheet VARCHAR(64) NULL, ADD data MEDIUMTEXT NULL');}db()->exec('ALTER TABLE pieces MODIFY file VARCHAR(64) NULL');file_put_contents($f,'1');}
migrate();

function migrate3():void{$f=__DIR__.'/storage/.v3';if(is_file($f))return;db()->exec("CREATE TABLE IF NOT EXISTS library(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,name VARCHAR(60) NOT NULL,file VARCHAR(64) NOT NULL,wcm INT NOT NULL DEFAULT 0,hcm INT NOT NULL DEFAULT 0,shared TINYINT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL,FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");@mkdir(__DIR__.'/storage/lib',0750,true);file_put_contents($f,'1');}
migrate3();

function notify(int $uid,string $msg,string $link=''):void{db()->prepare('INSERT INTO notifications(user_id,msg,link,created_at) VALUES(?,?,?,NOW())')->execute([$uid,mb_substr($msg,0,255),$link]);}
function notifyStaff(string $msg,string $link='',int $except=0):void{foreach(db()->query("SELECT id FROM users WHERE role IN ('admin','gestionnaire')")->fetchAll(PDO::FETCH_COLUMN) as $id)if((int)$id!==$except)notify((int)$id,$msg,$link);}
function unread():int{static $n;if($n===null){$u=me();if(!$u)return 0;$s=db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL');$s->execute([$u['id']]);$n=(int)$s->fetchColumn();}return $n;}
function throttled(string $k):bool{$h=hash('sha256',$k);db()->prepare('DELETE FROM login_fails WHERE ts<?')->execute([time()-900]);$s=db()->prepare('SELECT COUNT(*) FROM login_fails WHERE k=? AND ts>?');$s->execute([$h,time()-900]);return (int)$s->fetchColumn()>=8;}
function failed(string $k):void{db()->prepare('INSERT INTO login_fails(k,ts) VALUES(?,?)')->execute([hash('sha256',$k),time()]);}
function migrate4():void{$f=__DIR__.'/storage/.v4';if(is_file($f))return;
 try{db()->exec('ALTER TABLE users ADD phone VARCHAR(30) NULL');}catch(Throwable){}
 db()->exec("CREATE TABLE IF NOT EXISTS notifications(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,msg VARCHAR(255) NOT NULL,link VARCHAR(120) NOT NULL DEFAULT '',read_at DATETIME NULL,created_at DATETIME NOT NULL,INDEX(user_id,read_at),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 db()->exec("CREATE TABLE IF NOT EXISTS login_fails(id INT AUTO_INCREMENT PRIMARY KEY,k CHAR(64) NOT NULL,ts INT NOT NULL,INDEX(k,ts)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 foreach(['ALTER TABLE dossiers ADD INDEX idx_user_status(user_id,status)','ALTER TABLE dossiers ADD INDEX idx_status(status)'] as $q){try{db()->exec($q);}catch(Throwable){}}
 file_put_contents($f,'1');}
migrate4();
