<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$errors=[];$warnings=[];

$required=[
    $root.'/public/index.php',
    $root.'/app/bootstrap.php',
    $root.'/app/operations.php',
    $root.'/app/schema.sql',
    $root.'/public/assets/app.css',
    $root.'/public/assets/portal.js',
    $root.'/public/manifest.webmanifest',
    $root.'/public/service-worker.js',
];
foreach($required as $file){
    if(!is_file($file))$errors[]='Missing: '.str_replace($root.'/','',$file);
}

if(!extension_loaded('pdo_sqlite'))$errors[]='PHP extension pdo_sqlite is required.';
if(!extension_loaded('sodium'))$errors[]='PHP extension sodium is required.';
if(PHP_VERSION_ID<80200)$warnings[]='PHP 8.2+ is recommended for production.';

$storage=$root.'/storage';
if(!is_dir($storage) && !@mkdir($storage,0770,true))$errors[]='storage/ could not be created.';
if(is_dir($storage) && !is_writable($storage))$errors[]='storage/ is not writable.';
foreach([$root.'/app',$root.'/storage',$root.'/scripts'] as $protected){if(str_starts_with(realpath($protected)?:'',realpath($root.'/public')?:$root.'/public'))$errors[]='Protected directory is inside public/: '.$protected;}
foreach(glob($root.'/app/*.php')?:[] as $php){$out=[];$code=0;exec(PHP_BINARY.' -l '.escapeshellarg($php).' 2>&1',$out,$code);if($code!==0)$errors[]='PHP lint failed: '.basename($php).' · '.implode(' ',$out);}
foreach(glob($root.'/public/*.php')?:[] as $php){$out=[];$code=0;exec(PHP_BINARY.' -l '.escapeshellarg($php).' 2>&1',$out,$code);if($code!==0)$errors[]='PHP lint failed: public/'.basename($php).' · '.implode(' ',$out);}

$env=$root.'/.env';
if(!is_file($env)){
    $warnings[]='.env is missing. Copy .env.example and configure production values.';
}else{
    $envText=(string)file_get_contents($env);
    if(str_contains($envText,'CHANGE_ME_TO_A_RANDOM'))$errors[]='APP_KEY still contains the example placeholder.';
    if(!preg_match('/^APP_DEBUG=false$/mi',$envText))$warnings[]='APP_DEBUG should be false in production.';
}

$manifest=$root.'/public/manifest.webmanifest';
if(is_file($manifest)){
    json_decode((string)file_get_contents($manifest),true);
    if(json_last_error()!==JSON_ERROR_NONE)$errors[]='manifest.webmanifest is invalid JSON: '.json_last_error_msg();
}

echo "SecureLink deployment preflight\n";
foreach($warnings as $w)echo "WARN: ".$w."\n";
foreach($errors as $e)echo "ERROR: ".$e."\n";
if(!$errors)echo "OK: production prerequisites passed.\n";
exit($errors?1:0);
