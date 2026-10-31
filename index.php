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
   else try{db()->prepare("INSERT INTO users(email,pass_hash,name,role,created_at) VALUES(?,?,?,'declarant',NOW())")->execute([$em,password_hash((string)$_POST['pass'],PASSWORD_DEFAULT),mb_substr(trim((string)$_POST['name']),0,120)]);notifyStaff('Nouvel utilisateur inscrit : '.mb_substr(trim((string)$_POST['name']),0,60),'a=users');$err='Compte créé, connectez-vous.';$a='login';}catch(PDOException){$err='Email déjà utilisé.';}}}
 $reg=$a==='register';if($err)flash($err==='Compte créé, connectez-vous.'?'success':'danger',$err);
 page($reg?'Inscription':'Connexion','<h2>'.($reg?'Créer un compte':'Connexion').'</h2>'.$form(($reg?'Nom<input name="name" required>':'').'Email<input name="email" type="email" required>Mot de passe<input name="pass" type="password" required><button>'.($reg?'Créer':'Entrer').'</button>').'<p><a href="index.php?a='.($reg?'login':'register').'">'.($reg?'J\'ai déjà un compte':'Créer un compte').'</a></p><p class="zl"><a href="'.e(ZONE).'" target="_blank" rel="noopener">Voir dans quelle zone se trouve votre parcelle ↗</a></p>');exit;}

$u=need();

