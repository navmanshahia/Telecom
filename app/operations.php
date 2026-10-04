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
 'commissions'=>(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE commission_status IN ('pending','approved')"),
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
function deal_one_time_fees(array $deal): float {
    return max(0,(float)($deal['activation_fee']??0)) + max(0,(float)($deal['installation_fee']??0));
}
function deal_effective_monthly(array $deal): float {
    $months=deal_term($deal);
    return max(0,(((float)($deal['monthly_price']??0)*$months)+deal_one_time_fees($deal)-deal_rewards($deal))/$months);
}
function deal_term_cost(array $deal): float {
    $months=deal_term($deal);
    return max(0,((float)($deal['monthly_price']??0)*$months)+deal_one_time_fees($deal)-deal_rewards($deal));
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


/* Platform schema + account recovery hardening */
function db_table_exists(string $table): bool {
    $s=db()->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $s->execute([$table]);
    return (bool)$s->fetchColumn();
}
function db_column_exists(string $table,string $column): bool {
    if(!db_table_exists($table)) return false;
    foreach(db()->query("PRAGMA table_info(".$table.")")->fetchAll() as $row){
        if(($row['name']??'')===$column) return true;
    }
    return false;
}
function ensure_platform_schema(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS schema_migrations(version TEXT PRIMARY KEY,applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    if(!db_table_exists('users')) return;

    if(!db_column_exists('users','email_verified_at')) db()->exec("ALTER TABLE users ADD COLUMN email_verified_at TEXT");
    if(db_table_exists('deals') && !db_column_exists('deals','activation_fee')) db()->exec("ALTER TABLE deals ADD COLUMN activation_fee REAL NOT NULL DEFAULT 0");
    if(db_table_exists('deals') && !db_column_exists('deals','installation_fee')) db()->exec("ALTER TABLE deals ADD COLUMN installation_fee REAL NOT NULL DEFAULT 0");
    if(db_table_exists('orders') && !db_column_exists('orders','commission_amount')) db()->exec("ALTER TABLE orders ADD COLUMN commission_amount REAL NOT NULL DEFAULT 0");
    if(db_table_exists('orders') && !db_column_exists('orders','commission_status')) db()->exec("ALTER TABLE orders ADD COLUMN commission_status TEXT NOT NULL DEFAULT 'pending'");

    db()->exec("CREATE TABLE IF NOT EXISTS email_verification_tokens(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        token_hash TEXT NOT NULL UNIQUE,
        expires_at TEXT NOT NULL,
        used_at TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    db()->exec("CREATE INDEX IF NOT EXISTS idx_email_verification_user ON email_verification_tokens(user_id,used_at)");
    db()->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        token_hash TEXT NOT NULL UNIQUE,
        expires_at TEXT NOT NULL,
        used_at TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    db()->exec("CREATE INDEX IF NOT EXISTS idx_password_reset_user ON password_reset_tokens(user_id,used_at)");

    $version='006_sales_platform_hardening';
    $s=db()->prepare("SELECT 1 FROM schema_migrations WHERE version=?");
    $s->execute([$version]);
    if(!$s->fetchColumn()){
        db()->exec("UPDATE users SET email_verified_at=COALESCE(email_verified_at,created_at)");
        db()->prepare("INSERT INTO schema_migrations(version) VALUES(?)")->execute([$version]);
    }
}
function platform_schema_version(): string {
    if(!db_table_exists('schema_migrations')) return 'legacy';
    return (string)(scalar("SELECT version FROM schema_migrations ORDER BY applied_at DESC,version DESC LIMIT 1") ?: 'baseline');
}
function app_absolute_url(string $path): string {
    $configured=rtrim((string)envv('APP_URL',''),'/');
    if($configured!==''){
        if($path===''||$path==='/') return $configured.'/';
        return $configured.'/'.ltrim($path,'/');
    }
    $https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off') || (($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
    $host=$_SERVER['HTTP_HOST']??'localhost';
    return ($https?'https':'http').'://'.$host.url($path);
}
function issue_email_verification(int $userId): void {
    ensure_platform_schema();
    $u=rows("SELECT id,name,email,email_verified_at FROM users WHERE id=?",[$userId])[0]??null;
    if(!$u || !empty($u['email_verified_at'])) return;
    db()->prepare("DELETE FROM email_verification_tokens WHERE user_id=? AND used_at IS NULL")->execute([$userId]);
    $token=bin2hex(random_bytes(32));
    db()->prepare("INSERT INTO email_verification_tokens(user_id,token_hash,expires_at) VALUES(?,?,datetime('now','+24 hours'))")
      ->execute([$userId,hash('sha256',$token)]);
    $link=app_absolute_url('?page=verify-email&token='.rawurlencode($token));
    if(db_table_exists('communications')){
        queue_email($userId,null,null,'Verify your SecureLink email',"Hi ".($u['name']?:'there').",\n\nVerify your email to finish securing your SecureLink account:\n".$link."\n\nThis link expires in 24 hours.",$userId);
    }
}
function verify_email_token(string $token): bool {
    ensure_platform_schema();
    if(strlen($token)<32) return false;
    $s=db()->prepare("SELECT * FROM email_verification_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>=datetime('now') LIMIT 1");
    $s->execute([hash('sha256',$token)]);$row=$s->fetch();
    if(!$row) return false;
    db()->beginTransaction();
    try{
        db()->prepare("UPDATE users SET email_verified_at=datetime('now') WHERE id=?")->execute([(int)$row['user_id']]);
        db()->prepare("UPDATE email_verification_tokens SET used_at=datetime('now') WHERE id=?")->execute([(int)$row['id']]);
        db()->commit();
        return true;
    }catch(Throwable $e){ if(db()->inTransaction()) db()->rollBack(); return false; }
}
function issue_password_reset(string $email): void {
    ensure_platform_schema();
    $s=db()->prepare("SELECT id,name,email FROM users WHERE lower(email)=lower(?) LIMIT 1");$s->execute([trim($email)]);$u=$s->fetch();
    if(!$u) return;
    db()->prepare("DELETE FROM password_reset_tokens WHERE user_id=? AND used_at IS NULL")->execute([(int)$u['id']]);
    $token=bin2hex(random_bytes(32));
    db()->prepare("INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES(?,?,datetime('now','+60 minutes'))")
      ->execute([(int)$u['id'],hash('sha256',$token)]);
    $link=app_absolute_url('?page=reset-password&token='.rawurlencode($token));
    if(db_table_exists('communications')){
        queue_email((int)$u['id'],null,null,'Reset your SecureLink password',"Hi ".($u['name']?:'there').",\n\nUse this one-time link to reset your SecureLink password:\n".$link."\n\nThis link expires in 60 minutes. If you did not request it, ignore this message.",(int)$u['id']);
    }
}
function reset_password_with_token(string $token,string $password): bool {
    ensure_platform_schema();
    if(strlen($token)<32 || strlen($password)<12) return false;
    $s=db()->prepare("SELECT * FROM password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>=datetime('now') LIMIT 1");
    $s->execute([hash('sha256',$token)]);$row=$s->fetch();
    if(!$row) return false;
    db()->beginTransaction();
    try{
        db()->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($password,PASSWORD_DEFAULT),(int)$row['user_id']]);
        db()->prepare("UPDATE password_reset_tokens SET used_at=datetime('now') WHERE id=?")->execute([(int)$row['id']]);
        db()->prepare("UPDATE password_reset_tokens SET used_at=datetime('now') WHERE user_id=? AND used_at IS NULL")->execute([(int)$row['user_id']]);
        db()->commit();
        return true;
    }catch(Throwable $e){ if(db()->inTransaction()) db()->rollBack(); return false; }
}
