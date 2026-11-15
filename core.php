<?php
declare(strict_types=1);
// Fonctions métier partagées par le site (index.php) et l'API mobile (api.php).
// Voir docs/architecture.md.

function tryExec(string $sql):void{try{db()->exec($sql);}catch(Throwable){}}

/* ---------- Migration v8 : consentement, modèle d'accès, abonnements, jetons API ---------- */
function migrate8():void{$f=DATA.'/.v8';if(is_file($f))return;
 tryExec("ALTER TABLE users ADD consent_version VARCHAR(20) NULL");
 tryExec("ALTER TABLE users ADD opt_analytics TINYINT NOT NULL DEFAULT 0");
 tryExec("ALTER TABLE users ADD opt_marketing TINYINT NOT NULL DEFAULT 0");
 tryExec("ALTER TABLE dossiers ADD unlocked_via VARCHAR(20) NULL");
 tryExec("ALTER TABLE dossiers ADD unlocked_at DATETIME NULL");
 db()->exec("UPDATE dossiers SET unlocked_via='paiement',unlocked_at=NOW() WHERE status='paye' AND unlocked_via IS NULL");
 db()->exec("CREATE TABLE IF NOT EXISTS consents(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,version VARCHAR(20) NOT NULL,analytics TINYINT NOT NULL DEFAULT 0,marketing TINYINT NOT NULL DEFAULT 0,source VARCHAR(10) NOT NULL DEFAULT 'web',ip VARCHAR(45) NOT NULL DEFAULT '',created_at DATETIME NOT NULL,INDEX(user_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 db()->exec("CREATE TABLE IF NOT EXISTS subscriptions(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,plan VARCHAR(20) NOT NULL,platform VARCHAR(20) NOT NULL DEFAULT 'admin',status VARCHAR(20) NOT NULL DEFAULT 'active',starts_at DATETIME NOT NULL,ends_at DATETIME NULL,external_id VARCHAR(190) NULL,note VARCHAR(255) NOT NULL DEFAULT '',created_at DATETIME NOT NULL,INDEX(user_id,status),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 db()->exec("CREATE TABLE IF NOT EXISTS api_tokens(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,hash CHAR(64) NOT NULL UNIQUE,device VARCHAR(100) NOT NULL DEFAULT '',created_at DATETIME NOT NULL,last_used DATETIME NOT NULL,expires_at DATETIME NOT NULL,INDEX(user_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 file_put_contents($f,'1');}
migrate8();

/* ---------- Modèle d'accès (docs/acces-et-tarifs.md) ---------- */
const MODES=['gratuit'=>'Gratuit','quota'=>'Limité en nombre de dossiers','essai'=>'Période d’essai','abonnement'=>'Abonnement','acte'=>'Paiement par dossier'];
const ACC_DEFAULTS=['mode'=>'gratuit','free_until'=>'','after'=>'acte','quota_n'=>'3','quota_period'=>'mois','trial_days'=>'30',
 'price_DP'=>'','price_AP'=>'','price_PD'=>'','sub_month'=>'','sub_year'=>'','prod_sub_month'=>'','prod_sub_year'=>'',
 'app_min_android'=>'','app_min_ios'=>'','app_message'=>''];
function acc(string $k):string{return setting('acc_'.$k,ACC_DEFAULTS[$k]??'');}
// Mode réellement appliqué aujourd'hui (la gratuité peut avoir une date de fin)
function accMode():string{$m=acc('mode');if($m==='gratuit'&&acc('free_until')!==''&&date('Y-m-d')>acc('free_until'))$m=acc('after');return isset(MODES[$m])?$m:'acte';}
function activeSub(int $uid):?array{$s=db()->prepare("SELECT * FROM subscriptions WHERE user_id=? AND status='active' AND starts_at<=NOW() AND (ends_at IS NULL OR ends_at>NOW()) ORDER BY ends_at IS NULL DESC,ends_at DESC LIMIT 1");$s->execute([$uid]);return $s->fetch()?:null;}
function quotaUsed(int $uid):int{$w=acc('quota_period')==='mois'?" AND unlocked_at>=DATE_FORMAT(NOW(),'%Y-%m-01')":'';$s=db()->prepare("SELECT COUNT(*) FROM dossiers WHERE user_id=? AND unlocked_via='quota'$w");$s->execute([$uid]);return (int)$s->fetchColumn();}
function price(string $k):string{$v=acc($k);return $v===''?'':str_replace('.',',',$v).' €';}
// Le dossier de cet utilisateur peut-il être débloqué sans paiement ? ['ok','via','text']
function access(array $u):array{
 if($sub=activeSub((int)$u['id']))return ['ok'=>true,'via'=>'abonnement','text'=>'Abonnement actif'.($sub['ends_at']?' jusqu’au '.date('d/m/Y',strtotime($sub['ends_at'])):'').'.'];
 switch(accMode()){
  case 'gratuit':return ['ok'=>true,'via'=>'gratuit','text'=>'Accès gratuit'.(acc('mode')==='gratuit'&&acc('free_until')!==''?' jusqu’au '.date('d/m/Y',strtotime(acc('free_until'))):' pendant la période de lancement').' : vos dossiers sont débloqués dès leur envoi.'];
  case 'quota':$n=(int)acc('quota_n');$r=max(0,$n-quotaUsed((int)$u['id']));$p=acc('quota_period')==='mois'?' ce mois-ci':'';
   return $r>0?['ok'=>true,'via'=>'quota','text'=>"Il vous reste $r dossier(s) gratuit(s)$p."]:['ok'=>false,'via'=>'','text'=>"Vous avez utilisé vos $n dossier(s) gratuit(s)$p : les suivants sont débloqués après paiement."];
  case 'essai':$end=strtotime($u['created_at'].' +'.(int)acc('trial_days').' days');$j=(int)ceil(($end-time())/86400);
   return $j>0?['ok'=>true,'via'=>'essai','text'=>"Essai gratuit : encore $j jour(s)."]:['ok'=>false,'via'=>'','text'=>'Votre période d’essai est terminée : les dossiers sont débloqués après paiement.'];
  case 'abonnement':return ['ok'=>false,'via'=>'','text'=>'Un abonnement est nécessaire pour débloquer vos dossiers'.(acc('sub_month')!==''?' ('.price('sub_month').' par mois)':'').'.'];
 }
 return ['ok'=>false,'via'=>'','text'=>'Le PDF final est débloqué après validation de votre paiement.'];}
function dossierPrice(array $d):string{return price('price_'.ty($d));}

/* ---------- Consentement et textes légaux (docs/rgpd-consentement.md) ---------- */
function legalVersion():string{return setting('legal_version','1');}
function needsConsent(array $u):bool{return (string)($u['consent_version']??'')!==legalVersion();}
function recordConsent(int $uid,bool $an,bool $mk,string $src='web'):void{$v=legalVersion();
 db()->prepare('INSERT INTO consents(user_id,version,analytics,marketing,source,ip,created_at) VALUES(?,?,?,?,?,?,NOW())')->execute([$uid,$v,(int)$an,(int)$mk,$src,substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)]);
 db()->prepare('UPDATE users SET consent_version=?,opt_analytics=?,opt_marketing=? WHERE id=?')->execute([$v,(int)$an,(int)$mk,$uid]);}
const LEGAL_TITLES=['cgu'=>'Conditions générales d’utilisation','privacy'=>'Politique de confidentialité'];
function legalVars():array{return ['{APP}'=>APP,'{RESPONSABLE}'=>setting('legal_owner','[À COMPLÉTER : nom ou raison sociale, adresse]'),'{CONTACT}'=>setting('legal_contact','[À COMPLÉTER : adresse e-mail de contact]'),'{HEBERGEUR}'=>setting('legal_host','[À COMPLÉTER : hébergeur et pays des serveurs]'),'{VERSION}'=>legalVersion()];}
function legalText(string $k):string{return strtr(setting('legal_'.$k,LEGAL_DEFAULTS[$k]??''),legalVars());}
// Texte simple -> HTML : paragraphes séparés par une ligne vide, « ## » = titre, « - » = liste
function legalHtml(string $t):string{$h='';foreach(preg_split("/\R{2,}/u",trim($t)) as $b){$b=trim($b);if($b==='')continue;
  if(str_starts_with($b,'## '))$h.='<h3>'.e(substr($b,3)).'</h3>';
  elseif(str_starts_with($b,'- ')){$h.='<ul>';foreach(preg_split('/\R/u',$b) as $l)$h.='<li>'.e(ltrim($l,'- ')).'</li>';$h.='</ul>';}
  else $h.='<p>'.nl2br(e($b)).'</p>';}return $h;}
const LEGAL_DEFAULTS=[
'cgu'=>"## Objet
{APP} est un service qui aide les particuliers et les professionnels à constituer eux-mêmes leurs dossiers de demande d'autorisation d'urbanisme (déclaration préalable, autorisation préalable d'enseigne, permis de démolir). Les présentes conditions régissent l'utilisation du site et de l'application mobile.

## Éditeur
{RESPONSABLE}. Contact : {CONTACT}.

## Compte
La création d'un compte est nécessaire. Vous vous engagez à fournir des informations exactes et à garder votre mot de passe confidentiel. Vous pouvez supprimer votre compte à tout moment depuis « Mon profil ».

## Service
{APP} fournit des outils pour préparer les pièces du dossier et générer un PDF. Le dossier est déposé par vos soins auprès de la mairie compétente, accompagné du formulaire Cerfa officiel. {APP} n'est pas un service de l'administration et ne garantit pas l'acceptation de la demande. Les documents produits ne se substituent pas aux plans d'exécution.

## Accès et tarifs
L'accès peut être gratuit, limité ou payant selon la formule en vigueur, affichée dans votre espace. Toute évolution des conditions d'accès est annoncée à l'avance et ne remet pas en cause les dossiers déjà débloqués.

## Responsabilité de l'utilisateur
Vous êtes responsable du contenu que vous importez (photos, plans, textes) et de son exactitude. Vous garantissez disposer des droits sur ces contenus.

## Données personnelles
Le traitement de vos données est décrit dans la Politique de confidentialité.

## Modification des conditions
Les conditions peuvent évoluer. En cas de changement, une nouvelle acceptation vous sera demandée. Version {VERSION}.

## Droit applicable
Les présentes conditions sont soumises au droit français.",
'privacy'=>"## Responsable du traitement
{RESPONSABLE}. Pour toute question ou pour exercer vos droits : {CONTACT}.

## Données collectées
- Identité et contact : nom, adresse e-mail, téléphone (facultatif).
- Informations du projet : intitulé, adresse, numéro de parcelle, descriptions.
- Contenus importés : photos, plans, images et annotations.
- Données de connexion et de sécurité : dates de connexion, adresse IP, tentatives de connexion.
- Accès et paiement : formule utilisée, abonnement. Les données bancaires sont traitées uniquement par le prestataire de paiement (Google Play, Apple) et ne sont jamais stockées par {APP}.

## Finalités et bases légales
- Fournir le service (création du compte, constitution et génération des dossiers) : exécution du contrat.
- Sécuriser le service (prévention des abus) : intérêt légitime.
- Mesure d'audience anonyme : consentement, facultatif.
- Envoi d'informations par e-mail : consentement, facultatif.

## Durées de conservation
- Compte et dossiers : tant que le compte est actif, puis suppression [À COMPLÉTER : durée] après la dernière connexion.
- Journal de sécurité : 12 mois maximum.
- Registre des consentements : durée du compte, puis 5 ans pour preuve.

## Destinataires et hébergement
Vos données sont accessibles uniquement à l'équipe {APP} habilitée. Hébergement : {HEBERGEUR}. Sous-traitants : prestataires de paiement (Google, Apple) et service de notifications.

## Vos droits
Vous disposez des droits d'accès, de rectification, d'effacement, de portabilité, de limitation et d'opposition, ainsi que du droit de retirer votre consentement à tout moment. Depuis « Mon profil », vous pouvez modifier vos choix, télécharger vos données et supprimer votre compte. Vous pouvez aussi introduire une réclamation auprès de la CNIL (www.cnil.fr).

## Version
Version {VERSION} de la politique de confidentialité.",
];

/* ---------- Dossiers ---------- */
function createDossier(array $u,string $type,string $titre,string $adresse,string $parcelle):int{if(!isset(STEPS[$type]))$type='DP';
 db()->prepare('INSERT INTO dossiers(user_id,type,titre,adresse,parcelle,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$u['id'],$type,mb_substr(trim($titre),0,150),mb_substr(trim($adresse),0,255),mb_substr(trim($parcelle),0,40)]);
 return (int)db()->lastInsertId();}
function setDescription(array $d,string $t):void{db()->prepare('UPDATE dossiers SET description=? WHERE id=?')->execute([mb_substr($t,0,4000),$d['id']]);
 if($tc=txtCode(ty($d)))db()->prepare('UPDATE pieces SET sheet=NULL WHERE dossier_id=? AND code=?')->execute([$d['id'],$tc]);}
// Enregistre une planche (image rendue + image de fond + annotations). Renvoie null ou un message d'erreur.
function savePiece(array $u,array $d,string $c,?array $sheetF,?array $baseF,string $data,?string $desc):?string{
 if($u['id']!=$d['user_id']||$d['status']!=='brouillon'||!in_array($c,codes(ty($d)),true))return 'refusé';
 if(strlen($data)>200000||json_decode($data)===null)return 'données invalides';
 $sh=savejpeg($sheetF,1700);if(!$sh)return 'image invalide';$ba=savejpeg($baseF,2400);
 $o=pieces((int)$d['id'])[$c]??null;
 db()->prepare('INSERT INTO pieces(dossier_id,code,file,sheet,data) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE file=COALESCE(VALUES(file),file),sheet=VALUES(sheet),data=VALUES(data)')->execute([$d['id'],$c,$ba,$sh,$data]);
 if($o){if($o['sheet'])@unlink(DATA.'/img/'.basename($o['sheet']));if($ba&&$o['file'])@unlink(DATA.'/img/'.basename($o['file']));}
 $tc=txtCode(ty($d));if($tc&&$c===$tc&&$desc!==null)db()->prepare('UPDATE dossiers SET description=? WHERE id=?')->execute([mb_substr($desc,0,4000),$d['id']]);
 return null;}
function unlockDossier(int $id,string $via):void{db()->prepare("UPDATE dossiers SET status='paye',unlocked_via=?,unlocked_at=NOW() WHERE id=?")->execute([$via,$id]);
 $s=db()->prepare('SELECT user_id,titre FROM dossiers WHERE id=?');$s->execute([$id]);if($d=$s->fetch())notify((int)$d['user_id'],'Dossier « '.$d['titre'].' » débloqué : votre PDF complet est disponible.','a=dossier&id='.$id);}
// Envoi du dossier : débloqué tout de suite si la formule le permet, sinon en attente de paiement. ['ok','msg']
function submitDossier(array $u,array $d):array{
 if($u['id']!=$d['user_id']||$d['status']!=='brouillon')return [false,'Action impossible sur ce dossier.'];
 if(!complete($d,pieces((int)$d['id'])))return [false,'Le dossier est incomplet : complétez toutes les étapes.'];
 $ac=access($u);
 if($ac['ok']){unlockDossier((int)$d['id'],$ac['via']);notifyStaff('Dossier finalisé ('.$ac['via'].') : '.$d['titre'].' ('.$u['name'].')','a=dossier&id='.(int)$d['id']);return [true,'Dossier finalisé : votre PDF complet est disponible.'];}
 db()->prepare("UPDATE dossiers SET status='en_attente' WHERE id=?")->execute([$d['id']]);
 notifyStaff('Nouveau dossier soumis : '.$d['titre'].' ('.$u['name'].')','a=dossier&id='.(int)$d['id']);
 return [true,'Dossier soumis : il sera débloqué dès la validation de votre paiement'.(($p=dossierPrice($d))!==''?' ('.$p.')':'').'.'];}
function reopenDossier(array $u,array $d):bool{if($u['id']!=$d['user_id']||$d['status']!=='en_attente')return false;
 db()->prepare("UPDATE dossiers SET status='brouillon' WHERE id=?")->execute([$d['id']]);notifyStaff('Dossier retiré pour modification : '.$d['titre'].' ('.$u['name'].')','a=dossier&id='.(int)$d['id']);return true;}
function deleteDossier(array $u,array $d):bool{if(!(($u['id']==$d['user_id']&&$d['status']!=='paye')||$u['role']==='admin'))return false;
 foreach(pieces((int)$d['id']) as $pc)foreach(['file','sheet'] as $k)if(!empty($pc[$k]))@unlink(DATA.'/img/'.basename($pc[$k]));
 db()->prepare('DELETE FROM dossiers WHERE id=?')->execute([$d['id']]);return true;}
// Image d'une planche ('sheet') ou de son fond ('file'), avec filigrane BROUILLON si non débloqué
function sheetOut(array $d,string $c,string $kind):bool{$f=pieces((int)$d['id'])[$c][$kind]??null;if(!$f)return false;$path=DATA.'/img/'.basename($f);if(!is_file($path))return false;
 header('Content-Type: image/jpeg');header('Cache-Control: private, max-age=60');
 if($kind==='sheet'&&$d['status']!=='paye'&&($im=@imagecreatefromjpeg($path))){watermark($im,imagesx($im),imagesy($im),'BROUILLON');imagejpeg($im,null,85);imagedestroy($im);return true;}
 readfile($path);return true;}
// PDF du dossier : [contenu, nom de fichier]. Filigrane tant qu'il n'est pas débloqué.
function dossierPdf(array $d):array{$p=pieces((int)$d['id']);$wm=$d['status']!=='paye';$pdf=new MiniPdf;$n=0;
 foreach(codes(ty($d)) as $c){if(empty($p[$c]['sheet']))continue;$path=DATA.'/img/'.basename($p[$c]['sheet']);if(!is_file($path))continue;
  if($wm){$im=imagecreatefromjpeg($path);watermark($im,imagesx($im),imagesy($im));ob_start();imagejpeg($im,null,85);imagedestroy($im);$j=ob_get_clean();}else $j=file_get_contents($path);
  $g=getimagesizefromstring($j);$pdf->add($j,$g[0],$g[1]);$n++;}
 if(!$n)throw new Exception('Aucune planche : ouvrez les éditeurs et enregistrez chaque étape.');
 return [$pdf->out(),'DOSSIERUrba-'.ty($d).'-'.(int)$d['id'].($wm?'-apercu':'').'.pdf'];}

/* ---------- Compte ---------- */
// Suppression complète du compte (exigée par Google Play et l'App Store). Renvoie null ou un message d'erreur.
function deleteAccount(array $u):?string{
 if($u['role']==='admin'&&(int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn()<2)return 'Vous êtes le seul administrateur : nommez-en un autre avant de supprimer ce compte.';
 $ds=db()->prepare('SELECT id FROM dossiers WHERE user_id=?');$ds->execute([$u['id']]);
 foreach($ds->fetchAll(PDO::FETCH_COLUMN) as $did)foreach(pieces((int)$did) as $pc)foreach(['file','sheet'] as $k)if(!empty($pc[$k]))@unlink(DATA.'/img/'.basename($pc[$k]));
 $ls=db()->prepare('SELECT file FROM library WHERE user_id=?');$ls->execute([$u['id']]);foreach($ls->fetchAll(PDO::FETCH_COLUMN) as $lf)@unlink(DATA.'/lib/'.basename($lf));
 foreach(['DELETE FROM dossiers WHERE user_id=?','DELETE FROM library WHERE user_id=?','DELETE FROM users WHERE id=?'] as $q)db()->prepare($q)->execute([$u['id']]);
 return null;}
// Export des données personnelles (droit d'accès / portabilité)
function exportData(array $u):array{$q=function(string $sql)use($u){$s=db()->prepare($sql);$s->execute([$u['id']]);return $s->fetchAll();};
 $me=$u;unset($me['pass_hash']);$ds=$q('SELECT id,type,titre,adresse,parcelle,description,status,unlocked_via,unlocked_at,created_at FROM dossiers WHERE user_id=?');
 foreach($ds as &$d){$d['pieces']=array_keys(array_filter(pieces((int)$d['id']),fn($p)=>!empty($p['sheet'])));}unset($d);
 return ['application'=>APP,'exporte_le'=>date('c'),'compte'=>$me,'dossiers'=>$ds,'consentements'=>$q('SELECT version,analytics,marketing,source,created_at FROM consents WHERE user_id=? ORDER BY id'),
  'abonnements'=>$q('SELECT plan,platform,status,starts_at,ends_at FROM subscriptions WHERE user_id=?'),'notifications'=>$q('SELECT msg,read_at,created_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 200'),
  'bibliotheque'=>$q('SELECT name,wcm,hcm,shared,created_at FROM library WHERE user_id=?')];}

/* ---------- Mot de passe oublié (docs/rgpd-consentement.md, docs/api.md) ---------- */
function migrate9():void{$f=DATA.'/.v9';if(is_file($f))return;
 db()->exec("CREATE TABLE IF NOT EXISTS password_resets(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,hash CHAR(64) NOT NULL UNIQUE,expires_at DATETIME NOT NULL,used_at DATETIME NULL,created_at DATETIME NOT NULL,INDEX(user_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 file_put_contents($f,'1');}
migrate9();
function siteBase():string{return (empty($_SERVER['HTTPS'])?'http':'https').'://'.($_SERVER['HTTP_HOST']??'localhost').rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/')),'/').'/';}
// Envoi d'un e-mail texte via mail() (Hostinger). Expéditeur : réglage « mail_from » (Admin → Textes légaux).
function sendMail(string $to,string $subject,string $body):bool{
 $from=setting('mail_from','')?:'no-reply@'.preg_replace('/^www\./','',explode(':',(string)($_SERVER['HTTP_HOST']??'localhost'))[0]);
 $h="From: ".mb_encode_mimeheader(APP)." <$from>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
 if(filter_var(setting('legal_contact'),FILTER_VALIDATE_EMAIL))$h.="\r\nReply-To: ".setting('legal_contact');
 $ok=@mail($to,mb_encode_mimeheader($subject,'UTF-8'),$body,$h,'-f'.$from);if(!$ok)error_log('Envoi d’e-mail impossible vers '.$to.' : '.$subject);return $ok;}
// Demande de réinitialisation : toujours silencieuse (ne révèle pas si le compte existe). Lien valable 1 heure.
function startReset(string $email):void{$k='reset|'.($_SERVER['REMOTE_ADDR']??'');failed($k);if(throttled($k))return;
 $s=db()->prepare('SELECT id,name,email FROM users WHERE email=?');$s->execute([strtolower(trim($email))]);$u=$s->fetch();if(!$u)return;
 db()->prepare('DELETE FROM password_resets WHERE user_id=? OR expires_at<NOW()')->execute([$u['id']]);$t=bin2hex(random_bytes(32));
 db()->prepare('INSERT INTO password_resets(user_id,hash,expires_at,created_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR),NOW())')->execute([$u['id'],hash('sha256',$t)]);
 sendMail($u['email'],APP.' : réinitialisation de votre mot de passe',"Bonjour ".$u['name'].",\n\nPour choisir un nouveau mot de passe, ouvrez ce lien (valable 1 heure) :\n".siteBase()."index.php?a=reset&t=$t\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.\n\nL'équipe ".APP);}
function resetUser(string $t):?array{if(!preg_match('/^[a-f0-9]{64}$/',$t))return null;$s=db()->prepare('SELECT r.id rid,u.* FROM password_resets r JOIN users u ON u.id=r.user_id WHERE r.hash=? AND r.used_at IS NULL AND r.expires_at>NOW()');$s->execute([hash('sha256',$t)]);return $s->fetch()?:null;}
// Nouveau mot de passe : déconnecte aussi l'application mobile sur tous les appareils
function resetPassword(string $t,string $new):?string{$u=resetUser($t);if(!$u)return 'Ce lien n’est plus valable : refaites une demande.';if(strlen($new)<10)return 'Mot de passe : 10 caractères minimum.';
 db()->prepare('UPDATE users SET pass_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$u['id']]);db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')->execute([$u['rid']]);
 db()->prepare('DELETE FROM api_tokens WHERE user_id=?')->execute([$u['id']]);return null;}

/* ---------- Bibliothèque d'objets ---------- */
// Ajout d'un objet (PNG détouré). Renvoie null ou un message d'erreur. Seul un admin peut le partager.
function saveLibraryItem(array $u,?array $f,array $in):?string{
 if(!$f||$f['error']!==0||$f['size']>4*1048576)return 'fichier refusé';
 $raw=file_get_contents($f['tmp_name']);if((new finfo(FILEINFO_MIME_TYPE))->buffer($raw)!=='image/png'||!($im=@imagecreatefromstring($raw)))return 'image invalide';
 $n=db()->prepare('SELECT COUNT(*) FROM library WHERE user_id=?');$n->execute([$u['id']]);if($n->fetchColumn()>=100)return 'bibliothèque pleine (100)';
 $k=min(1,1000/max(imagesx($im),imagesy($im)));if($k<1){$t=imagecreatetruecolor((int)(imagesx($im)*$k),(int)(imagesy($im)*$k));imagealphablending($t,false);imagesavealpha($t,true);imagefill($t,0,0,imagecolorallocatealpha($t,0,0,0,127));imagecopyresampled($t,$im,0,0,0,0,imagesx($t),imagesy($t),imagesx($im),imagesy($im));$im=$t;}else{imagealphablending($im,false);imagesavealpha($im,true);}
 $fn=bin2hex(random_bytes(16)).'.png';imagepng($im,DATA.'/lib/'.$fn,6);$sh=($u['role']==='admin'&&!empty($in['shared']))?1:0;
 db()->prepare('INSERT INTO library(user_id,name,file,wcm,hcm,shared,created_at) VALUES(?,?,?,?,?,?,NOW())')->execute([$u['id'],mb_substr(trim((string)($in['name']??'Objet')),0,60)?:'Objet',$fn,max(0,min(5000,(int)($in['wcm']??0))),max(0,min(5000,(int)($in['hcm']??0))),$sh]);
 return null;}
