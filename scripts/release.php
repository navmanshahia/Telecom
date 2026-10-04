<?php
declare(strict_types=1);
$root=dirname(__DIR__);
function run(string $cmd): array { exec($cmd.' 2>&1',$out,$code);return [$code,implode("\n",$out)]; }
[$preCode,$pre]=run(PHP_BINARY.' '.escapeshellarg($root.'/scripts/preflight.php'));
if($preCode!==0){fwrite(STDERR,"PRE-FLIGHT FAILED\n".$pre."\n");exit(1);}
[$backupCode,$backup]=run(PHP_BINARY.' '.escapeshellarg($root.'/scripts/backup.php'));
if($backupCode!==0){fwrite(STDERR,"BACKUP FAILED\n".$backup."\n");exit(1);}
echo "PRE-FLIGHT OK\n".$pre."\nBACKUP OK\n".$backup."\n";
echo "Safe checkpoint created. Deploy code only after this command succeeds.\n";
