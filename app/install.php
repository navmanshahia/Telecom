<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
function installed(): bool {
    try { return (bool)db()->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn(); } catch(Throwable $e){ return false; }
}
if(installed() && (int)db()->query("SELECT COUNT(*) FROM users WHERE role='owner'")->fetchColumn()>0) exit('Installer disabled: owner already exists.');
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    db()->exec(file_get_contents(__DIR__.'/schema.sql'));
    // Seed the current provider catalogue for fresh installs.
    $offers=__DIR__.'/migrations/003_provider_offers_2026_10.sql';
    if(is_file($offers)) db()->exec(file_get_contents($offers));
    $complete=__DIR__.'/migrations/004_complete_telus_catalog.sql';
    if(is_file($complete)) db()->exec(file_get_contents($complete));
    $referrals=__DIR__.'/migrations/005_referral_programs.sql';
    if(is_file($referrals)) db()->exec(file_get_contents($referrals));
    $name=trim($_POST['name']??''); $email=strtolower(trim($_POST['email']??'')); $password=$_POST['password']??'';
    if(!$name || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12) exit('Use a valid name/email and a password of at least 12 characters.');
    $s=db()->prepare("INSERT INTO users(name,email,password_hash,role,status,approved_at) VALUES(?,?,?,'owner','approved',datetime('now'))");
    $s->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
    audit((int)db()->lastInsertId(),'install_owner','user',(int)db()->lastInsertId());
    redirect(url('?page=login'));
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="<?=e(url('assets/app.css'))?>"><title>Install</title></head><body><main class="shell narrow"><section class="card"><h1>Create owner</h1><form method="post"><input type="hidden" name="_token" value="<?=e(csrf())?>"><label>Name<input name="name" required></label><label>Email<input name="email" type="email" required></label><label>Password<input name="password" type="password" minlength="12" required></label><button>Create owner</button></form></section></main></body></html>
