<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}
require __DIR__.'/bootstrap.php';
require_once __DIR__.'/operations.php';
ensure_platform_schema();

$limit=isset($argv[1])?(int)$argv[1]:50;
$automation=run_sales_automation();
$renewals=generate_renewal_opportunities();
$result=process_notification_queue($limit);
$result['renewal_opportunities']=$renewals;
echo json_encode([
    'time'=>date(DATE_ATOM),
    'transport'=>envv('MAIL_TRANSPORT','log'),
    'automation'=>$automation,
    'result'=>$result
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
