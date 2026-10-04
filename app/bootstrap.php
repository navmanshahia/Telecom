<?php
declare(strict_types=1);

function env_load(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key,$value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "'\"");
        $_ENV[$key] = $value;
    }
}
env_load(dirname(__DIR__).'/.env');
function envv(string $key, ?string $default=null): ?string { return $_ENV[$key] ?? getenv($key) ?: $default; }
function base_path(string $path=''): string { return dirname(__DIR__).($path ? '/'.ltrim($path,'/') : ''); }

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly'=>true,'secure'=>envv('SESSION_SECURE','true')==='true',
        'samesite'=>'Lax','path'=>'/'
    ]);
    session_start();
}
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $path = envv('DB_PATH','storage/app.sqlite');
    if (!str_starts_with($path,'/')) $path = base_path($path);
    $dir=dirname($path); if(!is_dir($dir)) mkdir($dir,0770,true);
    $pdo=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL;');
    return $pdo;
}
function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function csrf(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_check(): void { if(!hash_equals($_SESSION['csrf']??'', $_POST['_token']??'')) { http_response_code(419); exit('Session expired.'); } }
function redirect(string $to): never { header('Location: '.$to); exit; }
function user(): ?array {
    if(empty($_SESSION['uid'])) return null;
    $s=db()->prepare('SELECT * FROM users WHERE id=?'); $s->execute([$_SESSION['uid']]); return $s->fetch() ?: null;
}
function require_login(): array { $u=user(); if(!$u) redirect('/?page=login'); return $u; }
function require_admin(): array { $u=require_login(); if(!in_array($u['role'],['owner','admin'],true)) { http_response_code(403); exit('Forbidden'); } return $u; }
function require_approved(): array { $u=require_login(); if($u['role']==='customer' && $u['status']!=='approved') redirect('/?page=pending'); return $u; }
function audit(?int $uid,string $action,string $entity='',?int $entityId=null,array $meta=[]): void {
    $s=db()->prepare('INSERT INTO audit_logs(user_id,action,entity,entity_id,metadata,ip,created_at) VALUES(?,?,?,?,?,?,datetime("now"))');
    $s->execute([$uid,$action,$entity,$entityId,json_encode($meta),$_SERVER['REMOTE_ADDR']??null]);
}
function secret_key(): string {
    $hex=envv('APP_KEY',''); if(strlen($hex)!==64 || !ctype_xdigit($hex)) throw new RuntimeException('APP_KEY must be 64 hexadecimal characters.');
    return sodium_hex2bin($hex);
}
function encrypt_secret(?string $value): ?string {
    if(!$value) return null; $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce.sodium_crypto_secretbox($value,$nonce,secret_key()));
}
function mask_secret(?string $encrypted): string {
    if(!$encrypted) return 'Not provided';
    try { $raw=base64_decode($encrypted,true); $n=substr($raw,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); $c=substr($raw,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); $v=sodium_crypto_secretbox_open($c,$n,secret_key()); return $v===false?'Protected':'•••• '.substr($v,-4); } catch(Throwable $e){ return 'Protected'; }
}
