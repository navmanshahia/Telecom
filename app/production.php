<?php
declare(strict_types=1);

function active_campaign_id(): ?int {
    $code=$_SESSION['campaign_code']??'';
    if(!$code) return null;
    $s=db()->prepare("SELECT id FROM campaigns WHERE code=? AND status='active' LIMIT 1");
    $s->execute([$code]); $id=$s->fetchColumn();
    return $id ? (int)$id : null;
}
function current_referral_program(int $providerId): ?array {
    $s=db()->prepare("SELECT * FROM referral_programs WHERE provider_id=? AND active=1 ORDER BY id DESC LIMIT 1");
    $s->execute([$providerId]); return $s->fetch() ?: null;
}
function referral_reward_now(array $p): float {
    if($p['promo_reward_amount']!==null && !empty($p['promo_ends_at']) && strtotime($p['promo_ends_at'])>=time()) return (float)$p['promo_reward_amount'];
    return (float)$p['reward_amount'];
}
function create_referral_for_order(int $orderId,string $referrer,string $customer,int $providerId): void {
    $p=current_referral_program($providerId);
    $reward=$p?referral_reward_now($p):0.0;
    $s=db()->prepare("INSERT INTO referrals(order_id,referred_by,referred_customer,reward_amount,status) VALUES(?,?,?,?, 'pending')");
    $s->execute([$orderId,$referrer,$customer,$reward]);
}
function customer_updates(int $orderId): array {
    $s=db()->prepare("SELECT subject,message,created_at FROM communications WHERE order_id=? AND visibility='customer' ORDER BY id DESC");
    $s->execute([$orderId]); return $s->fetchAll();
}
function run_maintenance(): void {
    static $ran=false; if($ran) return; $ran=true;
    try {
        // A referral becomes eligible only after its order has remained activated/completed
        // for the provider program's configured retention period.
        db()->exec("UPDATE referrals SET status='eligible',eligible_at=datetime('now')
          WHERE status='pending' AND order_id IN (
            SELECT o.id FROM orders o
            JOIN referral_programs rp ON rp.provider_id=o.provider_id AND rp.active=1
            WHERE o.status IN ('activated','completed')
              AND o.activated_at IS NOT NULL
              AND datetime(o.activated_at,'+'||rp.retention_days||' days')<=datetime('now')
          )");
    } catch(Throwable $e) { /* pre-migration installs remain reachable */ }
}