if($a==='dash'){
 if($post&&isset($_POST['titre'])){$tp=(string)($_POST['type']??'DP');if(!isset(STEPS[$tp]))$tp='DP';$s=db()->prepare('INSERT INTO dossiers(user_id,type,titre,adresse,parcelle,created_at) VALUES(?,?,?,?,?,NOW())');$s->execute([$u['id'],$tp,mb_substr(trim((string)$_POST['titre']),0,150),mb_substr(trim((string)$_POST['adresse']),0,255),mb_substr(trim((string)$_POST['parcelle']),0,40)]);flash('success','Dossier créé : complétez les étapes une par une.');go('dossier','&id='.db()->lastInsertId());}
 $q=trim((string)($_GET['q']??''));$st=(string)($_GET['st']??'');$pg=max(1,(int)($_GET['p']??1));$per=9;$w='user_id=?';$args=[$u['id']];
 if($q!==''){$w.=' AND (titre LIKE ? OR adresse LIKE ?)';array_push($args,"%$q%","%$q%");}
 if(in_array($st,['brouillon','en_attente','paye'],true)){$w.=' AND status=?';$args[]=$st;}
 $c=db()->prepare("SELECT COUNT(*) FROM dossiers WHERE $w");$c->execute($args);$tot=(int)$c->fetchColumn();
 $s=db()->prepare("SELECT *,(SELECT COUNT(*) FROM pieces p WHERE p.dossier_id=dossiers.id AND p.sheet IS NOT NULL AND p.code IN ('".implode("','",reqImg())."')) AS done FROM dossiers WHERE $w ORDER BY id DESC LIMIT $per OFFSET ".(($pg-1)*$per));$s->execute($args);$h='';
 foreach($s as $d){$nr=count(reqImg(ty($d)));$h.='<a class="dc" href="index.php?a=dossier&id='.$d['id'].'"><div class="l"><div class="ph" style="margin:0"><b>'.e($d['titre']).'</b>'.badge($d['status']).'</div><span class="mu">'.e($d['adresse']).'</span><div class="bar"><i style="width:'.round($d['done']/$nr*100).'%"></i></div><small>'.(int)$d['done'].' / '.$nr.' pièces obligatoires complétées</small></div><div class="r" title="'.e(TYPES[ty($d)][0]).'">'.ty($d).'</div></a>';}
 $n=db()->prepare('SELECT status,COUNT(*) FROM dossiers WHERE user_id=? GROUP BY status');$n->execute([$u['id']]);
 page('Accueil','<div class="c hero"><div><h2>Bienvenue sur '.APP.'</h2><p class="mu">Constituez vous-même votre dossier de demande d’autorisation d’urbanisme, étape par étape.</p></div><a class="btn" href="'.e(ZONE).'" target="_blank" rel="noopener">'.icon('search').'Voir dans quelle zone se trouve votre parcelle ↗</a></div>'
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
  elseif($act==='del'){$pd=db()->prepare("SELECT COUNT(*) FROM dossiers WHERE user_id=? AND status='paye'");$pd->execute([$u['id']]);$ad=(int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
   if(!password_verify((string)($_POST['cur']??''),$u['pass_hash']))flash('danger','Le mot de passe est incorrect : compte non supprimé.');
   elseif((int)$pd->fetchColumn()>0)flash('danger','Des dossiers payés sont rattachés à ce compte : contactez l’administrateur pour le supprimer.');
   elseif($u['role']==='admin'&&$ad<2)flash('danger','Vous êtes le seul administrateur : nommez-en un autre avant de supprimer ce compte.');
   else{$ds=db()->prepare('SELECT id FROM dossiers WHERE user_id=?');$ds->execute([$u['id']]);foreach($ds->fetchAll(PDO::FETCH_COLUMN) as $did){foreach(pieces((int)$did) as $pc)foreach(['file','sheet'] as $k)if(!empty($pc[$k]))@unlink(__DIR__.'/storage/img/'.basename($pc[$k]));}
    $ls=db()->prepare('SELECT file FROM library WHERE user_id=?');$ls->execute([$u['id']]);foreach($ls->fetchAll(PDO::FETCH_COLUMN) as $lf)@unlink(__DIR__.'/storage/lib/'.basename($lf));
    foreach(['DELETE FROM dossiers WHERE user_id=?','DELETE FROM library WHERE user_id=?','DELETE FROM users WHERE id=?'] as $q)db()->prepare($q)->execute([$u['id']]);
    session_destroy();header('Location: index.php?a=login');exit;}}
  go('profile');}
 $rl=['declarant'=>'Déclarant','gestionnaire'=>'Gestionnaire','admin'=>'Administrateur'][$u['role']];
 page('Mon profil','<div class="ph"><div><h2>Mon profil</h2><p class="mu">Rôle : '.e($rl).' · inscrit le '.e(substr($u['created_at'],0,10)).'</p></div></div>'
 .'<div class="c"><h3>Informations personnelles</h3>'.$form('<input type="hidden" name="act" value="info"><label>Nom complet</label><input name="name" value="'.e($u['name']).'" required><label>Adresse e-mail</label><input type="email" name="email" value="'.e($u['email']).'" required><label>Téléphone (facultatif)</label><input name="phone" value="'.e($u['phone']??'').'" autocomplete="tel"><button>'.icon('check').'Enregistrer</button>').'</div>'
 .'<div class="c"><h3>Mot de passe</h3>'.$form('<input type="hidden" name="act" value="pass"><label>Mot de passe actuel</label><input type="password" name="cur" autocomplete="current-password" required><label>Nouveau mot de passe (10 caractères minimum)</label><input type="password" name="new" minlength="10" autocomplete="new-password" required><label>Confirmer le nouveau mot de passe</label><input type="password" name="new2" minlength="10" autocomplete="new-password" required><button>'.icon('check').'Modifier le mot de passe</button>').'</div>'
 .'<div class="c" style="border-color:var(--er)"><h3>Supprimer mon compte</h3><p class="mu">Supprime définitivement votre compte, vos dossiers non payés, leurs images et vos objets de bibliothèque.</p>'.$form('<input type="hidden" name="act" value="del"><label>Mot de passe pour confirmer</label><input type="password" name="cur" required><button class="danger" data-type="danger" data-ok="Supprimer mon compte" data-confirm="Supprimer définitivement votre compte et vos données ? Cette action est irréversible.">'.icon('trash').'Supprimer mon compte</button>').'</div>');exit;}
if($a==='reopen'&&$post){$d=dossier((int)($_POST['id']??0));
 if($u['id']==$d['user_id']&&$d['status']==='en_attente'){db()->prepare("UPDATE dossiers SET status='brouillon' WHERE id=?")->execute([$d['id']]);flash('success','Dossier rouvert : modifiez-le puis soumettez-le à nouveau.');notifyStaff('Dossier retiré pour modification : '.$d['titre'].' ('.$u['name'].')','a=dossier&id='.(int)$d['id']);}else flash('danger','Action impossible sur ce dossier.');
 go('dossier','&id='.$d['id']);}
if($a==='deldoss'&&$post){$d=dossier((int)($_POST['id']??0));$mine=$u['id']==$d['user_id'];
 if(($mine&&$d['status']!=='paye')||$u['role']==='admin'){foreach(pieces((int)$d['id']) as $pc)foreach(['file','sheet'] as $k)if(!empty($pc[$k]))@unlink(__DIR__.'/storage/img/'.basename($pc[$k]));
  db()->prepare('DELETE FROM dossiers WHERE id=?')->execute([$d['id']]);flash('success','Dossier supprimé.');go($mine?'dash':'admin');}
 flash('danger','Suppression impossible : un dossier payé ne peut pas être supprimé.');go('dossier','&id='.$d['id']);}

if($a==='dossier'){
 $d=dossier((int)($_GET['id']??0));$own=$u['id']==$d['user_id'];$edit=$own&&$d['status']==='brouillon';$ty=ty($d);$tc=txtCode($ty);
 if($post&&$edit){if(isset($_POST['description'])){db()->prepare('UPDATE dossiers SET description=? WHERE id=?')->execute([mb_substr((string)$_POST['description'],0,4000),$d['id']]);db()->prepare("UPDATE pieces SET sheet=NULL WHERE dossier_id=? AND code=?")->execute([$d['id'],(string)$tc]);flash('success','Description enregistrée.');}
  if(isset($_POST['go_submit'])){if(complete(dossier((int)$d['id']),pieces((int)$d['id']))){db()->prepare("UPDATE dossiers SET status='en_attente' WHERE id=?")->execute([$d['id']]);flash('success','Dossier soumis : il sera ouvert dès la validation de votre paiement.');notifyStaff('Nouveau dossier soumis : '.$d['titre'].' ('.$u['name'].')','a=dossier&id='.(int)$d['id']);}else flash('danger','Le dossier est incomplet : complétez toutes les étapes.');}
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
 if($edit){$mi=array_diff(missing($d,$p),['PDF']);$h.=$mi?'<p class="mu">Pièces obligatoires à compléter avant l\'envoi : '.e(implode(', ',$mi)).'</p>':$form('<input type="hidden" name="go_submit" value="1"><button class="submitgo">Soumettre le dossier pour validation</button>');}
 $pf=fn($ac,$bt)=>'<form method="post" action="index.php?a='.$ac.'" style="display:inline"><input type="hidden" name="t" value="'.csrf().'"><input type="hidden" name="id" value="'.$d['id'].'">'.$bt.'</form>';$act='';
 if($own&&$d['status']==='en_attente')$act.=$pf('reopen','<button class="ghost" data-confirm="Le dossier repasse en brouillon pour être modifié. Vous devrez le soumettre à nouveau." data-ok="Modifier le dossier">'.icon('edit').'Modifier le dossier</button>');
 if(($own&&$d['status']!=='paye')||$u['role']==='admin')$act.=$pf('deldoss','<button class="danger" data-type="danger" data-ok="Supprimer" data-confirm="Supprimer définitivement ce dossier et toutes ses pièces ? Cette action est irréversible.">'.icon('trash').'Supprimer le dossier</button>');
 page($d['titre'],$h.'</p>'.($act?'<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">'.$act.'</div>':'').'<div id="auto" data-cfg="'.e(json_encode($cfg)).'"></div>','assets/editor.js');exit;}

if($a==='edit'){$d=dossier((int)($_GET['id']??0));$c=(string)($_GET['c']??'');$ty=ty($d);$st=steps($ty)[$c]??null;
 if($u['id']!=$d['user_id']||$d['status']!=='brouillon'||!$st||$st[1]!=='img'){http_response_code(403);exit('Refusé');}
 $p=pieces((int)$d['id']);$cfg=['id'=>$d['id'],'c'=>$c,'t'=>csrf(),'admin'=>$u['role']==='admin','title'=>mb_strtoupper($st[0]),'head'=>HEAD[$ty],'wm'=>true,'emojis'=>emojis(),'base'=>!empty($p[$c]['file']),'data'=>json_decode((string)($p[$c]['data']??'null')),'meta'=>['titre'=>$d['titre'],'adresse'=>$d['adresse'],'parcelle'=>$d['parcelle'],'owner'=>$u['name']]];
 page('Éditeur '.$c,'<h2>'.$c.' – '.e($st[0]).'</h2><p class="hp">'.e($st[2]).'</p><div id="ed" data-cfg="'.e(json_encode($cfg)).'"></div>','assets/editor.js');exit;}

if($a==='save'&&$post){header('Content-Type: application/json');$d=dossier((int)($_POST['id']??0));$c=(string)($_POST['c']??'');
 if($u['id']!=$d['user_id']||$d['status']!=='brouillon'||!in_array($c,codes(ty($d)),true)){http_response_code(403);echo '{"ok":0,"err":"refusé"}';exit;}
 $sh=savejpeg($_FILES['sheet']??null,1700);$ba=savejpeg($_FILES['base']??null,2400);$data=(string)($_POST['data']??'{}');
 if(!$sh||strlen($data)>200000||json_decode($data)===null){echo '{"ok":0,"err":"données invalides"}';exit;}
 $o=pieces((int)$d['id'])[$c]??null;
 db()->prepare('INSERT INTO pieces(dossier_id,code,file,sheet,data) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE file=COALESCE(VALUES(file),file),sheet=VALUES(sheet),data=VALUES(data)')->execute([$d['id'],$c,$ba,$sh,$data]);
 if($o){if($o['sheet'])@unlink(__DIR__.'/storage/img/'.basename($o['sheet']));if($ba&&$o['file'])@unlink(__DIR__.'/storage/img/'.basename($o['file']));}
 $tc=txtCode(ty($d));if($tc&&$c===$tc&&isset($_POST['desc']))db()->prepare('UPDATE dossiers SET description=? WHERE id=?')->execute([mb_substr((string)$_POST['desc'],0,4000),$d['id']]);
 if($c!==ty($d).'0'&&$c!==$tc)flash('success','Étape '.$c.' enregistrée.');
 echo '{"ok":1}';exit;}

if($a==='img'||$a==='sheet'){$d=dossier((int)($_GET['id']??0));$p=pieces((int)$d['id']);$c=(string)($_GET['c']??'');$f=$p[$c][$a==='img'?'file':'sheet']??null;if(!$f)exit;$path=__DIR__.'/storage/img/'.basename($f);
 header('Content-Type: image/jpeg');header('Cache-Control: private, max-age=60');
 if($a==='sheet'&&$d['status']!=='paye'&&($im=@imagecreatefromjpeg($path))){watermark($im,imagesx($im),imagesy($im),'BROUILLON');imagejpeg($im,null,85);imagedestroy($im);exit;}
 readfile($path);exit;}

if($a==='pdf'){try{$d=dossier((int)($_GET['id']??0));$p=pieces((int)$d['id']);$wm=$d['status']!=='paye';$pdf=new MiniPdf;$n=0;
 foreach(codes(ty($d)) as $c){if(empty($p[$c]['sheet']))continue;$path=__DIR__.'/storage/img/'.basename($p[$c]['sheet']);
  if($wm){$im=imagecreatefromjpeg($path);watermark($im,imagesx($im),imagesy($im));ob_start();imagejpeg($im,null,85);imagedestroy($im);$j=ob_get_clean();}else $j=file_get_contents($path);
  $g=getimagesizefromstring($j);$pdf->add($j,$g[0],$g[1]);$n++;}
 if(!$n)throw new Exception('Aucune planche : ouvrez les éditeurs et enregistrez chaque étape.');
 $out=$pdf->out();header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="DOSSIERUrba-'.ty($d).'-'.(int)$d['id'].($wm?'-apercu':'').'.pdf"');header('Cache-Control: private, no-store');echo $out;exit;}
 catch(Throwable $x){error_log((string)$x);flash('danger','Génération du PDF impossible : '.$x->getMessage());go('dossier','&id='.(int)($_GET['id']??0));}}

if($a==='lib'){header('Content-Type: application/json');$s=db()->prepare('SELECT id,name,wcm,hcm,shared,(user_id=?) AS mine FROM library WHERE user_id=? OR shared=1 ORDER BY id DESC');$s->execute([$u['id'],$u['id']]);echo json_encode($s->fetchAll());exit;}
if($a==='libimg'){$s=db()->prepare('SELECT * FROM library WHERE id=? AND (user_id=? OR shared=1)');$s->execute([(int)($_GET['id']??0),$u['id']]);$r=$s->fetch();if(!$r){http_response_code(404);exit;}header('Content-Type: image/png');header('Cache-Control: private, max-age=86400');readfile(__DIR__.'/storage/lib/'.basename($r['file']));exit;}
if($a==='libdel'&&$post){$s=db()->prepare('SELECT * FROM library WHERE id=?');$s->execute([(int)($_POST['id']??0)]);$r=$s->fetch();if($r&&($r['user_id']==$u['id']||$u['role']==='admin')){@unlink(__DIR__.'/storage/lib/'.basename($r['file']));db()->prepare('DELETE FROM library WHERE id=?')->execute([$r['id']]);}echo '{"ok":1}';exit;}
if($a==='libsave'&&$post){header('Content-Type: application/json');$f=$_FILES['png']??null;
 if(!$f||$f['error']!==0||$f['size']>4*1048576){echo '{"ok":0,"err":"fichier refusé"}';exit;}
 $raw=file_get_contents($f['tmp_name']);if((new finfo(FILEINFO_MIME_TYPE))->buffer($raw)!=='image/png'||!($im=@imagecreatefromstring($raw))){echo '{"ok":0,"err":"image invalide"}';exit;}
 $n=db()->prepare('SELECT COUNT(*) FROM library WHERE user_id=?');$n->execute([$u['id']]);if($n->fetchColumn()>=100){echo '{"ok":0,"err":"bibliothèque pleine (100)"}';exit;}
 $k=min(1,1000/max(imagesx($im),imagesy($im)));if($k<1){$t=imagecreatetruecolor((int)(imagesx($im)*$k),(int)(imagesy($im)*$k));imagealphablending($t,false);imagesavealpha($t,true);imagefill($t,0,0,imagecolorallocatealpha($t,0,0,0,127));imagecopyresampled($t,$im,0,0,0,0,imagesx($t),imagesy($t),imagesx($im),imagesy($im));$im=$t;}else{imagealphablending($im,false);imagesavealpha($im,true);}
 $fn=bin2hex(random_bytes(16)).'.png';imagepng($im,__DIR__.'/storage/lib/'.$fn,6);$sh=($u['role']==='admin'&&!empty($_POST['shared']))?1:0;
 db()->prepare('INSERT INTO library(user_id,name,file,wcm,hcm,shared,created_at) VALUES(?,?,?,?,?,?,NOW())')->execute([$u['id'],mb_substr(trim((string)($_POST['name']??'Objet')),0,60)?:'Objet',$fn,max(0,min(5000,(int)($_POST['wcm']??0))),max(0,min(5000,(int)($_POST['hcm']??0))),$sh]);
 echo '{"ok":1}';exit;}

if($a==='admin'){need(true);
 if($post&&isset($_POST['id'])){$st=$_POST['st']==='paye'?'paye':'en_attente';db()->prepare('UPDATE dossiers SET status=? WHERE id=?')->execute([$st,(int)$_POST['id']]);$dd=db()->prepare('SELECT user_id,titre FROM dossiers WHERE id=?');$dd->execute([(int)$_POST['id']]);if($dd=$dd->fetch())notify((int)$dd['user_id'],$st==='paye'?'Paiement validé pour « '.$dd['titre'].' » : votre PDF complet est disponible.':'La validation de « '.$dd['titre'].' » a été annulée.','a=dossier&id='.(int)$_POST['id']);flash('success',$st==='paye'?'Paiement validé : le filigrane est retiré du PDF.':'Validation annulée : le filigrane est rétabli.');go('admin');}
 $q=trim((string)($_GET['q']??''));$st=(string)($_GET['st']??'');$pg=max(1,(int)($_GET['p']??1));$per=10;$w='1=1';$args=[];
 if($q!==''){$w.=' AND (d.titre LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';array_push($args,"%$q%","%$q%","%$q%");}
 if(in_array($st,['brouillon','en_attente','paye'],true)){$w.=' AND d.status=?';$args[]=$st;}
 $c=db()->prepare("SELECT COUNT(*) FROM dossiers d JOIN users u ON u.id=d.user_id WHERE $w");$c->execute($args);$tot=(int)$c->fetchColumn();
 $s=db()->prepare("SELECT d.*,u.name,u.email FROM dossiers d JOIN users u ON u.id=d.user_id WHERE $w ORDER BY (d.status='en_attente') DESC,d.id DESC LIMIT $per OFFSET ".(($pg-1)*$per));$s->execute($args);$r='';
 foreach($s as $d){$pa=$d['status']==='paye';$r.='<tr><td><a href="index.php?a=dossier&id='.$d['id'].'"><b>'.e($d['titre']).'</b></a><br><small><span class="tk">'.ty($d).'</span> '.e($d['adresse']).'</small></td><td>'.e($d['name']).'<br><small>'.e($d['email']).'</small></td><td>'.badge($d['status']).'</td><td>'.e(substr($d['created_at'],0,10)).'</td><td>';
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
if($a==='log'){need(false,true);$f=__DIR__.'/storage/error.log';page('Journal','<h2>Journal d\'erreurs</h2><pre style="white-space:pre-wrap">'.e(is_file($f)?implode('',array_slice(file($f),-30)):'Vide').'</pre>');exit;}
go('dash');
