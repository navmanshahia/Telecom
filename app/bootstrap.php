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
function app_base_url(): string {
    $script = str_replace('\\\\','/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = rtrim(str_replace('\\\\','/', dirname($script)), '/.');
    return $dir === '' ? '' : $dir;
}
function url(string $path=''): string {
    $base = app_base_url();
    if ($path === '' || $path === '/') return $base !== '' ? $base.'/' : '/';
    if ($path[0] === '?') return ($base !== '' ? $base.'/' : '/').$path;
    return ($base !== '' ? $base : '').'/'.ltrim($path,'/');
}

ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly'=>true,'secure'=>envv('SESSION_SECURE','true')==='true',
        'samesite'=>'Lax','path'=>app_base_url() !== '' ? app_base_url().'/' : '/'
    ]);
    session_start();
}
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Cross-Origin-Opener-Policy: same-origin");
header("Cross-Origin-Resource-Policy: same-origin");
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'");
if((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || (($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https')){
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

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
function require_login(): array { $u=user(); if(!$u) redirect(url('?page=login')); return $u; }
function require_admin(): array { $u=require_login(); if(!in_array($u['role'],['owner','admin'],true)) { http_response_code(403); exit('Forbidden'); } return $u; }
function require_approved(): array { $u=require_login(); if($u['role']==='customer' && $u['status']!=='approved') redirect(url('?page=pending')); return $u; }
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


function rate_limit(string $scope,int $limit,int $windowSeconds): void {
    $limit=max(1,$limit);$windowSeconds=max(60,$windowSeconds);
    db()->exec("CREATE TABLE IF NOT EXISTS rate_limits(
        scope TEXT NOT NULL,
        key_hash TEXT NOT NULL,
        hits INTEGER NOT NULL DEFAULT 0,
        window_started INTEGER NOT NULL,
        PRIMARY KEY(scope,key_hash)
    )");
    $fingerprint=hash('sha256',($_SERVER['REMOTE_ADDR']??'unknown').'|'.substr($_SERVER['HTTP_USER_AGENT']??'',0,180));
    $now=time();
    $s=db()->prepare("SELECT hits,window_started FROM rate_limits WHERE scope=? AND key_hash=?");
    $s->execute([$scope,$fingerprint]);$row=$s->fetch();
    if(!$row || $now-(int)$row['window_started'] >= $windowSeconds){
        db()->prepare("INSERT INTO rate_limits(scope,key_hash,hits,window_started) VALUES(?,?,1,?)
            ON CONFLICT(scope,key_hash) DO UPDATE SET hits=1,window_started=excluded.window_started")
            ->execute([$scope,$fingerprint,$now]);
        return;
    }
    if((int)$row['hits'] >= $limit){
        http_response_code(429);
        header('Retry-After: '.max(1,$windowSeconds-($now-(int)$row['window_started'])));
        exit('Too many attempts. Please wait and try again.');
    }
    db()->prepare("UPDATE rate_limits SET hits=hits+1 WHERE scope=? AND key_hash=?")->execute([$scope,$fingerprint]);
}
function rate_limit_clear(string $scope): void {
    try{
        $fingerprint=hash('sha256',($_SERVER['REMOTE_ADDR']??'unknown').'|'.substr($_SERVER['HTTP_USER_AGENT']??'',0,180));
        db()->prepare("DELETE FROM rate_limits WHERE scope=? AND key_hash=?")->execute([$scope,$fingerprint]);
    }catch(Throwable $e){}
}
