<?php
declare(strict_types=1);
require __DIR__.'/lib.php';
$a=$_GET['a']??'dash';$post=$_SERVER['REQUEST_METHOD']==='POST';$err='';
if($post)chk();
$form=fn(string $in)=>'<form method="post"><input type="hidden" name="t" value="'.csrf().'">'.$in.'</form>';

if($a==='logout'&&$post){session_destroy();header('Location: index.php');exit;}

if($a==='login'||$a==='register'){
 if($post){$em=strtolower(trim((string)$_POST['email']));
  if($a==='login'){$tk='login|'.($_SERVER['REMOTE_ADDR']??'').'|'.$em;if(throttled($tk)){$err='Trop de tentatives : réessayez dans 15 minutes.';}else{$s=db()->prepare('SELECT * FROM users WHERE email=?');$s->execute([$em]);$r=$s->fetch();
   if($r&&password_verify((string)$_POST['pass'],$r['pass_hash'])){session_regenerate_id(true);$_SESSION['uid']=$r['id'];$cn=db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL');$cn->execute([$r['id']]);$kn=(int)$cn->fetchColumn();if($kn)flash('info','Vous avez '.$kn.' nouvelle(s) notification(s).');go('dash');}
   usleep(500000);failed($tk);$err='Identifiants invalides.';}}
  else{$rk='reg|'.($_SERVER['REMOTE_ADDR']??'');failed($rk);if(throttled($rk))$err='Trop de tentatives : réessayez dans 15 minutes.';elseif(!filter_var($em,FILTER_VALIDATE_EMAIL)||strlen((string)$_POST['pass'])<10||trim((string)$_POST['name'])==='')$err='Email, nom et mot de passe (10+ caractères) requis.';
   elseif(empty($_POST['cgu']))$err='Vous devez accepter les conditions d’utilisation et la politique de confidentialité.';
   else try{db()->prepare("INSERT INTO users(email,pass_hash,name,role,created_at) VALUES(?,?,?,'declarant',NOW())")->execute([$em,password_hash((string)$_POST['pass'],PASSWORD_DEFAULT),mb_substr(trim((string)$_POST['name']),0,120)]);recordConsent((int)db()->lastInsertId(),!empty($_POST['analytics']),!empty($_POST['marketing']));notifyStaff('Nouvel utilisateur inscrit : '.mb_substr(trim((string)$_POST['name']),0,60),'a=users');$err='Compte créé, connectez-vous.';$a='login';}catch(PDOException){$err='Email déjà utilisé.';}}}
 $reg=$a==='register';if($err)flash($err==='Compte créé, connectez-vous.'?'success':'danger',$err);
 page($reg?'Inscription':'Connexion','<h2>'.($reg?'Créer un compte':'Connexion').'</h2>'.$form(($reg?'Nom<input name="name" required>':'').'Email<input name="email" type="email" required>Mot de passe<input name="pass" type="password" required>'.($reg?consentBoxes(false,false):'').'<button>'.($reg?'Créer':'Entrer').'</button>').'<p><a href="index.php?a='.($reg?'login':'register').'">'.($reg?'J\'ai déjà un compte':'Créer un compte').'</a>'.($reg?'':' · <a href="index.php?a=forgot">Mot de passe oublié ?</a>').'</p><p class="zl"><a href="'.e(ZONE).'" target="_blank" rel="noopener">Voir dans quelle zone se trouve votre parcelle ↗</a></p><p class="zl">'.legalLinks().'</p>');exit;}

// Pages légales publiques (exigées par les stores : lien vers la politique et la suppression de compte)
if(isset(LEGAL_TITLES[$a])){page(LEGAL_TITLES[$a],'<div class="legal"><h2>'.e(LEGAL_TITLES[$a]).'</h2><p class="mu">Version '.e(legalVersion()).'</p>'.legalHtml(legalText($a)).'<p><a href="index.php">← Retour</a></p></div>');exit;}
if($a==='forgot'){if($post){startReset((string)($_POST['email']??''));flash('success','Si un compte existe avec cette adresse, un e-mail vient de lui être envoyé avec un lien valable 1 heure.');header('Location: index.php?a=login');exit;}
 page('Mot de passe oublié','<h2>Mot de passe oublié</h2><p class="mu">Indiquez l’adresse e-mail de votre compte : vous recevrez un lien pour choisir un nouveau mot de passe.</p>'.$form('<label>Adresse e-mail</label><input name="email" type="email" required autocomplete="email"><button>'.icon('send').'Envoyer le lien</button>').'<p><a href="index.php?a=login">← Retour à la connexion</a></p>');exit;}
if($a==='reset'){$t=(string)($_GET['t']??$_POST['tk']??'');
 if($post){if(($_POST['new']??'')!==($_POST['new2']??''))flash('danger','Les deux mots de passe sont différents.');elseif($er=resetPassword($t,(string)$_POST['new']))flash('danger',$er);else{flash('success','Mot de passe modifié : connectez-vous avec le nouveau, sur le site comme dans l’application.');header('Location: index.php?a=login');exit;}header('Location: index.php?a=reset&t='.urlencode($t));exit;}
 page('Nouveau mot de passe',resetUser($t)?'<h2>Nouveau mot de passe</h2>'.$form('<input type="hidden" name="tk" value="'.e($t).'"><label>Nouveau mot de passe (10 caractères minimum)</label><input type="password" name="new" minlength="10" required autocomplete="new-password"><label>Confirmer</label><input type="password" name="new2" minlength="10" required autocomplete="new-password"><button>'.icon('check').'Enregistrer</button>'):'<h2>Lien expiré</h2><p class="mu">Ce lien n’est plus valable (il expire après 1 heure et ne sert qu’une fois).</p><p><a class="b" href="index.php?a=forgot">Refaire une demande</a></p>');exit;}
if($a==='suppression'){page('Supprimer mon compte','<div class="legal"><h2>Supprimer mon compte '.APP.'</h2><p>Vous pouvez supprimer votre compte et toutes les données associées (dossiers, images, bibliothèque, consentements) à tout moment :</p><ul><li>sur le site ou dans l’application : <b>Mon profil → Supprimer mon compte</b> ;</li><li>ou en écrivant à '.e(legalVars()['{CONTACT}']).' depuis l’adresse e-mail de votre compte.</li></ul><p>La suppression est définitive et intervient immédiatement (sous 30 jours maximum en cas de demande par e-mail).</p><p><a href="index.php?a=login">Se connecter</a></p></div>');exit;}

$u=need();

if($a==='consent'){
 if($post){if(empty($_POST['cgu'])){flash('danger','Vous devez accepter les conditions pour utiliser '.APP.'.');go('consent');}
  recordConsent((int)$u['id'],!empty($_POST['analytics']),!empty($_POST['marketing']));flash('success','Merci, vos choix sont enregistrés.');go('dash');}
 page('Conditions d’utilisation','<div class="c legal"><h2>Avant de continuer</h2><p>'.APP.' a besoin de votre accord pour traiter vos données personnelles :</p><ul><li><b>Données utilisées</b> : nom, e-mail, téléphone (facultatif), adresse et parcelle du projet, photos et plans importés.</li><li><b>Pourquoi</b> : créer vos dossiers d’urbanisme et générer le PDF, gérer votre compte et votre accès.</li><li><b>Vos droits</b> : accès, rectification, suppression, portabilité, retrait du consentement, depuis « Mon profil ».</li></ul>'
 .$form(consentBoxes((bool)$u['opt_analytics'],(bool)$u['opt_marketing']).'<button>'.icon('check').'Continuer</button>').'</div>');exit;}
if(needsConsent($u))go('consent');

if($a==='dash'){
 if($post&&isset($_POST['titre'])){$id=createDossier($u,(string)($_POST['type']??'DP'),(string)$_POST['titre'],(string)$_POST['adresse'],(string)$_POST['parcelle']);flash('success','Dossier créé : complétez les étapes une par une.');go('dossier','&id='.$id);}
 $q=trim((string)($_GET['q']??''));$st=(string)($_GET['st']??'');$pg=max(1,(int)($_GET['p']??1));$per=9;$w='user_id=?';$args=[$u['id']];
 if($q!==''){$w.=' AND (titre LIKE ? OR adresse LIKE ?)';array_push($args,"%$q%","%$q%");}
 if(in_array($st,['brouillon','en_attente','paye'],true)){$w.=' AND status=?';$args[]=$st;}
 $c=db()->prepare("SELECT COUNT(*) FROM dossiers WHERE $w");$c->execute($args);$tot=(int)$c->fetchColumn();
 $s=db()->prepare("SELECT *,(SELECT COUNT(*) FROM pieces p WHERE p.dossier_id=dossiers.id AND p.sheet IS NOT NULL AND p.code IN ('".implode("','",reqImg())."')) AS done FROM dossiers WHERE $w ORDER BY id DESC LIMIT $per OFFSET ".(($pg-1)*$per));$s->execute($args);$h='';
 foreach($s as $d){$nr=count(reqImg(ty($d)));$h.='<a class="dc" href="index.php?a=dossier&id='.$d['id'].'"><div class="l"><div class="ph" style="margin:0"><b>'.e($d['titre']).'</b>'.badge($d['status']).'</div><span class="mu">'.e($d['adresse']).'</span><div class="bar"><i style="width:'.round($d['done']/$nr*100).'%"></i></div><small>'.(int)$d['done'].' / '.$nr.' pièces obligatoires complétées</small></div><div class="r" title="'.e(TYPES[ty($d)][0]).'">'.ty($d).'</div></a>';}
 $n=db()->prepare('SELECT status,COUNT(*) FROM dossiers WHERE user_id=? GROUP BY status');$n->execute([$u['id']]);
 page('Accueil','<div class="c hero"><div><h2>Bienvenue sur '.APP.'</h2><p class="mu">Constituez vous-même votre dossier de demande d’autorisation d’urbanisme, étape par étape.</p></div><a class="btn" href="'.e(ZONE).'" target="_blank" rel="noopener">'.icon('search').'Voir dans quelle zone se trouve votre parcelle ↗</a></div>'
 .'<div class="c acc">'.icon('check').'<span>'.e(access($u)['text']).'</span></div>'
 .'<div class="ph"><div><h2>Mes dossiers</h2><p class="mu">Constituez et suivez vos dossiers d’urbanisme.</p></div></div>'.stats($n->fetchAll(PDO::FETCH_KEY_PAIR))
 .'<details class="c" id="new"'.(isset($_GET['new'])?' open':'').'><summary class="btn">'.icon('plus').'Nouveau dossier</summary><div style="margin-top:14px">'.$form('<label>Type de dossier</label><select name="type">'.implode('',array_map(fn($k)=>'<option value="'.$k.'"'.(($_GET['new']??'')===$k?' selected':'').'>'.e(TYPES[$k][0]).' ('.$k.')</option>',array_keys(STEPS))).'</select><label>Intitulé du projet</label><input name="titre" placeholder="Ex : Aménagement des combles" required><label>Adresse du projet</label><input name="adresse" required><label>Numéro de parcelle</label><input name="parcelle" required><button>'.icon('check').'Créer le dossier</button>').'</div></details>'
 .tools('dash',$q,$st,'Rechercher par intitulé ou adresse…').($h?'<div class="grid">'.$h.'</div>':'<div class="c empty">'.icon('file').'<p>'.($q!==''||$st!==''?'Aucun dossier ne correspond à votre recherche.':'Aucun dossier pour le moment. Créez-en un pour commencer.').'</p></div>').pager($tot,$per,$pg,'index.php?a=dash&q='.urlencode($q).'&st='.urlencode($st)));exit;}

if($a==='notifs'){
 if($post){db()->prepare('UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL')->execute([$u['id']]);flash('success','Toutes les notifications sont marquées comme lues.');go('notifs');}
 $s=db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50');$s->execute([$u['id']]);$h='';
 foreach($s as $n)$h.='<a class="c nt'.($n['read_at']?'':' new').'" href="index.php?a=nopen&id='.$n['id'].'">'.icon('bell').'<div><span>'.($n['read_at']?'':'<b>').e($n['msg']).($n['read_at']?'':'</b>').'</span><br><small>'.e(substr($n['created_at'],0,16)).'</small></div></a>';
 page('Notifications','<div class="ph"><div><h2>Notifications</h2><p class="mu">Les 50 dernières, de la plus récente à la plus ancienne.</p></div>'.($h&&unread()?$form('<button class="ghost">'.icon('check').'Tout marquer comme lu</button>'):'').'</div>'.($h?:'<div class="c empty">'.icon('bell').'<p>Aucune notification pour le moment.</p></div>'));exit;}
if($a==='nopen'){$s=db()->prepare('SELECT * FROM notifications WHERE id=? AND user_id=?');$s->execute([(int)($_GET['id']??0),$u['id']]);$n=$s->fetch();
 if($n){db()->prepare('UPDATE notifications SET read_at=NOW() WHERE id=?')->execute([$n['id']]);if(strpos($n['link'],'a=')===0){header('Location: index.php?'.$n['link']);exit;}}go('notifs');}
if($a==='profile'){
 if($post){$act=(string)($_POST['act']??'');
  if($act==='info'){$nm=mb_substr(trim((string)($_POST['name']??'')),0,120);$em=strtolower(trim((string)($_POST['email']??'')));$ph=mb_substr(trim((string)($_POST['phone']??'')),0,30);
   if($nm===''||!filter_var($em,FILTER_VALIDATE_EMAIL))flash('danger','Un nom et une adresse e-mail valides sont requis.');
   else try{db()->prepare('UPDATE users SET name=?,email=?,phone=? WHERE id=?')->execute([$nm,$em,$ph?:null,$u['id']]);flash('success','Profil mis à jour.');}catch(PDOException){flash('danger','Cette adresse e-mail est déjà utilisée.');}}
  elseif($act==='pass'){if(!password_verify((string)($_POST['cur']??''),$u['pass_hash']))flash('danger','Le mot de passe actuel est incorrect.');
   elseif(strlen((string)($_POST['new']??''))<10||($_POST['new']??'')!==($_POST['new2']??''))flash('danger','Nouveau mot de passe : 10 caractères minimum, identique dans les deux champs.');
   else{db()->prepare('UPDATE users SET pass_hash=? WHERE id=?')->execute([password_hash((string)$_POST['new'],PASSWORD_DEFAULT),$u['id']]);session_regenerate_id(true);flash('success','Mot de passe modifié.');}}
  elseif($act==='del'){if(!password_verify((string)($_POST['cur']??''),$u['pass_hash']))flash('danger','Le mot de passe est incorrect : compte non supprimé.');
   elseif($er=deleteAccount($u))flash('danger',$er);
   else{session_destroy();header('Location: index.php?a=login');exit;}}
  elseif($act==='consent'){recordConsent((int)$u['id'],!empty($_POST['analytics']),!empty($_POST['marketing']));flash('success','Vos choix sont enregistrés.');}
  go('profile');}
 $rl=['declarant'=>'Déclarant','gestionnaire'=>'Gestionnaire','admin'=>'Administrateur'][$u['role']];
 page('Mon profil','<div class="ph"><div><h2>Mon profil</h2><p class="mu">Rôle : '.e($rl).' · inscrit le '.e(substr($u['created_at'],0,10)).'</p></div></div>'
 .'<div class="c"><h3>Informations personnelles</h3>'.$form('<input type="hidden" name="act" value="info"><label>Nom complet</label><input name="name" value="'.e($u['name']).'" required><label>Adresse e-mail</label><input type="email" name="email" value="'.e($u['email']).'" required><label>Téléphone (facultatif)</label><input name="phone" value="'.e($u['phone']??'').'" autocomplete="tel"><button>'.icon('check').'Enregistrer</button>').'</div>'
 .'<div class="c"><h3>Mot de passe</h3>'.$form('<input type="hidden" name="act" value="pass"><label>Mot de passe actuel</label><input type="password" name="cur" autocomplete="current-password" required><label>Nouveau mot de passe (10 caractères minimum)</label><input type="password" name="new" minlength="10" autocomplete="new-password" required><label>Confirmer le nouveau mot de passe</label><input type="password" name="new2" minlength="10" autocomplete="new-password" required><button>'.icon('check').'Modifier le mot de passe</button>').'</div>'
 .'<div class="c"><h3>Mes données et consentements</h3><p class="hp">Conditions acceptées : version '.e((string)$u['consent_version']).'. '.legalLinks().'</p>'.$form('<input type="hidden" name="act" value="consent"><input type="hidden" name="cgu" value="1">'.consentBoxes((bool)$u['opt_analytics'],(bool)$u['opt_marketing'],false).'<button>'.icon('check').'Enregistrer mes choix</button>').'<p style="margin-top:10px"><a class="b ghost" href="index.php?a=export">'.icon('dl').'Télécharger mes données (JSON)</a></p></div>'
 .'<div class="c" style="border-color:var(--er)"><h3>Supprimer mon compte</h3><p class="mu">Supprime définitivement votre compte, tous vos dossiers, leurs images, vos objets de bibliothèque et vos consentements.</p>'.$form('<input type="hidden" name="act" value="del"><label>Mot de passe pour confirmer</label><input type="password" name="cur" required><button class="danger" data-type="danger" data-ok="Supprimer mon compte" data-confirm="Supprimer définitivement votre compte et vos données ? Cette action est irréversible.">'.icon('trash').'Supprimer mon compte</button>').'</div>');exit;}
if($a==='export'){header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="mes-donnees-DOSSIERUrba.json"');echo json_encode(exportData($u),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($a==='reopen'&&$post){$d=dossier((int)($_POST['id']??0));
 if(reopenDossier($u,$d))flash('success','Dossier rouvert : modifiez-le puis soumettez-le à nouveau.');else flash('danger','Action impossible sur ce dossier.');
 go('dossier','&id='.$d['id']);}
if($a==='deldoss'&&$post){$d=dossier((int)($_POST['id']??0));$mine=$u['id']==$d['user_id'];
 if(deleteDossier($u,$d)){flash('success','Dossier supprimé.');go($mine?'dash':'admin');}
 flash('danger','Suppression impossible : un dossier payé ne peut pas être supprimé.');go('dossier','&id='.$d['id']);}

if($a==='dossier'){
 $d=dossier((int)($_GET['id']??0));$own=$u['id']==$d['user_id'];$edit=$own&&$d['status']==='brouillon';$ty=ty($d);$tc=txtCode($ty);
 if($post&&$edit){if(isset($_POST['description'])){setDescription($d,(string)$_POST['description']);flash('success','Description enregistrée.');}
  if(isset($_POST['go_submit'])){[$ok,$msg]=submitDossier($u,dossier((int)$d['id']));flash($ok?'success':'danger',$msg);}
  go('dossier','&id='.$d['id']);}
 $d=dossier((int)$d['id']);$p=pieces((int)$d['id']);$list=[];foreach(steps($ty) as $c=>[$t,$k,,$opt])if(!$opt||!empty($p[$c]['sheet']))$list[]="$c : $t";
 $cfg=['id'=>$d['id'],'t'=>csrf(),'edit'=>$edit,'ty'=>$ty,'head'=>HEAD[$ty],'txt'=>$tc?[$tc,mb_strtoupper(steps($ty)[$tc][0])]:null,'desc'=>$d['description'],'list'=>$list,'meta'=>['titre'=>$d['titre'],'adresse'=>$d['adresse'],'parcelle'=>$d['parcelle'],'owner'=>$own?$u['name']:'']];
 $h='<div class="ph"><div><h2>'.e($d['titre']).'</h2><p class="mu"><span class="tk">'.$ty.'</span> '.e(TYPES[$ty][0]).' · '.e($d['adresse']).' – parcelle '.e($d['parcelle']).'</p></div>'.badge($d['status']).'</div><div class="steps">';
 [$cn,$cu]=CERFA[$ty];$h.='<div class="c cerfa"><b>Cerfa n°'.$cn.'</b> – Formulaire officiel<p class="hp">Téléchargez le formulaire, imprimez-le et remplissez-le au stylo. Il se dépose en mairie avec les pièces ci-dessous.</p><a class="b" href="'.e($cu).'" target="_blank" rel="noopener">'.icon('dl').'Accéder au Cerfa et le télécharger ↗</a></div>';
 foreach(steps($ty) as $c=>[$t,$k,$hp,$opt]){$h.='<div class="c"><b>'.$c.'</b> – '.e($t).' '.($opt?'<span class="badge b-mu">Facultatif</span> ':'');
  if($k==='img'){$ok=!empty($p[$c]['sheet']);$h.=($ok?'<span class="ok">✔</span>':($opt?'':'<span class="er">à compléter</span>')).'<p class="hp">'.e($hp).'</p>'.($ok?'<img src="index.php?a=sheet&id='.$d['id'].'&c='.$c.'&s='.$d['status'].'" style="max-width:320px;border:1px solid #ddd">':'');
   if($edit)$h.='<br><a class="b" style="margin-top:8px" href="index.php?a=edit&id='.$d['id'].'&c='.$c.'">'.icon('edit').($ok?'Modifier dans l\'éditeur':'Ouvrir l\'éditeur').'</a>';}
  else{$h.='<p class="hp">'.e($hp).'</p>';$h.=$edit?$form('<textarea name="description" rows="6" placeholder="'.($ty==='PD'?'Décrivez les précautions prises pendant la démolition…':'Décrivez vos travaux : matériaux, couleurs, dimensions…').'">'.e($d['description']).'</textarea><button>Enregistrer</button>'):'<p>'.nl2br(e($d['description'])).'</p>';}
  $h.='</div>';}
$h.='</div><p style="margin-top:18px"><a class="b pdfgo" href="index.php?a=pdf&id='.$d['id'].'">'.icon('dl').($d['status']==='paye'?'Télécharger le PDF complet':'Aperçu PDF (filigrané)').'</a> ';
 if($edit){$mi=array_diff(missing($d,$p),['PDF']);$h.=$mi?'<p class="mu">Pièces obligatoires à compléter avant l\'envoi : '.e(implode(', ',$mi)).'</p>':$form('<input type="hidden" name="go_submit" value="1">'.(($ac=access($u))['ok']?'<p class="hp">'.e($ac['text']).'</p><button class="submitgo">'.icon('check').'Finaliser le dossier et obtenir le PDF</button>':'<p class="hp">'.e($ac['text']).(($pr=dossierPrice($d))!==''?' Prix : '.e($pr).'.':'').'</p><button class="submitgo">Soumettre le dossier pour validation</button>'));}
 $pf=fn($ac,$bt)=>'<form method="post" action="index.php?a='.$ac.'" style="display:inline"><input type="hidden" name="t" value="'.csrf().'"><input type="hidden" name="id" value="'.$d['id'].'">'.$bt.'</form>';$act='';
 if($own&&$d['status']==='en_attente')$act.=$pf('reopen','<button class="ghost" data-confirm="Le dossier repasse en brouillon pour être modifié. Vous devrez le soumettre à nouveau." data-ok="Modifier le dossier">'.icon('edit').'Modifier le dossier</button>');
 if(($own&&$d['status']!=='paye')||$u['role']==='admin')$act.=$pf('deldoss','<button class="danger" data-type="danger" data-ok="Supprimer" data-confirm="Supprimer définitivement ce dossier et toutes ses pièces ? Cette action est irréversible.">'.icon('trash').'Supprimer le dossier</button>');
 page($d['titre'],$h.'</p>'.($act?'<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">'.$act.'</div>':'').'<div id="auto" data-cfg="'.e(json_encode($cfg)).'"></div>','assets/editor.js');exit;}

if($a==='edit'){$d=dossier((int)($_GET['id']??0));$c=(string)($_GET['c']??'');$ty=ty($d);$st=steps($ty)[$c]??null;
 if($u['id']!=$d['user_id']||$d['status']!=='brouillon'||!$st||$st[1]!=='img'){http_response_code(403);exit('Refusé');}
 $p=pieces((int)$d['id']);$cfg=['id'=>$d['id'],'c'=>$c,'t'=>csrf(),'admin'=>$u['role']==='admin','title'=>mb_strtoupper($st[0]),'head'=>HEAD[$ty],'wm'=>true,'emojis'=>emojis(),'base'=>!empty($p[$c]['file']),'data'=>json_decode((string)($p[$c]['data']??'null')),'meta'=>['titre'=>$d['titre'],'adresse'=>$d['adresse'],'parcelle'=>$d['parcelle'],'owner'=>$u['name']]];
 page('Éditeur '.$c,'<h2>'.$c.' – '.e($st[0]).'</h2><p class="hp">'.e($st[2]).'</p><div id="ed" data-cfg="'.e(json_encode($cfg)).'"></div>','assets/editor.js');exit;}

if($a==='save'&&$post){header('Content-Type: application/json');$d=dossier((int)($_POST['id']??0));$c=(string)($_POST['c']??'');
 if($er=savePiece($u,$d,$c,$_FILES['sheet']??null,$_FILES['base']??null,(string)($_POST['data']??'{}'),isset($_POST['desc'])?(string)$_POST['desc']:null)){http_response_code($er==='refusé'?403:200);echo json_encode(['ok'=>0,'err'=>$er]);exit;}
 if($c!==ty($d).'0'&&$c!==txtCode(ty($d)))flash('success','Étape '.$c.' enregistrée.');
 echo '{"ok":1}';exit;}

if($a==='img'||$a==='sheet'){$d=dossier((int)($_GET['id']??0));sheetOut($d,(string)($_GET['c']??''),$a==='img'?'file':'sheet');exit;}

if($a==='pdf'){try{$d=dossier((int)($_GET['id']??0));[$out,$fn]=dossierPdf($d);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$fn.'"');header('Cache-Control: private, no-store');echo $out;exit;}
 catch(Throwable $x){error_log((string)$x);flash('danger','Génération du PDF impossible : '.$x->getMessage());go('dossier','&id='.(int)($_GET['id']??0));}}

