<?php
declare(strict_types=1);
if (is_file(__DIR__.'/config.php')) { http_response_code(403); exit('Déjà installé. Supprimez install.php.'); }
$err=''; $ok=false;
if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    $f=fn($k)=>trim((string)($_POST[$k]??''));
    if (!filter_var($f('email'),FILTER_VALIDATE_EMAIL) || strlen($_POST['apass']??'')<10) throw new Exception('Email admin invalide ou mot de passe < 10 caractères.');
    $pdo=new PDO("mysql:host={$f('h')};dbname={$f('n')};charset=utf8mb4",$f('u'),$_POST['p']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE TABLE IF NOT EXISTS users(id INT AUTO_INCREMENT PRIMARY KEY,email VARCHAR(190) UNIQUE NOT NULL,pass_hash VARCHAR(255) NOT NULL,name VARCHAR(120) NOT NULL,role ENUM('declarant','gestionnaire','admin') NOT NULL DEFAULT 'declarant',created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS dossiers(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,titre VARCHAR(150) NOT NULL,adresse VARCHAR(255) NOT NULL,parcelle VARCHAR(40) NOT NULL,description TEXT,status ENUM('brouillon','en_attente','paye') NOT NULL DEFAULT 'brouillon',created_at DATETIME NOT NULL,FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pieces(id INT AUTO_INCREMENT PRIMARY KEY,dossier_id INT NOT NULL,code VARCHAR(8) NOT NULL,file VARCHAR(64) NOT NULL,UNIQUE(dossier_id,code),FOREIGN KEY(dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->prepare("INSERT INTO users(email,pass_hash,name,role,created_at) VALUES(?,?,?,'admin',NOW())")->execute([strtolower($f('email')),password_hash($_POST['apass'],PASSWORD_DEFAULT),$f('name')?:'Admin']);
    @mkdir(__DIR__.'/storage/img',0750,true);
    file_put_contents(__DIR__.'/storage/.htaccess',"Require all denied\n");
    $cfg=['h'=>$f('h'),'n'=>$f('n'),'u'=>$f('u'),'p'=>$_POST['p']??''];
    file_put_contents(__DIR__.'/config.php',"<?php\nreturn ".var_export($cfg,true).";\n");
    @chmod(__DIR__.'/config.php',0640);
    $ok=true; @unlink(__FILE__);
  } catch (Throwable $x) { $err=$x->getMessage(); }
}
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Installation – DOSSIERUrba</title><link rel="stylesheet" href="assets/app.css"><script src="assets/theme.js"></script>
<body><main class="auth"><div class="c" style="max-width:520px">
<button type="button" class="ghost th" style="position:absolute;top:12px;right:12px" aria-label="Thème"><span class="sun">☀</span><span class="moon">☾</span></button><img class="logo" src="assets/logo.png" alt="DOSSIERUrba" style="display:block;width:180px;margin:0 auto 10px"><p class="mu" style="text-align:center">Installation de DOSSIERUrba</p>
<?php
$e=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
$chk=[['PHP 8.0 ou plus ('.PHP_VERSION.')',version_compare(PHP_VERSION,'8.0','>=')],['Extension PDO MySQL',extension_loaded('pdo_mysql')],['Extension GD (images)',extension_loaded('gd')],['Extension mbstring',extension_loaded('mbstring')],['Extension fileinfo',extension_loaded('fileinfo')],['Dossier inscriptible',is_writable(__DIR__)]];
$all=!in_array(false,array_column($chk,1),true);
if($ok): ?>
<h2>Installation terminée</h2><p class="mu">Le fichier install.php a été supprimé. Vérifiez qu’il n’est plus présent sur le serveur.</p><a class="btn w" href="index.php">Se connecter</a>
<?php else: ?>
<h2>Configuration</h2><p class="mu">Quelques informations suffisent. Créez d’abord une base MySQL dans votre hébergement.</p>
<ul class="req"><?php foreach($chk as [$l,$v]) echo '<li class="'.($v?'ok':'er').'">'.($v?'✔':'✘').' '.$e($l).'</li>'; ?></ul>
<?php if($err) echo '<div id="flash" hidden data-type="danger" data-msg="'.$e($err).'"></div>'; ?>
<form method="post" id="f">
<h3>Base de données</h3>
<label>Hôte MySQL</label><input name="h" value="<?=$e($_POST['h']??'localhost')?>" required>
<label>Nom de la base</label><input name="n" value="<?=$e($_POST['n']??'')?>" required>
<label>Utilisateur</label><input name="u" value="<?=$e($_POST['u']??'')?>" required>
<label>Mot de passe</label><input name="p" type="password">
<h3 style="margin-top:8px">Compte administrateur</h3>
<label>Nom</label><input name="name" value="<?=$e($_POST['name']??'')?>">
<label>E-mail</label><input name="email" type="email" value="<?=$e($_POST['email']??'')?>" required>
<label>Mot de passe (10 caractères minimum)</label><input name="apass" type="password" minlength="10" required>
<button class="w" id="go" <?= $all?'':'disabled' ?>>Installer l’application</button>
</form>
<?php endif; ?>
<script src="assets/ui.js"></script>
</div></main></body></html>
