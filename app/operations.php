<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';

function scalar(string $sql,array $params=[]): mixed { $s=db()->prepare($sql);$s->execute($params);return $s->fetchColumn(); }
function rows(string $sql,array $params=[]): array { $s=db()->prepare($sql);$s->execute($params);return $s->fetchAll(); }
function lead_stages(): array { return ['lead'=>'Lead','interested'=>'Interested','follow_up'=>'Follow-up','application'=>'Application','order'=>'Order','activated'=>'Activated','lost'=>'Lost']; }
function lost_reasons(): array { return ['Price','Credit / eligibility','Address unavailable','Customer changed mind','Competitor offer','Unable to contact','Duplicate','Provider eligibility','Other']; }
function sla_minutes(array $order): int { return max(0,60-(int)floor((time()-strtotime($order['created_at']))/60)); }
function sla_state(array $order): string { if(!empty($order['first_contact_at'])) return 'contacted'; $m=sla_minutes($order); return $m<=15?'critical':($m<=35?'warning':'good'); }
function campaign_url(array $campaign): string { $base=rtrim(envv('APP_URL',''),'/' ); return $base.($campaign['destination']?:'/?page=register').(str_contains($campaign['destination']?:'','?')?'&':'?').'campaign='.rawurlencode($campaign['code']); }
function queue_email(?int $userId,?int $leadId,?int $orderId,string $subject,string $message,int $actor): void {
 $s=db()->prepare("INSERT INTO communications(user_id,lead_id,order_id,kind,visibility,subject,message,delivery_status,created_by) VALUES(?,?,?,'email','customer',?,?,'queued',?)");
 $s->execute([$userId,$leadId,$orderId,$subject,$message,$actor]);
}
function log_comm(?int $userId,?int $leadId,?int $orderId,string $kind,string $visibility,string $subject,string $message,int $actor): void {
 $s=db()->prepare("INSERT INTO communications(user_id,lead_id,order_id,kind,visibility,subject,message,created_by) VALUES(?,?,?,?,?,?,?,?)");
 $s->execute([$userId,$leadId,$orderId,$kind,$visibility,$subject,$message,$actor]);
}
function create_task(string $title,string $entityType,int $entityId,?int $assigned,string $priority,?string $due,string $description,int $actor): void {
 $s=db()->prepare("INSERT INTO tasks(title,description,entity_type,entity_id,assigned_to,priority,due_at,created_by) VALUES(?,?,?,?,?,?,?,?)");
 $s->execute([$title,$description,$entityType,$entityId,$assigned,$priority,$due,$actor]);
}
function dashboard_metrics(): array {
 return [
 'leads'=>(int)scalar("SELECT COUNT(*) FROM leads WHERE stage!='lost'"),
 'followups'=>(int)scalar("SELECT COUNT(*) FROM leads WHERE stage='follow_up' OR (next_follow_up_at IS NOT NULL AND next_follow_up_at<=datetime('now','+1 day') AND stage NOT IN ('activated','lost'))"),
 'new_orders'=>(int)scalar("SELECT COUNT(*) FROM orders WHERE status IN ('submitted','reviewing','need_information','ready_to_process')"),
 'sla'=>(int)scalar("SELECT COUNT(*) FROM orders WHERE first_contact_at IS NULL AND status='submitted' AND created_at<=datetime('now','-45 minutes')"),
 'tasks'=>(int)scalar("SELECT COUNT(*) FROM tasks WHERE status='open' AND (due_at IS NULL OR due_at<=datetime('now','+1 day'))"),
 'payouts'=>(float)scalar("SELECT COALESCE(SUM(reward_amount),0) FROM referrals WHERE status IN ('eligible','approved')"),
 'activated'=>(int)scalar("SELECT COUNT(*) FROM orders WHERE status IN ('activated','completed') AND created_at>=datetime('now','start of month')")
 ];
}
function funnel(): array {
 return [
  'Leads'=>(int)scalar("SELECT COUNT(*) FROM leads"),
  'Interested'=>(int)scalar("SELECT COUNT(*) FROM leads WHERE stage IN ('interested','follow_up','application','order','activated')"),
  'Applications'=>(int)scalar("SELECT COUNT(*) FROM orders"),
  'Processed'=>(int)scalar("SELECT COUNT(*) FROM orders WHERE status NOT IN ('submitted','cancelled','rejected')"),
  'Activated'=>(int)scalar("SELECT COUNT(*) FROM orders WHERE status IN ('activated','completed')")
 ];
}


function ensure_merchandising_schema(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS deal_merchandising(
        deal_id INTEGER PRIMARY KEY REFERENCES deals(id) ON DELETE CASCADE,
        featured INTEGER NOT NULL DEFAULT 0,
        display_order INTEGER NOT NULL DEFAULT 0,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
}
function deal_rewards(array $deal): float {
    return (float)($deal['bill_credit']??0)+(float)($deal['referral_reward']??0)+(float)($deal['other_reward']??0);
}
function deal_term(array $deal): int {
    return max(1,(int)($deal['term_months']??0) ?: 24);
}
function deal_effective_monthly(array $deal): float {
    return max(0,(float)($deal['monthly_price']??0) - (deal_rewards($deal)/deal_term($deal)));
}
function deal_term_cost(array $deal): float {
    $months=deal_term($deal);
    return max(0,((float)($deal['monthly_price']??0)*$months)-deal_rewards($deal));
}
function provider_slug(string $provider): string {
    $slug=strtolower(trim(preg_replace('/[^A-Za-z0-9]+/','-', $provider),'-'));
    return $slug!==''?$slug:'provider';
}
function active_deals(string $category=''): array {
    ensure_merchandising_schema();
    $sql="SELECT d.*,p.name provider,COALESCE(m.featured,0) featured,COALESCE(m.display_order,0) display_order
          FROM deals d
          JOIN providers p ON p.id=d.provider_id
          LEFT JOIN deal_merchandising m ON m.deal_id=d.id
          WHERE d.status='active' AND p.status='active'
            AND (d.starts_at IS NULL OR d.starts_at<=datetime('now'))
            AND (d.expires_at IS NULL OR d.expires_at>=datetime('now'))";
    $params=[];
    if($category!==''){ $sql.=" AND lower(d.category)=lower(?)";$params[]=$category; }
    $sql.=" ORDER BY COALESCE(m.featured,0) DESC,COALESCE(m.display_order,999999),p.display_order,d.id DESC";
    return rows($sql,$params);
}
function featured_deals(int $limit=3): array {
    ensure_merchandising_schema();
    $limit=max(1,min(12,$limit));
    return rows("SELECT d.*,p.name provider,COALESCE(m.featured,0) featured,COALESCE(m.display_order,0) display_order
        FROM deals d JOIN providers p ON p.id=d.provider_id
        LEFT JOIN deal_merchandising m ON m.deal_id=d.id
        WHERE d.status='active' AND p.status='active'
          AND (d.starts_at IS NULL OR d.starts_at<=datetime('now'))
          AND (d.expires_at IS NULL OR d.expires_at>=datetime('now'))
        ORDER BY COALESCE(m.featured,0) DESC,COALESCE(m.display_order,999999),d.id DESC LIMIT ".$limit);
}
