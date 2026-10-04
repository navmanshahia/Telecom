<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/operations.php';
ensure_platform_schema();
$root=dirname(__DIR__);$src=database_path();if(!is_file($src)){fwrite(STDERR,"Database not found.\n");exit(1);}
$dir=$root.'/storage/backups';if(!is_dir($dir)&&!mkdir($dir,0770,true)){fwrite(STDERR,"Cannot create backup directory.\n");exit(1);}
db()->exec('PRAGMA wal_checkpoint(FULL)');
$name='securelink-'.gmdate('Ymd-His').'.sqlite';$dest=$dir.'/'.$name;
if(!copy($src,$dest)){fwrite(STDERR,"Backup failed.\n");exit(1);}
$hash=hash_file('sha256',$dest);$bytes=filesize($dest);
db()->prepare("INSERT INTO deployment_backups(filename,bytes,sha256) VALUES(?,?,?)")->execute([$name,$bytes,$hash]);
$files=glob($dir.'/securelink-*.sqlite')?:[];usort($files,fn($a,$b)=>filemtime($b)<=>filemtime($a));foreach(array_slice($files,14) as $old)@unlink($old);
echo json_encode(['ok'=>true,'file'=>$name,'bytes'=>$bytes,'sha256'=>$hash],JSON_UNESCAPED_SLASHES).PHP_EOL;