if($a==='lib'){header('Content-Type: application/json');$s=db()->prepare('SELECT id,name,wcm,hcm,shared,(user_id=?) AS mine FROM library WHERE user_id=? OR shared=1 ORDER BY id DESC');$s->execute([$u['id'],$u['id']]);echo json_encode($s->fetchAll());exit;}
if($a==='libimg'){$s=db()->prepare('SELECT * FROM library WHERE id=? AND (user_id=? OR shared=1)');$s->execute([(int)($_GET['id']??0),$u['id']]);$r=$s->fetch();if(!$r){http_response_code(404);exit;}header('Content-Type: image/png');header('Cache-Control: private, max-age=86400');readfile(DATA.'/lib/'.basename($r['file']));exit;}
if($a==='libdel'&&$post){$s=db()->prepare('SELECT * FROM library WHERE id=?');$s->execute([(int)($_POST['id']??0)]);$r=$s->fetch();if($r&&($r['user_id']==$u['id']||$u['role']==='admin')){@unlink(DATA.'/lib/'.basename($r['file']));db()->prepare('DELETE FROM library WHERE id=?')->execute([$r['id']]);}echo '{"ok":1}';exit;}
if($a==='libsave'&&$post){header('Content-Type: application/json');$f=$_FILES['png']??null;
 if(!$f||$f['error']!==0||$f['size']>4*1048576){echo '{"ok":0,"err":"fichier refusé"}';exit;}
 $raw=file_get_contents($f['tmp_name']);if((new finfo(FILEINFO_MIME_TYPE))->buffer($raw)!=='image/png'||!($im=@imagecreatefromstring($raw))){echo '{"ok":0,"err":"image invalide"}';exit;}
 $n=db()->prepare('SELECT COUNT(*) FROM library WHERE user_id=?');$n->execute([$u['id']]);if($n->fetchColumn()>=100){echo '{"ok":0,"err":"bibliothèque pleine (100)"}';exit;}
 $k=min(1,1000/max(imagesx($im),imagesy($im)));if($k<1){$t=imagecreatetruecolor((int)(imagesx($im)*$k),(int)(imagesy($im)*$k));imagealphablending($t,false);imagesavealpha($t,true);imagefill($t,0,0,imagecolorallocatealpha($t,0,0,0,127));imagecopyresampled($t,$im,0,0,0,0,imagesx($t),imagesy($t),imagesx($im),imagesy($im));$im=$t;}else{imagealphablending($im,false);imagesavealpha($im,true);}
 $fn=bin2hex(random_bytes(16)).'.png';imagepng($im,DATA.'/lib/'.$fn,6);$sh=($u['role']==='admin'&&!empty($_POST['shared']))?1:0;
 db()->prepare('INSERT INTO library(user_id,name,file,wcm,hcm,shared,created_at) VALUES(?,?,?,?,?,?,NOW())')->execute([$u['id'],mb_substr(trim((string)($_POST['name']??'Objet')),0,60)?:'Objet',$fn,max(0,min(5000,(int)($_POST['wcm']??0))),max(0,min(5000,(int)($_POST['hcm']??0))),$sh]);
 echo '{"ok":1}';exit;}

