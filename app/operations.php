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


/* Discovery, merchandising history, notifications and command-centre analytics */
function filtered_deals(array $filters=[]): array {
    ensure_merchandising_schema();
    $sql="SELECT d.*,p.name provider,COALESCE(m.featured,0) featured,COALESCE(m.display_order,0) display_order
          FROM deals d
          JOIN providers p ON p.id=d.provider_id
          LEFT JOIN deal_merchandising m ON m.deal_id=d.id
          WHERE d.status='active' AND p.status='active'
            AND (d.starts_at IS NULL OR d.starts_at<=datetime('now'))
            AND (d.expires_at IS NULL OR d.expires_at>=datetime('now'))";
    $params=[];
    $category=strtolower(trim((string)($filters['category']??'')));
    $provider=trim((string)($filters['provider']??''));
    $q=trim((string)($filters['q']??''));
    $maxPrice=(float)($filters['max_price']??0);
    $minReward=(float)($filters['min_reward']??0);
    $term=(int)($filters['term']??0);
    if($category!==''){ $sql.=" AND lower(d.category)=lower(?)";$params[]=$category; }
    if($provider!==''){ $sql.=" AND lower(p.name)=lower(?)";$params[]=$provider; }
    if($q!==''){
        $sql.=" AND (d.name LIKE ? OR d.description LIKE ? OR d.speed_data LIKE ? OR p.name LIKE ?)";
        $like='%'.$q.'%';array_push($params,$like,$like,$like,$like);
    }
    if($maxPrice>0){$sql.=" AND d.monthly_price<=?";$params[]=$maxPrice;}
    if($minReward>0){$sql.=" AND (COALESCE(d.bill_credit,0)+COALESCE(d.referral_reward,0)+COALESCE(d.other_reward,0))>=?";$params[]=$minReward;}
    if($term>0){$sql.=" AND COALESCE(d.term_months,0)=?";$params[]=$term;}
    $sort=(string)($filters['sort']??'featured');
    $orders=[
      'price_low'=>'d.monthly_price ASC',
      'price_high'=>'d.monthly_price DESC',
      'rewards'=>'(COALESCE(d.bill_credit,0)+COALESCE(d.referral_reward,0)+COALESCE(d.other_reward,0)) DESC',
      'newest'=>'d.id DESC',
      'term'=>'COALESCE(d.term_months,9999) ASC',
      'featured'=>'COALESCE(m.featured,0) DESC,COALESCE(m.display_order,999999),p.display_order,d.id DESC'
    ];
    $sql.=" ORDER BY ".($orders[$sort]??$orders['featured']);
    return rows($sql,$params);
}
function active_provider_names(): array {
    return array_map(fn($r)=>(string)$r['name'],rows("SELECT name FROM providers WHERE status='active' ORDER BY display_order,name"));
}
function deal_snapshot_state(int $dealId): ?array {
    $r=rows("SELECT d.*,COALESCE(m.featured,0) featured,COALESCE(m.display_order,0) display_order
             FROM deals d LEFT JOIN deal_merchandising m ON m.deal_id=d.id WHERE d.id=? LIMIT 1",[$dealId]);
    return $r[0]??null;
}
function record_deal_history(int $dealId,int $actor,string $changeType='update'): void {
    $snapshot=deal_snapshot_state($dealId);
    if(!$snapshot) return;
    $version=(int)scalar("SELECT COALESCE(MAX(version_no),0)+1 FROM deal_history WHERE deal_id=?",[$dealId]);
    db()->prepare("INSERT INTO deal_history(deal_id,version_no,snapshot_json,change_type,changed_by) VALUES(?,?,?,?,?)")
      ->execute([$dealId,$version,json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$changeType,$actor]);
}
function restore_deal_history(int $historyId,int $actor): int {
    $h=rows("SELECT * FROM deal_history WHERE id=? LIMIT 1",[$historyId])[0]??null;
    if(!$h) throw new RuntimeException('Offer version not found.');
    $snap=json_decode((string)$h['snapshot_json'],true);
    if(!is_array($snap)||empty($snap['id'])) throw new RuntimeException('Offer version is invalid.');
    $dealId=(int)$snap['id'];
    record_deal_history($dealId,$actor,'before_restore');
    $fields=['provider_id','category','name','description','monthly_price','regular_price','bill_credit','referral_reward','other_reward','activation_fee','installation_fee','term_months','speed_data','fine_print','status','starts_at','expires_at'];
    $sets=[];$vals=[];
    foreach($fields as $field){$sets[]=$field.'=?';$vals[]=$snap[$field]??null;}
    $vals[]=$dealId;
    db()->prepare("UPDATE deals SET ".implode(',',$sets)." WHERE id=?")->execute($vals);
    db()->prepare("INSERT INTO deal_merchandising(deal_id,featured,display_order,updated_at) VALUES(?,?,?,datetime('now'))
                   ON CONFLICT(deal_id) DO UPDATE SET featured=excluded.featured,display_order=excluded.display_order,updated_at=datetime('now')")
      ->execute([$dealId,(int)($snap['featured']??0),(int)($snap['display_order']??0)]);
    record_deal_history($dealId,$actor,'restore');
    return $dealId;
}
function queue_notification(?int $userId,?int $orderId,?int $leadId,string $subject,string $message,int $actor=0,string $channel='email',?string $recipient=null,?string $scheduledAt=null,string $type='transactional'): int {
    if($recipient===null && $userId){
        $recipient=(string)(scalar("SELECT email FROM users WHERE id=?",[$userId])?:'');
    }
    $scheduledAt=$scheduledAt?:(string)scalar("SELECT datetime('now')");
    $scheduledAt=str_replace('T',' ',$scheduledAt);
    if(strlen($scheduledAt)===16)$scheduledAt.=':00';
    $s=db()->prepare("INSERT INTO notification_queue(user_id,order_id,lead_id,channel,notification_type,recipient,subject,message,status,scheduled_at,created_by)
                      VALUES(?,?,?,?,?,?,?,?,'queued',?,?)");
    $s->execute([$userId,$orderId,$leadId,$channel,$type,$recipient,$subject,$message,$scheduledAt,$actor?:null]);
    return (int)db()->lastInsertId();
}
function queue_order_status_notification(int $orderId,int $actor,string $customMessage=''): void {
    $o=rows("SELECT o.*,u.name customer,u.email,p.name provider,d.name deal FROM orders o
            JOIN users u ON u.id=o.user_id JOIN providers p ON p.id=o.provider_id JOIN deals d ON d.id=o.deal_id
            WHERE o.id=? LIMIT 1",[$orderId])[0]??null;
    if(!$o) return;
    $status=ucwords(str_replace('_',' ',(string)$o['status']));
    $message=$customMessage!==''?$customMessage:
      "Hi ".($o['customer']?:'there').",\n\nYour SecureLink order ".$o['public_id']." for ".$o['provider']." · ".$o['deal']." is now: ".$status.".".
      (!empty($o['appointment_at'])?"\nAppointment: ".$o['appointment_at'].".":"").
      (!empty($o['provider_reference'])?"\nProvider reference: ".$o['provider_reference'].".":"").
      "\n\nYou can sign in to SecureLink to view the latest status.";
    queue_notification((int)$o['user_id'],$orderId,null,'Order update · '.$status,$message,$actor,'email',(string)$o['email']);
    if(db_table_exists('communications')) queue_email((int)$o['user_id'],null,$orderId,'Order update · '.$status,$message,$actor);
}
function notification_stats(): array {
    return [
      'queued'=>(int)scalar("SELECT COUNT(*) FROM notification_queue WHERE status='queued'"),
      'due'=>(int)scalar("SELECT COUNT(*) FROM notification_queue WHERE status='queued' AND scheduled_at<=datetime('now')"),
      'sent_today'=>(int)scalar("SELECT COUNT(*) FROM notification_queue WHERE status='sent' AND date(sent_at)=date('now')"),
      'failed'=>(int)scalar("SELECT COUNT(*) FROM notification_queue WHERE status='failed'")
    ];
}
function process_notification_queue(int $limit=25): array {
    $limit=max(1,min(100,$limit));$sent=0;$failed=0;$skipped=0;
    $transport=strtolower((string)envv('MAIL_TRANSPORT','log'));
    $items=rows("SELECT * FROM notification_queue WHERE status='queued' AND scheduled_at<=datetime('now') ORDER BY scheduled_at,id LIMIT ".$limit);
    foreach($items as $n){
        $type=(string)($n['notification_type']??'transactional');
        if($type==='order_followup' && !empty($n['order_id'])){
            $orderStatus=(string)(scalar("SELECT status FROM orders WHERE id=?",[(int)$n['order_id']])?:'');
            if(!in_array($orderStatus,['submitted','reviewing','need_information'],true)){
                db()->prepare("UPDATE notification_queue SET status='cancelled',last_error='Order progressed before scheduled follow-up' WHERE id=?")->execute([(int)$n['id']]);
                continue;
            }
        }
        if($type==='lead_followup' && !empty($n['lead_id'])){
            $leadStage=(string)(scalar("SELECT stage FROM leads WHERE id=?",[(int)$n['lead_id']])?:'');
            if(in_array($leadStage,['activated','lost','order'],true)){
                db()->prepare("UPDATE notification_queue SET status='cancelled',last_error='Lead progressed before scheduled follow-up' WHERE id=?")->execute([(int)$n['id']]);
                continue;
            }
        }
        if($transport!=='mail'){ $skipped++; continue; }
        $recipient=trim((string)$n['recipient']);
        if($recipient===''||!filter_var($recipient,FILTER_VALIDATE_EMAIL)){
            db()->prepare("UPDATE notification_queue SET status='failed',attempts=attempts+1,last_error=? WHERE id=?")
              ->execute(['Missing or invalid email recipient',(int)$n['id']]);$failed++;continue;
        }
        $headers="MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        $from=trim((string)envv('MAIL_FROM',''));
        if($from!=='')$headers.="From: ".$from."\r\n";
        $ok=@mail($recipient,(string)$n['subject'],(string)$n['message'],$headers);
        if($ok){
            db()->prepare("UPDATE notification_queue SET status='sent',sent_at=datetime('now'),attempts=attempts+1,last_error=NULL WHERE id=?")->execute([(int)$n['id']]);$sent++;
        }else{
            db()->prepare("UPDATE notification_queue SET attempts=attempts+1,last_error=? WHERE id=?")->execute(['mail() returned false',(int)$n['id']]);$failed++;
        }
    }
    return ['sent'=>$sent,'failed'=>$failed,'skipped'=>$skipped];
}
function track_event(string $event,?string $entityType=null,?int $entityId=null,array $metadata=[]): void {
    try{
        $u=user();$uid=$u?(int)$u['id']:null;
        $sessionKey=hash('sha256',session_id().'|'.($_SERVER['REMOTE_ADDR']??''));
        db()->prepare("INSERT INTO analytics_events(user_id,event_name,entity_type,entity_id,session_key,metadata) VALUES(?,?,?,?,?,?)")
          ->execute([$uid,$event,$entityType,$entityId,$sessionKey,$metadata?json_encode($metadata,JSON_UNESCAPED_SLASHES):null]);
    }catch(Throwable $e){}
}
function command_centre_series(int $days=7): array {
    $days=max(3,min(30,$days));$out=[];
    for($i=$days-1;$i>=0;$i--){
        $date=date('Y-m-d',strtotime('-'.$i.' day'));
        $out[]=['date'=>$date,'orders'=>(int)scalar("SELECT COUNT(*) FROM orders WHERE date(created_at)=?",[$date]),'activated'=>(int)scalar("SELECT COUNT(*) FROM orders WHERE date(created_at)=? AND status IN ('activated','completed')",[$date])];
    }
    return $out;
}
function provider_conversion_rows(): array {
    return rows("SELECT p.name provider,COUNT(o.id) orders,
                 SUM(CASE WHEN o.status IN ('activated','completed') THEN 1 ELSE 0 END) activated,
                 SUM(CASE WHEN o.status IN ('cancelled','rejected') THEN 1 ELSE 0 END) lost
                 FROM providers p LEFT JOIN orders o ON o.provider_id=p.id
                 GROUP BY p.id,p.name ORDER BY orders DESC,p.name");
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

    // Bring older production databases up to the current baseline without requiring
    // a manual migration before the admin dashboard can load.
    $schemaFile=__DIR__.'/schema.sql';
    if(is_file($schemaFile)){
        $schema=file_get_contents($schemaFile);
        if($schema!==false) db()->exec($schema);
    }
    if(!db_table_exists('users')) return;

    $addColumn=function(string $table,string $column,string $definition): void {
        if(db_table_exists($table) && !db_column_exists($table,$column)){
            db()->exec("ALTER TABLE ".$table." ADD COLUMN ".$column." ".$definition);
        }
    };

    $addColumn('users','status',"TEXT NOT NULL DEFAULT 'pending'");
    $addColumn('users','approved_at','TEXT');
    $addColumn('users','email_verified_at','TEXT');

    $addColumn('providers','description','TEXT');
    $addColumn('providers','website','TEXT');
    $addColumn('providers','status',"TEXT NOT NULL DEFAULT 'active'");
    $addColumn('providers','display_order','INTEGER NOT NULL DEFAULT 0');

    $addColumn('deals','regular_price','REAL');
    $addColumn('deals','bill_credit','REAL NOT NULL DEFAULT 0');
    $addColumn('deals','referral_reward','REAL NOT NULL DEFAULT 0');
    $addColumn('deals','other_reward','REAL NOT NULL DEFAULT 0');
    $addColumn('deals','activation_fee','REAL NOT NULL DEFAULT 0');
    $addColumn('deals','installation_fee','REAL NOT NULL DEFAULT 0');
    $addColumn('deals','term_months','INTEGER');
    $addColumn('deals','speed_data','TEXT');
    $addColumn('deals','fine_print','TEXT');
    $addColumn('deals','status',"TEXT NOT NULL DEFAULT 'draft'");
    $addColumn('deals','starts_at','TEXT');
    $addColumn('deals','expires_at','TEXT');

    $addColumn('orders','campaign_id','INTEGER');
    $addColumn('orders','lead_id','INTEGER');
    $addColumn('orders','sales_agent','TEXT');
    $addColumn('orders','provider_reference','TEXT');
    $addColumn('orders','appointment_at','TEXT');
    $addColumn('orders','admin_notes','TEXT');
    $addColumn('orders','lost_reason','TEXT');
    $addColumn('orders','first_contact_at','TEXT');
    $addColumn('orders','commission_amount','REAL NOT NULL DEFAULT 0');
    $addColumn('orders','commission_status',"TEXT NOT NULL DEFAULT 'pending'");
    $addColumn('notification_queue','notification_type',"TEXT NOT NULL DEFAULT 'transactional'");

    db()->exec("CREATE TABLE IF NOT EXISTS referral_programs(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        provider_id INTEGER NOT NULL REFERENCES providers(id),
        name TEXT NOT NULL,
        active INTEGER NOT NULL DEFAULT 1,
        reward_amount REAL NOT NULL DEFAULT 0,
        promo_reward_amount REAL,
        promo_ends_at TEXT,
        retention_days INTEGER NOT NULL DEFAULT 0,
        bonus_5_amount REAL NOT NULL DEFAULT 0,
        bonus_10_amount REAL NOT NULL DEFAULT 0,
        terms TEXT,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    $telusId=(int)(scalar("SELECT id FROM providers WHERE lower(name)=lower('TELUS') LIMIT 1")?:0);
    if($telusId && !(int)scalar("SELECT COUNT(*) FROM referral_programs WHERE provider_id=?",[$telusId])){
        db()->prepare("INSERT INTO referral_programs(provider_id,name,active,reward_amount,promo_reward_amount,promo_ends_at,retention_days,bonus_5_amount,bonus_10_amount,terms) VALUES(?,?,?,?,?,?,?,?,?,?)")
          ->execute([$telusId,'TELUS Refer & Earn',1,50,100,'2026-10-31 23:59:59',0,200,250,'Referral reward applies after a successful TELUS activation. Milestone bonuses are based on successful referrals and remain subject to program eligibility.']);
    }

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

    $version='008_legacy_database_compatibility';
    $s=db()->prepare("SELECT 1 FROM schema_migrations WHERE version=?");
    $s->execute([$version]);
    if(!$s->fetchColumn()){
        db()->exec("UPDATE users SET email_verified_at=COALESCE(email_verified_at,created_at)");
        db()->prepare("INSERT INTO schema_migrations(version) VALUES(?)")->execute([$version]);
    }
    $version='009_growth_operations';
    $s=db()->prepare("SELECT 1 FROM schema_migrations WHERE version=?");
    $s->execute([$version]);
    if(!$s->fetchColumn()){
        db()->prepare("INSERT INTO schema_migrations(version) VALUES(?)")->execute([$version]);
    }
}
function referral_code_for_user(array $user): string {
    $id=(int)($user['id']??0);
    $email=strtolower(trim((string)($user['email']??'')));
    return 'SL'.str_pad((string)$id,4,'0',STR_PAD_LEFT).'-'.strtoupper(substr(hash('sha256',$id.'|'.$email),0,6));
}
function active_referral_program(string $provider='TELUS'): ?array {
    ensure_platform_schema();
    $r=rows("SELECT rp.*,p.name provider FROM referral_programs rp JOIN providers p ON p.id=rp.provider_id WHERE rp.active=1 AND lower(p.name)=lower(?) ORDER BY rp.id DESC LIMIT 1",[$provider]);
    if(!$r) return null;
    $p=$r[0];
    $promo=!empty($p['promo_reward_amount']) && !empty($p['promo_ends_at']) && strtotime((string)$p['promo_ends_at'])>=time();
    $p['current_reward']=$promo?(float)$p['promo_reward_amount']:(float)$p['reward_amount'];
    $p['promo_active']=$promo;
    return $p;
}
function referral_dashboard(array $user,string $provider='TELUS'): array {
    $code=referral_code_for_user($user);
    $items=rows("SELECT r.*,o.public_id,o.status order_status FROM referrals r LEFT JOIN orders o ON o.id=r.order_id WHERE r.referred_by IN (?,?,?) ORDER BY r.id DESC",[$code,(string)($user['email']??''),(string)($user['name']??'')]);
    $successful=0;$pending=0;$earned=0.0;$paid=0.0;
    foreach($items as $r){
        if(in_array($r['status'],['eligible','approved','paid'],true)){$successful++;$earned+=(float)$r['reward_amount'];}
        elseif($r['status']==='pending'){$pending++;}
        if($r['status']==='paid')$paid+=(float)$r['reward_amount'];
    }
    $program=active_referral_program($provider);
    $bonus=0.0;
    if($program){
        if($successful>=10)$bonus=(float)$program['bonus_10_amount'];
        elseif($successful>=5)$bonus=(float)$program['bonus_5_amount'];
    }
    return ['code'=>$code,'items'=>$items,'successful'=>$successful,'pending'=>$pending,'earned'=>$earned,'paid'=>$paid,'milestone_bonus'=>$bonus,'program'=>$program];
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
    $subject='Verify your SecureLink email';$message="Hi ".($u['name']?:'there').",\n\nVerify your email to finish securing your SecureLink account:\n".$link."\n\nThis link expires in 24 hours.";
    if(db_table_exists('communications')) queue_email($userId,null,null,$subject,$message,$userId);
    if(db_table_exists('notification_queue')) queue_notification($userId,null,null,$subject,$message,$userId,'email',(string)$u['email']);
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
    $subject='Reset your SecureLink password';$message="Hi ".($u['name']?:'there').",\n\nUse this one-time link to reset your SecureLink password:\n".$link."\n\nThis link expires in 60 minutes. If you did not request it, ignore this message.";
    if(db_table_exists('communications')) queue_email((int)$u['id'],null,null,$subject,$message,(int)$u['id']);
    if(db_table_exists('notification_queue')) queue_notification((int)$u['id'],null,null,$subject,$message,(int)$u['id'],'email',(string)$u['email']);
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