if($a==='admin'){need(true);
 if($post&&isset($_POST['id'])){$id=(int)$_POST['id'];if($_POST['st']==='paye'){unlockDossier($id,'paiement');flash('success','Paiement validé : le filigrane est retiré du PDF.');}
  else{db()->prepare("UPDATE dossiers SET status='en_attente',unlocked_via=NULL,unlocked_at=NULL WHERE id=?")->execute([$id]);$dd=db()->prepare('SELECT user_id,titre FROM dossiers WHERE id=?');$dd->execute([$id]);if($dd=$dd->fetch())notify((int)$dd['user_id'],'La validation de « '.$dd['titre'].' » a été annulée.','a=dossier&id='.$id);flash('success','Validation annulée : le filigrane est rétabli.');}go('admin');}
 $q=trim((string)($_GET['q']??''));$st=(string)($_GET['st']??'');$pg=max(1,(int)($_GET['p']??1));$per=10;$w='1=1';$args=[];
 if($q!==''){$w.=' AND (d.titre LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';array_push($args,"%$q%","%$q%","%$q%");}
 if(in_array($st,['brouillon','en_attente','paye'],true)){$w.=' AND d.status=?';$args[]=$st;}
 $c=db()->prepare("SELECT COUNT(*) FROM dossiers d JOIN users u ON u.id=d.user_id WHERE $w");$c->execute($args);$tot=(int)$c->fetchColumn();
 $s=db()->prepare("SELECT d.*,u.name,u.email FROM dossiers d JOIN users u ON u.id=d.user_id WHERE $w ORDER BY (d.status='en_attente') DESC,d.id DESC LIMIT $per OFFSET ".(($pg-1)*$per));$s->execute($args);$r='';
 foreach($s as $d){$pa=$d['status']==='paye';$r.='<tr><td><a href="index.php?a=dossier&id='.$d['id'].'"><b>'.e($d['titre']).'</b></a><br><small><span class="tk">'.ty($d).'</span> '.e($d['adresse']).'</small></td><td>'.e($d['name']).'<br><small>'.e($d['email']).'</small></td><td>'.badge($d['status']).($d['unlocked_via']?'<br><small>'.e($d['unlocked_via']).'</small>':'').'</td><td>'.e(substr($d['created_at'],0,10)).'</td><td>';
  if($d['status']!=='brouillon')$r.=$form('<input type="hidden" name="id" value="'.$d['id'].'"><input type="hidden" name="st" value="'.($pa?'en_attente':'paye').'"><button class="'.($pa?'ghost':'').'" data-confirm="'.($pa?'Annuler la validation du paiement ?':'Confirmer la réception du paiement ?').'">'.icon($pa?'undo':'check').($pa?'Annuler':'Valider le paiement').'</button>');$r.='</td></tr>';}
 page('Gestion','<div class="ph"><div><h2>Gestion des dossiers</h2><p class="mu">Validez les paiements pour retirer le filigrane et autoriser le téléchargement.</p></div></div>'.stats(db()->query('SELECT status,COUNT(*) FROM dossiers GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR)).tools('admin',$q,$st,'Dossier, déclarant ou e-mail…')
 .($r?'<div class="c" style="padding:0;overflow:auto"><table><tr><th>Dossier</th><th>Déclarant</th><th>Statut</th><th>Créé le</th><th></th></tr>'.$r.'</table></div>':'<div class="c empty">Aucun dossier trouvé.</div>').pager($tot,$per,$pg,'index.php?a=admin&q='.urlencode($q).'&st='.urlencode($st)));exit;}

if($a==='users'){need(false,true);
 if($post&&isset($_POST['id'])&&(int)$_POST['id']!==(int)$u['id']&&in_array($_POST['role'],['declarant','gestionnaire','admin'],true)){db()->prepare('UPDATE users SET role=? WHERE id=?')->execute([$_POST['role'],(int)$_POST['id']]);flash('success','Rôle mis à jour.');go('users');}
 $q=trim((string)($_GET['q']??''));$pg=max(1,(int)($_GET['p']??1));$per=10;$w='1=1';$args=[];if($q!==''){$w.=' AND (name LIKE ? OR email LIKE ?)';array_push($args,"%$q%","%$q%");}
 $c=db()->prepare("SELECT COUNT(*) FROM users WHERE $w");$c->execute($args);$tot=(int)$c->fetchColumn();$s=db()->prepare("SELECT * FROM users WHERE $w ORDER BY id LIMIT $per OFFSET ".(($pg-1)*$per));$s->execute($args);$r='';
 foreach($s as $x){$r.='<tr><td><b>'.e($x['name']).'</b><br><small>'.e($x['email']).'</small></td><td>'.($x['id']==$u['id']?'<span class="badge b-ok">'.e($x['role']).' (vous)</span>':$form('<input type="hidden" name="id" value="'.$x['id'].'"><div style="display:flex;gap:8px"><select name="role" style="margin:0">'.implode('',array_map(fn($y)=>'<option'.($y===$x['role']?' selected':'').'>'.$y.'</option>',['declarant','gestionnaire','admin'])).'</select><button>'.icon('check').'Appliquer</button></div>')).'</td><td>'.e(substr($x['created_at'],0,10)).'</td></tr>';}
 page('Utilisateurs','<div class="ph"><div><h2>Utilisateurs</h2><p class="mu">Attribuez les rôles : le gestionnaire valide les paiements, l’administrateur gère tout.</p></div></div>'.tools('users',$q,'','Nom ou e-mail…',false).'<div class="c" style="padding:0;overflow:auto"><table><tr><th>Utilisateur</th><th>Rôle</th><th>Inscrit le</th></tr>'.$r.'</table></div>'.pager($tot,$per,$pg,'index.php?a=users&q='.urlencode($q)));exit;}
if($a==='library'){need(false,true);
 if($post&&isset($_POST['emojis'])){setSetting('emojis',mb_substr(trim((string)preg_replace('/\s+/u',' ',(string)$_POST['emojis'])),0,1000));flash('success','Liste des émojis enregistrée.');go('library');}
 page('Bibliothèque','<div class="ph"><div><h2>Bibliothèque partagée</h2><p class="mu">Les images et émojis ajoutés ici sont proposés à tous les utilisateurs dans l’éditeur.</p></div></div>'
 .'<div class="c"><h3>Images partagées</h3><p class="hp">Fenêtres, portes, enseignes, arbres, véhicules… Importez une photo : l’arrière-plan peut être retiré automatiquement.</p><div id="libadm" data-cfg="'.e(json_encode(['t'=>csrf()])).'"></div></div>'
 .'<div class="c"><h3>Émojis et symboles</h3>'.$form('<label>Émojis proposés dans l’éditeur, séparés par des espaces</label><textarea name="emojis" rows="3" style="font-size:22px">'.e(implode(' ',emojis())).'</textarea><button>'.icon('check').'Enregistrer</button>').'</div>','assets/editor.js');exit;}
if($a==='access'){need(false,true);
 if($post){$act=(string)($_POST['act']??'');
  if($act==='cfg'){foreach(array_keys(ACC_DEFAULTS) as $k)if(isset($_POST[$k])){$v=mb_substr(trim((string)$_POST[$k]),0,255);
    if(in_array($k,['mode','after'],true)&&!isset(MODES[$v]))continue;if($k==='free_until'&&$v!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v))continue;
    if(preg_match('/^(price_|sub_)/',$k)&&$v!==''){$v=str_replace(',','.',$v);if(!is_numeric($v))continue;}
    if(in_array($k,['quota_n','trial_days'],true))$v=(string)max(0,min(3650,(int)$v));setSetting('acc_'.$k,$v);}flash('success','Réglages d’accès enregistrés.');}
  elseif($act==='grant'){$s=db()->prepare('SELECT id,name FROM users WHERE email=?');$s->execute([strtolower(trim((string)($_POST['email']??'')))]);$x=$s->fetch();$j=(int)($_POST['days']??0);
   if(!$x)flash('danger','Aucun utilisateur avec cet e-mail.');
   else{db()->prepare("INSERT INTO subscriptions(user_id,plan,platform,status,starts_at,ends_at,note,created_at) VALUES(?,'offert','admin','active',NOW(),".($j>0?'DATE_ADD(NOW(),INTERVAL '.$j.' DAY)':'NULL').",?,NOW())")->execute([$x['id'],mb_substr(trim((string)($_POST['note']??'')),0,255)]);notify((int)$x['id'],'Un accès '.APP.' vous a été offert'.($j>0?' pour '.$j.' jours':'').'.','a=dash');flash('success','Accès offert à '.$x['name'].'.');}}
  elseif($act==='revoke'){db()->prepare("UPDATE subscriptions SET status='revoque',ends_at=NOW() WHERE id=?")->execute([(int)($_POST['id']??0)]);flash('success','Accès retiré.');}
  go('access');}
 $sel=fn($n,$v,$opts)=>'<select name="'.$n.'">'.implode('',array_map(fn($k)=>'<option value="'.$k.'"'.($k===$v?' selected':'').'>'.e($opts[$k]).'</option>',array_keys($opts))).'</select>';
 $in=fn($n,$l,$ph='',$t='text')=>'<label>'.$l.'</label><input type="'.$t.'" name="'.$n.'" value="'.e(acc($n)).'" placeholder="'.e($ph).'">';
 $cur=accMode();$st=db()->query("SELECT COALESCE(unlocked_via,'—') v,COUNT(*) n FROM dossiers WHERE status='paye' GROUP BY v")->fetchAll(PDO::FETCH_KEY_PAIR);
 $subs=db()->query("SELECT s.*,u.name,u.email FROM subscriptions s JOIN users u ON u.id=s.user_id WHERE s.status='active' AND (s.ends_at IS NULL OR s.ends_at>NOW()) ORDER BY s.id DESC LIMIT 100")->fetchAll();$r='';
 foreach($subs as $x)$r.='<tr><td><b>'.e($x['name']).'</b><br><small>'.e($x['email']).'</small></td><td>'.e($x['plan']).' · '.e($x['platform']).'<br><small>'.e($x['note']).'</small></td><td>'.($x['ends_at']?e(date('d/m/Y',strtotime($x['ends_at']))):'Sans limite').'</td><td>'.$form('<input type="hidden" name="act" value="revoke"><input type="hidden" name="id" value="'.$x['id'].'"><button class="ghost" data-confirm="Retirer cet accès ?">Retirer</button>').'</td></tr>';
 page('Accès et tarifs','<div class="ph"><div><h2>Accès et tarifs</h2><p class="mu">Formule appliquée aujourd’hui : <b>'.e(MODES[$cur]).'</b>. Les dossiers déjà débloqués le restent quel que soit le changement.</p></div></div>'
 .'<div class="c"><h3>Formule d’accès</h3>'.$form('<input type="hidden" name="act" value="cfg"><label>Formule</label>'.$sel('mode',acc('mode'),MODES)
  .'<div class="g2"><div>'.$in('free_until','Fin de la gratuité (facultatif)','','date').'</div><div><label>Formule après la gratuité</label>'.$sel('after',acc('after'),array_diff_key(MODES,['gratuit'=>1])).'</div></div>'
  .'<div class="g2"><div>'.$in('quota_n','Limité : nombre de dossiers gratuits','3','number').'</div><div><label>Limité : période</label>'.$sel('quota_period',acc('quota_period'),['mois'=>'Par mois','total'=>'Au total']).'</div></div>'
  .$in('trial_days','Essai : durée en jours après l’inscription','30','number')
  .'<h3 style="margin-top:14px">Tarifs (en euros)</h3><div class="g3"><div>'.$in('price_DP','Dossier DP').'</div><div>'.$in('price_AP','Dossier AP').'</div><div>'.$in('price_PD','Dossier PD').'</div></div>'
  .'<div class="g2"><div>'.$in('sub_month','Abonnement mensuel').'</div><div>'.$in('sub_year','Abonnement annuel').'</div></div>'
  .'<h3 style="margin-top:14px">Application mobile</h3><p class="hp">Identifiants des produits à créer dans Google Play / App Store (phase mobile).</p><div class="g2"><div>'.$in('prod_sub_month','Produit abonnement mensuel','dossierurba_mensuel').'</div><div>'.$in('prod_sub_year','Produit abonnement annuel','dossierurba_annuel').'</div></div>'
  .'<div class="g2"><div>'.$in('app_min_android','Version minimale Android','1.0.0').'</div><div>'.$in('app_min_ios','Version minimale iPhone','1.0.0').'</div></div>'.$in('app_message','Message affiché dans l’application (facultatif)')
  .'<button>'.icon('check').'Enregistrer</button>').'</div>'
 .'<div class="c"><h3>Dossiers débloqués par mode</h3><p>'.($st?implode(' · ',array_map(fn($k,$v)=>e($k).' : <b>'.(int)$v.'</b>',array_keys($st),$st)):'Aucun pour le moment.').'</p></div>'
 .'<div class="c"><h3>Abonnements et accès offerts</h3>'.$form('<input type="hidden" name="act" value="grant"><div class="g3"><div><label>E-mail de l’utilisateur</label><input type="email" name="email" required></div><div><label>Durée en jours (0 = sans limite)</label><input type="number" name="days" value="30" min="0"></div><div><label>Note</label><input name="note" placeholder="Ex : partenaire, test…"></div></div><button>'.icon('plus').'Offrir un accès</button>')
 .($r?'<div style="overflow:auto;margin-top:12px"><table><tr><th>Utilisateur</th><th>Formule</th><th>Fin</th><th></th></tr>'.$r.'</table></div>':'<p class="mu" style="margin-top:10px">Aucun abonnement actif.</p>').'</div>');exit;}
if($a==='legal'){need(false,true);
 if($post){foreach(['owner','contact','host'] as $k)setSetting('legal_'.$k,mb_substr(trim((string)($_POST[$k]??'')),0,255));if(isset($_POST['mail_from']))setSetting('mail_from',filter_var(trim((string)$_POST['mail_from']),FILTER_VALIDATE_EMAIL)?trim((string)$_POST['mail_from']):'');
  foreach(['cgu','privacy'] as $k)setSetting('legal_'.$k,mb_substr(str_replace("\r",'',(string)($_POST[$k]??'')),0,60000));
  if(!empty($_POST['bump'])){setSetting('legal_version',date('Y-m-d.His'));flash('success','Nouvelle version publiée : les utilisateurs devront accepter à nouveau.');}else flash('success','Textes enregistrés (même version).');go('legal');}
 $raw=fn($k)=>setting('legal_'.$k,LEGAL_DEFAULTS[$k]);$v=legalVersion();$ok=db()->prepare('SELECT COUNT(*) FROM users WHERE consent_version=?');$ok->execute([$v]);$tot=(int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
 $rc='';foreach(db()->query('SELECT c.*,u.email FROM consents c JOIN users u ON u.id=c.user_id ORDER BY c.id DESC LIMIT 20') as $x)$rc.='<tr><td>'.e($x['email']).'</td><td>'.e($x['version']).'</td><td>'.($x['analytics']?'oui':'non').' / '.($x['marketing']?'oui':'non').'</td><td>'.e($x['source']).'</td><td>'.e(substr($x['created_at'],0,16)).'</td></tr>';
 page('Textes légaux','<div class="ph"><div><h2>Textes légaux et consentements</h2><p class="mu">Version en vigueur : <b>'.e($v).'</b> · acceptée par '.(int)$ok->fetchColumn().' utilisateur(s) sur '.$tot.'. Pages publiques : '.legalLinks().'</p></div></div>'
 .'<div class="c"><p class="hp">⚠️ Textes à faire relire par un juriste ou un DPO. Variables utilisables : {APP}, {RESPONSABLE}, {CONTACT}, {HEBERGEUR}, {VERSION}. Mise en forme : ligne vide entre paragraphes, « ## » pour un titre, « - » pour une liste.</p>'.$form(
  '<div class="g3"><div><label>Responsable du traitement (nom, adresse)</label><input name="owner" value="'.e(setting('legal_owner')).'"></div><div><label>Contact (e-mail)</label><input name="contact" value="'.e(setting('legal_contact')).'"></div><div><label>Expéditeur des e-mails</label><input name="mail_from" type="email" value="'.e(setting('mail_from')).'" placeholder="no-reply@votre-domaine"></div><div><label>Hébergeur et pays des serveurs</label><input name="host" value="'.e(setting('legal_host')).'"></div></div>'
  .'<label>Conditions générales d’utilisation</label><textarea name="cgu" rows="14">'.e($raw('cgu')).'</textarea><label>Politique de confidentialité</label><textarea name="privacy" rows="14">'.e($raw('privacy')).'</textarea>'
  .'<label style="display:flex;gap:8px;align-items:center;margin:10px 0"><input type="checkbox" name="bump" value="1" style="width:auto;margin:0"> Publier comme nouvelle version : tous les utilisateurs devront accepter à nouveau</label><button>'.icon('check').'Enregistrer</button>').'</div>'
 .'<div class="c"><h3>Derniers consentements</h3>'.($rc?'<div style="overflow:auto"><table><tr><th>Utilisateur</th><th>Version</th><th>Audience / e-mails</th><th>Source</th><th>Date</th></tr>'.$rc.'</table></div>':'<p class="mu">Aucun pour le moment.</p>').'</div>');exit;}
if($a==='log'){need(false,true);$f=DATA.'/error.log';page('Journal','<h2>Journal d\'erreurs</h2><pre style="white-space:pre-wrap">'.e(is_file($f)?implode('',array_slice(file($f),-30)):'Vide').'</pre>');exit;}
go('dash');
