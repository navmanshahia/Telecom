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
 $s->execute([$title,$description,$entityType,$entityId,$assigned,$priority,$due,$actor ?: null]);
}
function dashboard_metrics(): array {
 return [
 'leads'=>(int)scalar("SELECT COUNT(*) FROM leads WHERE stage!='lost'"),
 'followups'=>(int)scalar("SELECT COUNT(*) FROM leads WHERE stage='follow_up' OR (next_follow_up_at IS NOT NULL AND next_follow_up_at<=datetime('now','+1 day') AND stage NOT IN ('activated','lost'))"),
 'new_orders'=>(int)scalar("SELECT COUNT(*) FROM orders WHERE status IN ('submitted','reviewing','need_information','ready_to_process')"),
 'sla'=>(int)scalar("SELECT COUNT(*) FROM orders WHERE first_contact_at IS NULL AND status='submitted' AND created_at<=datetime('now','-45 minutes')"),
 'tasks'=>(int)scalar("SELECT COUNT(*) FROM tasks WHERE status='open' AND (due_at IS NULL OR due_at<=datetime('now','+1 day'))"),
 'payouts'=>(float)scalar("SELECT COALESCE(SUM(reward_amount),0) FROM referrals WHERE status IN ('eligible','approved','processing','partial_completed','other_processing')"),
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
function deal_customer_credit(array $deal): float { return max(0,(float)($deal['bill_credit']??0)); }
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
            AND EXISTS(SELECT 1 FROM deal_categories dc WHERE lower(dc.slug)=lower(d.category) AND dc.status='active')
            AND EXISTS(SELECT 1 FROM deal_categories dc WHERE lower(dc.slug)=lower(d.category) AND dc.status='active')
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
          AND EXISTS(SELECT 1 FROM deal_categories dc WHERE lower(dc.slug)=lower(d.category) AND dc.status='active')
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
function active_deal_categories(): array { return rows("SELECT * FROM deal_categories WHERE status='active' ORDER BY display_order,name"); }
function all_deal_categories(): array { return rows("SELECT dc.*,(SELECT COUNT(*) FROM deals d WHERE lower(d.category)=lower(dc.slug)) deal_count FROM deal_categories dc ORDER BY dc.display_order,dc.name"); }
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
    $id=(int)db()->lastInsertId();
    if($channel==='email' && strtotime($scheduledAt)<=time()){
        try{ process_notification_queue(10); }catch(Throwable $e){ /* queue remains available for cron/manual retry */ }
    }
    return $id;
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
    if(db_table_exists('communications')) queue_email((int)$o['user_id'],null,$orderId,'Order update · '.$status,$message,$actor);
    queue_notification((int)$o['user_id'],$orderId,null,'Order update · '.$status,$message,$actor,'email',(string)$o['email']);
}
function notification_stats(): array {
    if(db_table_exists('users')){
        foreach(rows("SELECT id FROM users WHERE email_verified_at IS NOT NULL") as $verified) cancel_verification_messages((int)$verified['id']);
    }
    return [
      'queued'=>(int)scalar("SELECT COUNT(*) FROM notification_queue WHERE status='queued'"),
      'due'=>(int)scalar("SELECT COUNT(*) FROM notification_queue WHERE status='queued' AND scheduled_at<=datetime('now')"),
      'sent_today'=>(int)scalar("SELECT COUNT(*) FROM notification_queue WHERE status='sent' AND date(sent_at)=date('now')"),
      'logged'=>(int)scalar("SELECT COUNT(*) FROM notification_queue WHERE status='logged'"),
      'failed'=>(int)scalar("SELECT COUNT(*) FROM notification_queue WHERE status='failed'")
    ];
}
function sync_notification_communication(array $n,string $status): void {
    if(!db_table_exists('communications')) return;
    $where=["kind='email'","delivery_status='queued'","subject=?","message=?"];
    $params=[(string)$n['subject'],(string)$n['message']];
    if(!empty($n['user_id'])){$where[]='user_id=?';$params[]=(int)$n['user_id'];}
    if(!empty($n['order_id'])){$where[]='order_id=?';$params[]=(int)$n['order_id'];}
    if(!empty($n['lead_id'])){$where[]='lead_id=?';$params[]=(int)$n['lead_id'];}
    db()->prepare("UPDATE communications SET delivery_status=? WHERE id=(SELECT id FROM communications WHERE ".implode(' AND ',$where)." ORDER BY id DESC LIMIT 1)")
      ->execute([$status,...$params]);
}
function cancel_verification_messages(int $userId,string $reason='Email already verified'): void {
    if(db_table_exists('notification_queue')) db()->prepare("UPDATE notification_queue SET status='cancelled',last_error=? WHERE user_id=? AND status='queued' AND subject='Verify your SecureLink email'")->execute([$reason,$userId]);
    if(db_table_exists('communications')) db()->prepare("UPDATE communications SET delivery_status='cancelled' WHERE user_id=? AND kind='email' AND delivery_status='queued' AND subject='Verify your SecureLink email'")->execute([$userId]);
}
function process_notification_queue(int $limit=25): array {
    $limit=max(1,min(100,$limit));$sent=0;$failed=0;$skipped=0;$logged=0;
    $transport=strtolower((string)envv('MAIL_TRANSPORT','mail'));
    if(db_table_exists('users')){
        foreach(rows("SELECT id FROM users WHERE email_verified_at IS NOT NULL") as $verified) cancel_verification_messages((int)$verified['id']);
    }
    $items=rows("SELECT * FROM notification_queue WHERE status='queued' AND scheduled_at<=datetime('now') ORDER BY scheduled_at,id LIMIT ".$limit);
    foreach($items as $n){
        $type=(string)($n['notification_type']??'transactional');
        if($type==='order_followup' && !empty($n['order_id'])){
            $orderStatus=(string)(scalar("SELECT status FROM orders WHERE id=?",[(int)$n['order_id']])?:'');
            if(!in_array($orderStatus,['submitted','reviewing','need_information'],true)){
                db()->prepare("UPDATE notification_queue SET status='cancelled',last_error='Order progressed before scheduled follow-up' WHERE id=?")->execute([(int)$n['id']]);
                sync_notification_communication($n,'cancelled');continue;
            }
        }
        if($type==='lead_followup' && !empty($n['lead_id'])){
            $leadStage=(string)(scalar("SELECT stage FROM leads WHERE id=?",[(int)$n['lead_id']])?:'');
            if(in_array($leadStage,['activated','lost','order'],true)){
                db()->prepare("UPDATE notification_queue SET status='cancelled',last_error='Lead progressed before scheduled follow-up' WHERE id=?")->execute([(int)$n['id']]);
                sync_notification_communication($n,'cancelled');continue;
            }
        }
        if($transport==='log'){
            db()->prepare("UPDATE notification_queue SET status='logged',attempts=attempts+1,last_error='MAIL_TRANSPORT=log: delivery not attempted' WHERE id=?")->execute([(int)$n['id']]);
            sync_notification_communication($n,'logged');$logged++;continue;
        }
        if($transport!=='mail'){
            db()->prepare("UPDATE notification_queue SET status='failed',attempts=attempts+1,last_error=? WHERE id=?")->execute(['Unsupported MAIL_TRANSPORT: '.$transport,(int)$n['id']]);
            sync_notification_communication($n,'failed');$failed++;continue;
        }
        $recipient=trim((string)$n['recipient']);
        if($recipient===''||!filter_var($recipient,FILTER_VALIDATE_EMAIL)){
            db()->prepare("UPDATE notification_queue SET status='failed',attempts=attempts+1,last_error=? WHERE id=?")
              ->execute(['Missing or invalid email recipient',(int)$n['id']]);
            sync_notification_communication($n,'failed');$failed++;continue;
        }
        $headers="MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        $from=trim((string)envv('MAIL_FROM',''));
        if($from!=='')$headers.="From: ".$from."\r\n";
        $ok=@mail($recipient,(string)$n['subject'],(string)$n['message'],$headers);
        if($ok){
            db()->prepare("UPDATE notification_queue SET status='sent',sent_at=datetime('now'),attempts=attempts+1,last_error=NULL WHERE id=?")->execute([(int)$n['id']]);
            sync_notification_communication($n,'sent');$sent++;
        }else{
            db()->prepare("UPDATE notification_queue SET status='failed',attempts=attempts+1,last_error=? WHERE id=?")->execute(['mail() returned false',(int)$n['id']]);
            sync_notification_communication($n,'failed');$failed++;
        }
    }
    return ['sent'=>$sent,'failed'=>$failed,'logged'=>$logged,'skipped'=>$skipped];
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

    $addColumn('orders','quote_id','INTEGER');
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
    $addColumn('referrals','referrer_user_id','INTEGER');
    $addColumn('referrals','customer_user_id','INTEGER');
    $addColumn('referrals','admin_notes','TEXT');
    $addColumn('referrals','customer_notes','TEXT');
    $addColumn('referrals','updated_at','TEXT');
    db()->exec("CREATE TABLE IF NOT EXISTS customer_service_accounts(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        provider TEXT NOT NULL,
        service_type TEXT NOT NULL,
        account_number TEXT,
        cid TEXT,
        account_name TEXT,
        service_address TEXT,
        email TEXT,
        phone TEXT,
        status TEXT NOT NULL DEFAULT 'active',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    db()->exec("CREATE INDEX IF NOT EXISTS idx_customer_service_accounts_user ON customer_service_accounts(user_id,provider,service_type)");
    db()->exec("CREATE TABLE IF NOT EXISTS referral_status_history(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        referral_id INTEGER NOT NULL REFERENCES referrals(id) ON DELETE CASCADE,
        old_status TEXT,
        new_status TEXT NOT NULL,
        admin_note TEXT,
        customer_note TEXT,
        changed_by INTEGER REFERENCES users(id),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $addColumn('leads','lead_score','INTEGER NOT NULL DEFAULT 50');
    $addColumn('leads','last_contact_at','TEXT');
    $addColumn('leads','estimated_value','REAL NOT NULL DEFAULT 0');
    db()->exec("CREATE TABLE IF NOT EXISTS quotes(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT NOT NULL UNIQUE,user_id INTEGER REFERENCES users(id),lead_id INTEGER REFERENCES leads(id),customer_name TEXT NOT NULL,customer_email TEXT,customer_phone TEXT,status TEXT NOT NULL DEFAULT 'draft',deal_ids_json TEXT NOT NULL DEFAULT '[]',monthly_total REAL NOT NULL DEFAULT 0,regular_total REAL NOT NULL DEFAULT 0,credits_total REAL NOT NULL DEFAULT 0,fees_total REAL NOT NULL DEFAULT 0,term_months INTEGER NOT NULL DEFAULT 24,notes TEXT,expires_at TEXT,viewed_at TEXT,accepted_at TEXT,created_by INTEGER REFERENCES users(id),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS automation_rules(id INTEGER PRIMARY KEY AUTOINCREMENT,rule_key TEXT NOT NULL UNIQUE,name TEXT NOT NULL,enabled INTEGER NOT NULL DEFAULT 1,delay_minutes INTEGER NOT NULL DEFAULT 0,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS commission_rules(id INTEGER PRIMARY KEY AUTOINCREMENT,provider_id INTEGER NOT NULL REFERENCES providers(id),category TEXT,amount REAL NOT NULL DEFAULT 0,salesperson_percent REAL NOT NULL DEFAULT 0,active INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE INDEX IF NOT EXISTS idx_quotes_status ON quotes(status,created_at)");
    db()->exec("CREATE INDEX IF NOT EXISTS idx_commission_rules_provider ON commission_rules(provider_id,active)");
    db()->exec("CREATE TABLE IF NOT EXISTS customer_notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,title TEXT NOT NULL,message TEXT NOT NULL,link TEXT,is_read INTEGER NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE INDEX IF NOT EXISTS idx_customer_notifications_user ON customer_notifications(user_id,is_read,created_at)");
    db()->exec("CREATE TABLE IF NOT EXISTS documents(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,document_type TEXT NOT NULL,label TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'requested',storage_path TEXT,original_name TEXT,expires_at TEXT,reviewed_by INTEGER REFERENCES users(id),reviewed_at TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE INDEX IF NOT EXISTS idx_documents_user ON documents(user_id,status,created_at)");
    db()->exec("CREATE TABLE IF NOT EXISTS salesperson_profiles(user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,display_name TEXT,active INTEGER NOT NULL DEFAULT 1,commission_percent REAL NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS commission_ledger(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,sales_agent TEXT,provider_id INTEGER REFERENCES providers(id),category TEXT,gross_amount REAL NOT NULL DEFAULT 0,salesperson_amount REAL NOT NULL DEFAULT 0,company_amount REAL NOT NULL DEFAULT 0,status TEXT NOT NULL DEFAULT 'pending',earned_at TEXT,paid_at TEXT,payment_reference TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(order_id))");
    $addColumn('commission_ledger','activated_at','TEXT');
    $addColumn('commission_ledger','clawback_until','TEXT');
    $addColumn('commission_ledger','clawback_amount','REAL NOT NULL DEFAULT 0');
    $addColumn('commission_ledger','clawback_status',"TEXT NOT NULL DEFAULT 'none'");
    $addColumn('commission_ledger','clawback_reason','TEXT');
    $addColumn('commission_ledger','clawback_at','TEXT');
    db()->exec("CREATE TABLE IF NOT EXISTS commission_adjustments(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,sales_agent TEXT,amount REAL NOT NULL,adjustment_type TEXT NOT NULL DEFAULT 'clawback',reason TEXT,status TEXT NOT NULL DEFAULT 'open',created_by INTEGER REFERENCES users(id),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,resolved_at TEXT)");
    db()->exec("CREATE TABLE IF NOT EXISTS bundle_carts(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,deal_ids_json TEXT NOT NULL DEFAULT '[]',status TEXT NOT NULL DEFAULT 'active',monthly_total REAL NOT NULL DEFAULT 0,credits_total REAL NOT NULL DEFAULT 0,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS bundle_rules(id INTEGER PRIMARY KEY AUTOINCREMENT,provider_id INTEGER REFERENCES providers(id),name TEXT NOT NULL,required_categories TEXT NOT NULL DEFAULT '[]',discount_monthly REAL NOT NULL DEFAULT 0,bonus_credit REAL NOT NULL DEFAULT 0,waive_activation INTEGER NOT NULL DEFAULT 0,active INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS deployment_backups(id INTEGER PRIMARY KEY AUTOINCREMENT,filename TEXT NOT NULL,bytes INTEGER NOT NULL DEFAULT 0,sha256 TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS deal_categories(id INTEGER PRIMARY KEY AUTOINCREMENT,slug TEXT NOT NULL UNIQUE,name TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'active',display_order INTEGER NOT NULL DEFAULT 100,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS products(id INTEGER PRIMARY KEY AUTOINCREMENT,provider_id INTEGER NOT NULL REFERENCES providers(id),category_slug TEXT NOT NULL,name TEXT NOT NULL,sku TEXT,status TEXT NOT NULL DEFAULT 'active',description TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS deal_products(deal_id INTEGER PRIMARY KEY REFERENCES deals(id) ON DELETE CASCADE,product_id INTEGER REFERENCES products(id) ON DELETE SET NULL)");
    db()->exec("CREATE TABLE IF NOT EXISTS order_items(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,deal_id INTEGER REFERENCES deals(id),product_id INTEGER REFERENCES products(id),category TEXT NOT NULL,name TEXT NOT NULL,quantity INTEGER NOT NULL DEFAULT 1,monthly_price REAL NOT NULL DEFAULT 0,credit REAL NOT NULL DEFAULT 0,commission_amount REAL NOT NULL DEFAULT 0,snapshot_json TEXT NOT NULL DEFAULT '{}',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS mobility_lines(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,line_no INTEGER NOT NULL,phone_number TEXT,porting INTEGER NOT NULL DEFAULT 0,current_provider TEXT,plan_name TEXT,device_mode TEXT NOT NULL DEFAULT 'byod',imei TEXT,eid TEXT,monthly_price REAL NOT NULL DEFAULT 0,activation_fee REAL NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(order_id,line_no))");
    db()->exec("CREATE TABLE IF NOT EXISTS order_changes(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,change_type TEXT NOT NULL,summary TEXT NOT NULL,before_json TEXT,after_json TEXT,created_by INTEGER REFERENCES users(id),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS approval_requests(id INTEGER PRIMARY KEY AUTOINCREMENT,request_type TEXT NOT NULL,entity_type TEXT NOT NULL,entity_id INTEGER NOT NULL,requested_by INTEGER REFERENCES users(id),payload_json TEXT NOT NULL DEFAULT '{}',reason TEXT,status TEXT NOT NULL DEFAULT 'pending',reviewed_by INTEGER REFERENCES users(id),reviewed_at TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS service_contracts(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,order_item_id INTEGER REFERENCES order_items(id) ON DELETE SET NULL,provider_id INTEGER REFERENCES providers(id),service_type TEXT,started_at TEXT,contract_ends_at TEXT,promo_ends_at TEXT,status TEXT NOT NULL DEFAULT 'active',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS renewal_opportunities(id INTEGER PRIMARY KEY AUTOINCREMENT,service_contract_id INTEGER NOT NULL REFERENCES service_contracts(id) ON DELETE CASCADE,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,due_at TEXT,status TEXT NOT NULL DEFAULT 'open',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(service_contract_id,due_at))");
    db()->exec("CREATE TABLE IF NOT EXISTS sales_targets(id INTEGER PRIMARY KEY AUTOINCREMENT,sales_agent TEXT NOT NULL,period TEXT NOT NULL,target_orders INTEGER NOT NULL DEFAULT 0,target_activations INTEGER NOT NULL DEFAULT 0,target_commission REAL NOT NULL DEFAULT 0,target_bundles INTEGER NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(sales_agent,period))");
    db()->exec("CREATE TABLE IF NOT EXISTS role_permissions(role TEXT NOT NULL,permission TEXT NOT NULL,allowed INTEGER NOT NULL DEFAULT 1,PRIMARY KEY(role,permission))");
    db()->exec("CREATE TABLE IF NOT EXISTS import_jobs(id INTEGER PRIMARY KEY AUTOINCREMENT,import_type TEXT NOT NULL,filename TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'preview',total_rows INTEGER NOT NULL DEFAULT 0,valid_rows INTEGER NOT NULL DEFAULT 0,error_rows INTEGER NOT NULL DEFAULT 0,report_json TEXT NOT NULL DEFAULT '{}',created_by INTEGER REFERENCES users(id),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS release_runs(id INTEGER PRIMARY KEY AUTOINCREMENT,commit_sha TEXT,status TEXT NOT NULL,backup_file TEXT,preflight_output TEXT,health_output TEXT,created_by INTEGER REFERENCES users(id),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,completed_at TEXT)");
    db()->exec("CREATE TABLE IF NOT EXISTS customer_profiles(user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,service_address TEXT,alternate_phone TEXT,preferred_contact TEXT NOT NULL DEFAULT 'email',updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS support_threads(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,subject TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'open',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS support_messages(id INTEGER PRIMARY KEY AUTOINCREMENT,thread_id INTEGER NOT NULL REFERENCES support_threads(id) ON DELETE CASCADE,sender_user_id INTEGER REFERENCES users(id),message TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS quote_responses(id INTEGER PRIMARY KEY AUTOINCREMENT,quote_id INTEGER NOT NULL REFERENCES quotes(id) ON DELETE CASCADE,response_type TEXT NOT NULL,message TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS appointment_requests(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,user_id INTEGER NOT NULL REFERENCES users(id),request_type TEXT NOT NULL DEFAULT 'reschedule',preferred_window TEXT,note TEXT,status TEXT NOT NULL DEFAULT 'pending',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    db()->exec("CREATE TABLE IF NOT EXISTS login_activity(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,ip_hash TEXT,user_agent TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    foreach([['internet','Internet',10],['mobility','Mobility',20],['tv','TV',30],['homephone','Home Phone',40],['security','Security',50],['streaming','Streaming',60],['devices','Devices',70]] as $cat)db()->prepare("INSERT OR IGNORE INTO deal_categories(slug,name,status,display_order) VALUES(?,?,'active',?)")->execute($cat);
    foreach([['lead_followup','Lead follow-up',1,60],['quote_followup','Quote follow-up',1,1440],['appointment_reminder','Appointment reminder',1,1440],['offer_expiry','Offer expiry',1,0]] as $rule){
        db()->prepare("INSERT OR IGNORE INTO automation_rules(rule_key,name,enabled,delay_minutes) VALUES(?,?,?,?)")->execute($rule);
    }

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
    $version='010_operating_system_v3';
    $s=db()->prepare("SELECT 1 FROM schema_migrations WHERE version=?");$s->execute([$version]);
    if(!$s->fetchColumn()) db()->prepare("INSERT INTO schema_migrations(version) VALUES(?)")->execute([$version]);

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
        if(in_array($r['status'],['eligible','approved','paid','partial_completed','completed'],true)){$successful++;$earned+=(float)$r['reward_amount'];}
        elseif(in_array($r['status'],['pending','processing','other_processing'],true)){$pending++;}
        if(in_array($r['status'],['paid','completed'],true))$paid+=(float)$r['reward_amount'];
    }
    $program=active_referral_program($provider);
    $bonus=0.0;
    if($program){
        if($successful>=10)$bonus=(float)$program['bonus_10_amount'];
        elseif($successful>=5)$bonus=(float)$program['bonus_5_amount'];
    }
    return ['code'=>$code,'items'=>$items,'successful'=>$successful,'pending'=>$pending,'earned'=>$earned,'paid'=>$paid,'milestone_bonus'=>$bonus,'program'=>$program];
}


function order_status_steps(): array {
    return [
      'submitted'=>'Submitted','reviewing'=>'Reviewed','ready_to_process'=>'Ready',
      'submitted_to_provider'=>'Provider submitted','appointment_confirmed'=>'Appointment',
      'activated'=>'Activated','completed'=>'Completed'
    ];
}
function order_status_rank(string $status): int {
    $aliases=['need_information'=>'reviewing'];
    $status=$aliases[$status]??$status;
    $keys=array_keys(order_status_steps());$i=array_search($status,$keys,true);
    return $i===false?0:(int)$i;
}
function deal_expiry_label(?string $expires): string {
    if(!$expires) return '';
    $seconds=strtotime($expires)-time();
    if($seconds<0) return 'Expired';
    $days=(int)ceil($seconds/86400);
    if($days<=1) return 'Ends today';
    if($days<=14) return 'Ends in '.$days.' days';
    return '';
}
function business_financials(): array {
    $commission=(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE status IN ('activated','completed')");
    $paidCommission=(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE commission_status='paid'");
    $referralPaid=(float)scalar("SELECT COALESCE(SUM(reward_amount),0) FROM referrals WHERE status IN ('paid','completed')");
    $referralLiability=(float)scalar("SELECT COALESCE(SUM(reward_amount),0) FROM referrals WHERE status IN ('eligible','approved')");
    $monthCommission=(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE status IN ('activated','completed') AND created_at>=datetime('now','start of month')");
    $monthReferral=(float)scalar("SELECT COALESCE(SUM(reward_amount),0) FROM referrals WHERE status='paid' AND paid_at>=datetime('now','start of month')");
    return ['commission'=>$commission,'paid_commission'=>$paidCommission,'referral_paid'=>$referralPaid,'referral_liability'=>$referralLiability,'month_commission'=>$monthCommission,'month_referral'=>$monthReferral,'month_net'=>$monthCommission-$monthReferral];
}
function conversion_funnel_v2(int $days=30): array {
    $days=max(1,min(365,$days));$cut="-".$days." days";
    return [
      'Visitors'=>(int)scalar("SELECT COUNT(DISTINCT session_key) FROM analytics_events WHERE created_at>=datetime('now',?)",[$cut]),
      'Registrations'=>(int)scalar("SELECT COUNT(*) FROM users WHERE role='customer' AND created_at>=datetime('now',?)",[$cut]),
      'Approved'=>(int)scalar("SELECT COUNT(*) FROM users WHERE role='customer' AND approved_at IS NOT NULL AND approved_at>=datetime('now',?)",[$cut]),
      'Deal views'=>(int)scalar("SELECT COUNT(*) FROM analytics_events WHERE event_name='deals_view' AND created_at>=datetime('now',?)",[$cut]),
      'Applications'=>(int)scalar("SELECT COUNT(*) FROM analytics_events WHERE event_name='application_view' AND created_at>=datetime('now',?)",[$cut]),
      'Orders'=>(int)scalar("SELECT COUNT(*) FROM orders WHERE created_at>=datetime('now',?)",[$cut]),
      'Activated'=>(int)scalar("SELECT COUNT(*) FROM orders WHERE status IN ('activated','completed') AND created_at>=datetime('now',?)",[$cut])
    ];
}
function sync_referral_success_for_order(int $orderId): void {
    $o=rows("SELECT o.*,u.email customer_email FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=? LIMIT 1",[$orderId])[0]??null;
    if(!$o) return;
    if(in_array($o['status'],['activated','completed'],true)){
        db()->prepare("UPDATE referrals SET status=CASE WHEN status='pending' THEN 'eligible' ELSE status END,eligible_at=COALESCE(eligible_at,datetime('now')) WHERE order_id=?")->execute([$orderId]);
    } elseif(in_array($o['status'],['cancelled','rejected'],true)){
        db()->prepare("UPDATE referrals SET status=CASE WHEN status='pending' THEN 'not_eligible' ELSE status END WHERE order_id=?")->execute([$orderId]);
    }
}
function run_offer_automation(): array {
    $expired=(int)scalar("SELECT COUNT(*) FROM deals WHERE status='active' AND expires_at IS NOT NULL AND expires_at<datetime('now')");
    if($expired) db()->exec("UPDATE deals SET status='paused' WHERE status='active' AND expires_at IS NOT NULL AND expires_at<datetime('now')");
    $queued=0;
    foreach(rows("SELECT d.id,d.name,d.expires_at,p.name provider FROM deals d JOIN providers p ON p.id=d.provider_id WHERE d.status='active' AND d.expires_at IS NOT NULL AND d.expires_at BETWEEN datetime('now') AND datetime('now','+14 days')") as $d){
        $days=max(0,(int)ceil((strtotime($d['expires_at'])-time())/86400));
        if(!in_array($days,[14,7,3,1],true)) continue;
        $subject='Offer expiry · '.$d['provider'].' · '.$d['name'];
        $exists=(int)scalar("SELECT COUNT(*) FROM notification_queue WHERE notification_type='offer_expiry' AND subject=? AND date(created_at)=date('now')",[$subject]);
        if(!$exists){queue_notification(null,null,null,$subject,$d['provider'].' · '.$d['name'].' expires in '.$days.' day'.($days===1?'':'s').'. Review or extend the offer in Admin.',0,'email',envv('ADMIN_EMAIL',''),null,'offer_expiry');$queued++;}
    }
    return ['expired'=>$expired,'queued'=>$queued];
}
function customer_dashboard_data(array $u): array {
    $orders=rows("SELECT o.*,d.name deal,p.name provider FROM orders o JOIN deals d ON d.id=o.deal_id JOIN providers p ON p.id=o.provider_id WHERE o.user_id=? ORDER BY o.id DESC LIMIT 5",[(int)$u['id']]);
    $ref=referral_dashboard($u,'TELUS');
    return ['orders'=>$orders,'referrals'=>$ref,'featured'=>featured_deals(3)];
}



function customer_notification(int $userId,string $title,string $message,string $link=''): void {
    db()->prepare("INSERT INTO customer_notifications(user_id,title,message,link) VALUES(?,?,?,?)")->execute([$userId,$title,$message,$link]);
}
function unread_notification_count(int $userId): int { return (int)scalar("SELECT COUNT(*) FROM customer_notifications WHERE user_id=? AND is_read=0",[$userId]); }
function customer_360(int $userId): array {
    $u=rows("SELECT * FROM users WHERE id=? LIMIT 1",[$userId])[0]??null;if(!$u)return [];
    if(!empty($u['email_verified_at'])) cancel_verification_messages($userId);
    return ['user'=>$u,'orders'=>rows("SELECT o.*,p.name provider,d.name deal FROM orders o JOIN providers p ON p.id=o.provider_id JOIN deals d ON d.id=o.deal_id WHERE o.user_id=? ORDER BY o.id DESC",[$userId]),'quotes'=>rows("SELECT * FROM quotes WHERE user_id=? ORDER BY id DESC",[$userId]),'documents'=>rows("SELECT * FROM documents WHERE user_id=? ORDER BY id DESC",[$userId]),'communications'=>rows("SELECT * FROM communications WHERE user_id=? ORDER BY id DESC LIMIT 50",[$userId]),'notifications'=>rows("SELECT * FROM customer_notifications WHERE user_id=? ORDER BY id DESC LIMIT 50",[$userId])];
}
function global_command_search(string $q): array {
    $q=trim($q);if(strlen($q)<2)return [];$like='%'.$q.'%';$out=[];
    foreach(rows("SELECT id,name,email,phone FROM users WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? LIMIT 7",[$like,$like,$like]) as $x)$out[]=['type'=>'Customer','title'=>$x['name'],'meta'=>$x['email'].' · '.$x['phone'],'url'=>'?page=admin&view=customer360&id='.$x['id']];
    foreach(rows("SELECT id,public_id,status,provider_reference FROM orders WHERE public_id LIKE ? OR provider_reference LIKE ? OR contact_email LIKE ? OR contact_phone LIKE ? LIMIT 7",[$like,$like,$like,$like]) as $x)$out[]=['type'=>'Order','title'=>$x['public_id'],'meta'=>$x['status'].' · '.($x['provider_reference']?:'No provider ref'),'url'=>'?page=admin&view=orders&q='.rawurlencode($x['public_id'])];
    foreach(rows("SELECT id,name,email,phone,lead_score FROM leads WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? LIMIT 7",[$like,$like,$like]) as $x)$out[]=['type'=>'Lead','title'=>$x['name'],'meta'=>'Score '.$x['lead_score'].' · '.($x['phone']?:$x['email']),'url'=>'?page=admin&view=leads&edit='.$x['id']];
    foreach(rows("SELECT id,public_id,customer_name,status FROM quotes WHERE public_id LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? LIMIT 5",[$like,$like,$like]) as $x)$out[]=['type'=>'Quote','title'=>$x['public_id'],'meta'=>$x['customer_name'].' · '.$x['status'],'url'=>'?page=admin&view=quotes'];
    return array_slice($out,0,20);
}
function executive_dashboard_v3(): array {
    $month=(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE status IN ('activated','completed') AND created_at>=datetime('now','start of month')");
    $orders=(int)scalar("SELECT COUNT(*) FROM orders WHERE created_at>=datetime('now','start of month')");
    $activated=(int)scalar("SELECT COUNT(*) FROM orders WHERE status IN ('activated','completed') AND created_at>=datetime('now','start of month')");
    return ['month_commission'=>$month,'month_orders'=>$orders,'month_activated'=>$activated,'conversion'=>$orders?round($activated*100/$orders,1):0,'pipeline'=>(float)scalar("SELECT COALESCE(SUM(estimated_value),0) FROM leads WHERE stage NOT IN ('activated','lost')"),'hot_leads'=>(int)scalar("SELECT COUNT(*) FROM leads WHERE lead_score>=75 AND stage NOT IN ('activated','lost')"),'quotes_open'=>(int)scalar("SELECT COUNT(*) FROM quotes WHERE status IN ('sent','viewed')"),'unpaid_commission'=>(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE commission_status IN ('pending','approved')")];
}
function salesperson_dashboard(array $u): array {
    $name=(string)$u['name'];$leads=rows("SELECT * FROM leads WHERE sales_agent=? ORDER BY CASE WHEN next_follow_up_at IS NOT NULL AND next_follow_up_at<=datetime('now','+1 day') THEN 0 ELSE 1 END,lead_score DESC,updated_at DESC LIMIT 100",[$name]);$orders=rows("SELECT o.*,p.name provider,d.name deal FROM orders o JOIN providers p ON p.id=o.provider_id JOIN deals d ON d.id=o.deal_id WHERE o.sales_agent=? ORDER BY o.id DESC LIMIT 100",[$name]);$tasks=rows("SELECT * FROM tasks WHERE assigned_to=? AND status='open' ORDER BY priority DESC,due_at LIMIT 50",[(int)$u['id']]);$commission=(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE sales_agent=? AND commission_status IN ('pending','approved','paid')",[$name]);$pending=(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE sales_agent=? AND commission_status='pending'",[$name]);$approved=(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE sales_agent=? AND commission_status='approved'",[$name]);$paid=(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE sales_agent=? AND commission_status='paid'",[$name]);$monthOrders=(int)scalar("SELECT COUNT(*) FROM orders WHERE sales_agent=? AND created_at>=datetime('now','start of month')",[$name]);$activated=(int)scalar("SELECT COUNT(*) FROM orders WHERE sales_agent=? AND status IN ('activated','completed') AND created_at>=datetime('now','start of month')",[$name]);$followups=(int)scalar("SELECT COUNT(*) FROM leads WHERE sales_agent=? AND next_follow_up_at IS NOT NULL AND next_follow_up_at<=datetime('now','+1 day') AND stage NOT IN ('activated','lost')",[$name]);return compact('leads','orders','tasks','commission','pending','approved','paid','monthOrders','activated','followups');
}

function quote_calculate(array $dealIds): array {
    $items=[];$monthly=0.0;$regular=0.0;$credits=0.0;$fees=0.0;$term=24;
    foreach(array_unique(array_map('intval',$dealIds)) as $id){
        $d=rows("SELECT d.*,p.name provider FROM deals d JOIN providers p ON p.id=d.provider_id WHERE d.id=? LIMIT 1",[$id])[0]??null;
        if(!$d) continue;
        $items[]=$d;$monthly+=(float)$d['monthly_price'];$regular+=(float)($d['regular_price']?:$d['monthly_price']);$credits+=deal_customer_credit($d);$fees+=deal_one_time_fees($d);$term=max($term,deal_term($d));
    }
    return compact('items','monthly','regular','credits','fees','term');
}
function quote_create(array $input,int $actor): string {
    $calc=quote_calculate($input['deal_ids']??[]);if(!$calc['items']) throw new RuntimeException('Select at least one offer.');
    $public='Q-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));$ids=array_map(fn($d)=>(int)$d['id'],$calc['items']);
    db()->prepare("INSERT INTO quotes(public_id,user_id,lead_id,customer_name,customer_email,customer_phone,status,deal_ids_json,monthly_total,regular_total,credits_total,fees_total,term_months,notes,expires_at,created_by) VALUES(?,?,?,?,?,?,'sent',?,?,?,?,?,?,?,?,?)")
      ->execute([$public,(int)($input['user_id']??0)?:null,(int)($input['lead_id']??0)?:null,trim((string)$input['customer_name']),trim((string)($input['customer_email']??'')),trim((string)($input['customer_phone']??'')),json_encode($ids),$calc['monthly'],$calc['regular'],$calc['credits'],$calc['fees'],$calc['term'],trim((string)($input['notes']??'')),date('Y-m-d H:i:s',strtotime('+7 days')),$actor]);
    return $public;
}
function quote_get(string $public): ?array {
    $q=rows("SELECT * FROM quotes WHERE public_id=? LIMIT 1",[$public])[0]??null;if(!$q)return null;
    $q['items']=quote_calculate(json_decode((string)$q['deal_ids_json'],true)?:[])['items'];return $q;
}
function automation_enabled(string $key): bool { return (bool)scalar("SELECT enabled FROM automation_rules WHERE rule_key=? LIMIT 1",[$key]); }
function run_sales_automation(): array {
    $result=['tasks'=>0,'notifications'=>0,'offers'=>['expired'=>0,'queued'=>0]];
    if(automation_enabled('offer_expiry'))$result['offers']=run_offer_automation();
    if(automation_enabled('lead_followup')){
        foreach(rows("SELECT * FROM leads WHERE stage NOT IN ('activated','lost') AND created_at<=datetime('now','-60 minutes') AND (last_contact_at IS NULL)") as $lead){
            if(!(int)scalar("SELECT COUNT(*) FROM tasks WHERE entity_type='lead' AND entity_id=? AND title='Contact lead' AND status='open'",[(int)$lead['id']])){create_task('Contact lead','lead',(int)$lead['id'],null,'high',date('Y-m-d H:i:s'),'Automatic follow-up: lead has not been contacted.',0);$result['tasks']++;}
        }
    }
    if(automation_enabled('quote_followup')){
        foreach(rows("SELECT * FROM quotes WHERE status='sent' AND created_at<=datetime('now','-1 day') AND expires_at>=datetime('now')") as $q){
            if(!(int)scalar("SELECT COUNT(*) FROM notification_queue WHERE notification_type='quote_followup' AND subject=?",['Your SecureLink quote · '.$q['public_id']])){
                queue_notification($q['user_id']?(int)$q['user_id']:null,null,$q['lead_id']?(int)$q['lead_id']:null,'Your SecureLink quote · '.$q['public_id'],'Your private SecureLink quote is ready. Review it before '.$q['expires_at'].'.',0,'email',(string)$q['customer_email'],null,'quote_followup');$result['notifications']++;
            }
        }
    }
    return $result;
}
function lead_score(array $lead): int {
    $score=40;if(!empty($lead['phone']))$score+=10;if(!empty($lead['email']))$score+=8;if(!empty($lead['provider_id']))$score+=8;if(!empty($lead['deal_id']))$score+=12;
    if(in_array($lead['stage']??'',['interested','follow_up'],true))$score+=8;if(in_array($lead['stage']??'',['application','order'],true))$score+=14;
    if(!empty($lead['last_contact_at']))$score+=5;if(($lead['stage']??'')==='lost')$score=5;return max(0,min(100,$score));
}
function refresh_lead_scores(): void { foreach(rows("SELECT * FROM leads") as $l)db()->prepare("UPDATE leads SET lead_score=? WHERE id=?")->execute([lead_score($l),(int)$l['id']]); }
function commission_rule_amount(int $providerId,string $category): float {
    $v=scalar("SELECT amount FROM commission_rules WHERE active=1 AND provider_id=? AND (lower(category)=lower(?) OR category IS NULL OR category='') ORDER BY CASE WHEN lower(category)=lower(?) THEN 0 ELSE 1 END,id DESC LIMIT 1",[$providerId,$category,$category]);return (float)($v?:0);
}
function commission_for_order_v2(int $orderId): float {
    $o=rows("SELECT provider_id,category,commission_amount FROM orders WHERE id=? LIMIT 1",[$orderId])[0]??null;if(!$o)return 0.0;
    $rule=commission_rule_amount((int)$o['provider_id'],(string)$o['category']);return $rule>0?$rule:(float)$o['commission_amount'];
}
function commission_summary_v2(): array {
    return ['pending'=>(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE commission_status='pending'"),'approved'=>(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE commission_status='approved'"),'paid'=>(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE commission_status='paid'"),'chargebacks'=>(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE commission_status='void'")];
}
function commission_clawback_sync(): void {
    $active=['activated','completed'];foreach(rows("SELECT o.*,cl.id ledger_id,cl.activated_at,cl.clawback_until,cl.salesperson_amount,cl.status ledger_status,cl.clawback_status FROM orders o JOIN commission_ledger cl ON cl.order_id=o.id") as $x){
        $oid=(int)$x['id'];$isActive=in_array($x['status'],$active,true);
        if($isActive && empty($x['activated_at'])){db()->prepare("UPDATE commission_ledger SET activated_at=datetime('now'),clawback_until=datetime('now','+90 days'),status=CASE WHEN status='pending' THEN 'hold' ELSE status END WHERE id=?")->execute([(int)$x['ledger_id']]);continue;}
        if(!$isActive && !empty($x['activated_at']) && !empty($x['clawback_until']) && strtotime((string)$x['clawback_until'])>=time() && $x['clawback_status']==='none'){
            $amt=max(0,(float)$x['salesperson_amount']);db()->prepare("UPDATE commission_ledger SET clawback_amount=?,clawback_status='required',clawback_reason=?,clawback_at=datetime('now'),status=CASE WHEN status='paid' THEN status ELSE 'clawback' END WHERE id=?")->execute([$amt,'Service cancelled before 90-day retention period',(int)$x['ledger_id']]);if($amt>0)db()->prepare("INSERT INTO commission_adjustments(order_id,sales_agent,amount,adjustment_type,reason,status) VALUES(?,?,?,'clawback',?,'open')")->execute([$oid,$x['sales_agent'],-$amt,'90-day cancellation clawback']);continue;}
        if($isActive && !empty($x['clawback_until']) && strtotime((string)$x['clawback_until'])<time() && $x['clawback_status']==='none' && in_array($x['ledger_status'],['pending','hold'],true))db()->prepare("UPDATE commission_ledger SET status='approved',clawback_status='cleared' WHERE id=?")->execute([(int)$x['ledger_id']]);
    }
}
function salesperson_commission_v3(string $name): array {
    commission_clawback_sync();$risk=(float)scalar("SELECT COALESCE(SUM(salesperson_amount),0) FROM commission_ledger WHERE sales_agent=? AND status='hold'",[$name]);$protected=(float)scalar("SELECT COALESCE(SUM(salesperson_amount),0) FROM commission_ledger WHERE sales_agent=? AND status IN ('approved','paid') AND clawback_status IN ('none','cleared')",[$name]);$clawbacks=abs((float)scalar("SELECT COALESCE(SUM(amount),0) FROM commission_adjustments WHERE sales_agent=? AND adjustment_type='clawback' AND status='open'",[$name]));$payable=max(0,(float)scalar("SELECT COALESCE(SUM(salesperson_amount),0) FROM commission_ledger WHERE sales_agent=? AND status='approved'",[$name])-$clawbacks);return compact('risk','protected','clawbacks','payable');
}



function sync_commission_ledger(int $orderId): void {
    $o=rows("SELECT o.*,cr.salesperson_percent FROM orders o LEFT JOIN commission_rules cr ON cr.id=(SELECT id FROM commission_rules WHERE provider_id=o.provider_id AND active=1 AND (lower(category)=lower(o.category) OR category IS NULL OR category='') ORDER BY CASE WHEN lower(category)=lower(o.category) THEN 0 ELSE 1 END,id DESC LIMIT 1) WHERE o.id=? LIMIT 1",[$orderId])[0]??null;if(!$o)return;
    $gross=commission_for_order_v2($orderId);$pct=max(0,min(100,(float)($o['salesperson_percent']??0)));$sales=$gross*$pct/100;$company=$gross-$sales;$status=(string)($o['commission_status']??'pending');
    db()->prepare("INSERT INTO commission_ledger(order_id,sales_agent,provider_id,category,gross_amount,salesperson_amount,company_amount,status,earned_at,paid_at) VALUES(?,?,?,?,?,?,?,?,CASE WHEN ? IN ('activated','completed') THEN datetime('now') END,CASE WHEN ?='paid' THEN datetime('now') END) ON CONFLICT(order_id) DO UPDATE SET sales_agent=excluded.sales_agent,provider_id=excluded.provider_id,category=excluded.category,gross_amount=excluded.gross_amount,salesperson_amount=excluded.salesperson_amount,company_amount=excluded.company_amount,status=excluded.status,earned_at=COALESCE(commission_ledger.earned_at,excluded.earned_at),paid_at=CASE WHEN excluded.status='paid' THEN COALESCE(commission_ledger.paid_at,datetime('now')) ELSE commission_ledger.paid_at END")->execute([$orderId,$o['sales_agent'],$o['provider_id'],$o['category'],$gross,$sales,$company,$status,$o['status'],$status]);
}
function commission_payroll_v3(): array {
    return ['totals'=>['gross'=>(float)scalar("SELECT COALESCE(SUM(gross_amount),0) FROM commission_ledger"),'salesperson'=>(float)scalar("SELECT COALESCE(SUM(salesperson_amount),0) FROM commission_ledger"),'company'=>(float)scalar("SELECT COALESCE(SUM(company_amount),0) FROM commission_ledger"),'payable'=>(float)scalar("SELECT COALESCE(SUM(salesperson_amount),0) FROM commission_ledger WHERE status IN ('pending','approved')")],'agents'=>rows("SELECT COALESCE(NULLIF(sales_agent,''),'Unassigned') sales_agent,COUNT(*) orders,SUM(gross_amount) gross,SUM(salesperson_amount) payable,SUM(company_amount) company FROM commission_ledger GROUP BY COALESCE(NULLIF(sales_agent,''),'Unassigned') ORDER BY gross DESC"),'recent'=>rows("SELECT cl.*,o.public_id,p.name provider FROM commission_ledger cl JOIN orders o ON o.id=cl.order_id LEFT JOIN providers p ON p.id=cl.provider_id ORDER BY cl.id DESC LIMIT 100")];
}
function lead_intelligence(): array {
    $rows=rows("SELECT l.*,p.name provider,(SELECT MAX(created_at) FROM communications c WHERE c.lead_id=l.id) last_comm,(SELECT COUNT(*) FROM communications c WHERE c.lead_id=l.id) touches FROM leads l LEFT JOIN providers p ON p.id=l.provider_id WHERE l.stage NOT IN ('activated','lost') ORDER BY l.lead_score DESC,l.updated_at ASC");
    foreach($rows as &$x){$age=(time()-strtotime($x['last_comm']?:$x['created_at']))/3600;$x['temperature']=$x['lead_score']>=75?'Hot':($x['lead_score']>=50?'Warm':'Cold');$x['risk']=$age>=72?'Needs attention':($age>=24?'Follow up':'Active');$x['probability']=min(95,max(5,(int)round($x['lead_score']*.9)));}unset($x);return $rows;
}
function quote_analytics_v2(): array {
    $sent=(int)scalar("SELECT COUNT(*) FROM quotes");$viewed=(int)scalar("SELECT COUNT(*) FROM quotes WHERE viewed_at IS NOT NULL");$accepted=(int)scalar("SELECT COUNT(*) FROM quotes WHERE accepted_at IS NOT NULL OR status='accepted'");
    return ['sent'=>$sent,'viewed'=>$viewed,'accepted'=>$accepted,'view_rate'=>$sent?round($viewed*100/$sent,1):0,'accept_rate'=>$sent?round($accepted*100/$sent,1):0,'value'=>(float)scalar("SELECT COALESCE(SUM(monthly_total),0) FROM quotes WHERE status IN ('sent','viewed','accepted')"),'recent'=>rows("SELECT public_id,customer_name,status,monthly_total,credits_total,created_at,viewed_at,accepted_at FROM quotes ORDER BY id DESC LIMIT 50")];
}
function bundle_cart_save(int $userId,array $dealIds): array {
    $calc=quote_calculate($dealIds);$ids=array_map(fn($d)=>(int)$d['id'],$calc['items']);$json=json_encode($ids);
    $id=(int)(scalar("SELECT id FROM bundle_carts WHERE user_id=? AND status='active' ORDER BY id DESC LIMIT 1",[$userId])?:0);
    if($id)db()->prepare("UPDATE bundle_carts SET deal_ids_json=?,monthly_total=?,credits_total=?,updated_at=datetime('now') WHERE id=?")->execute([$json,$calc['monthly'],$calc['credits'],$id]);
    else{db()->prepare("INSERT INTO bundle_carts(user_id,deal_ids_json,monthly_total,credits_total) VALUES(?,?,?,?)")->execute([$userId,$json,$calc['monthly'],$calc['credits']]);$id=(int)db()->lastInsertId();}
    return ['id'=>$id,'ids'=>$ids]+$calc;
}
function bundle_cart_get(int $userId): array { $x=rows("SELECT * FROM bundle_carts WHERE user_id=? AND status='active' ORDER BY id DESC LIMIT 1",[$userId])[0]??null;if(!$x)return ['ids'=>[],'monthly_total'=>0,'credits_total'=>0];$x['ids']=array_map('intval',json_decode($x['deal_ids_json']?:'[]',true)?:[]);return $x; }
function customer_360_v2(int $userId): array {
    $x=customer_360($userId);if(!$x)return [];$x['tasks']=rows("SELECT * FROM tasks WHERE (entity_type='customer' AND entity_id=?) OR (entity_type='order' AND entity_id IN (SELECT id FROM orders WHERE user_id=?)) ORDER BY id DESC LIMIT 30",[$userId,$userId]);$x['referrals']=rows("SELECT r.* FROM referrals r JOIN orders o ON o.id=r.order_id WHERE o.user_id=? ORDER BY r.id DESC",[$userId]);$x['lifetime_value']=(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE user_id=?",[$userId]);$x['unread']=(int)scalar("SELECT COUNT(*) FROM customer_notifications WHERE user_id=? AND is_read=0",[$userId]);$x['timeline']=customer_timeline($userId);return $x;
}
function customer_360_v3(int $userId): array { $x=customer_360_v2($userId);if(!$x)return [];$x['service_accounts']=rows("SELECT * FROM customer_service_accounts WHERE user_id=? ORDER BY provider,service_type",[$userId]);$x['contracts']=renewal_centre($userId);$x['renewal_opportunities']=rows("SELECT ro.*,sc.service_type,sc.contract_ends_at,sc.promo_ends_at FROM renewal_opportunities ro JOIN service_contracts sc ON sc.id=ro.service_contract_id WHERE ro.user_id=? ORDER BY ro.due_at",[$userId]);$x['quotes']=rows("SELECT * FROM quotes WHERE user_id=? ORDER BY id DESC LIMIT 30",[$userId]);$x['communications']=rows("SELECT * FROM communications WHERE user_id=? ORDER BY id DESC LIMIT 50",[$userId]);return $x; }
function quote_convert_to_order(int $quoteId,int $actor): array { $q=rows("SELECT * FROM quotes WHERE id=? LIMIT 1",[$quoteId])[0]??null;if(!$q)throw new RuntimeException('Quote not found.');if($q['status']!=='accepted')throw new RuntimeException('Customer must accept the quote first.');$existing=rows("SELECT * FROM orders WHERE quote_id=? ORDER BY id LIMIT 1",[$quoteId])[0]??null;if($existing)return $existing;$ids=array_map('intval',json_decode($q['deal_ids_json']?:'[]',true)?:[]);if(!$ids)throw new RuntimeException('Quote has no offers.');$deal=rows("SELECT * FROM deals WHERE id=? LIMIT 1",[$ids[0]])[0]??null;if(!$deal)throw new RuntimeException('Offer unavailable.');$public='SL-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));$agent=(string)(scalar("SELECT sales_agent FROM leads WHERE id=? LIMIT 1",[(int)$q['lead_id']])?:'');db()->prepare("INSERT INTO orders(public_id,user_id,provider_id,deal_id,category,status,deal_snapshot,lead_id,sales_agent,commission_amount,commission_status,created_at) VALUES(?,?,?,?,?,'submitted',?,?,?,?, 'pending',datetime('now'))")->execute([$public,$q['user_id']?:null,(int)$deal['provider_id'],(int)$deal['id'],$deal['category'],json_encode($deal),(int)$q['lead_id']?:null,$agent,commission_rule_amount((int)$deal['provider_id'],(string)$deal['category'])]);$oid=(int)db()->lastInsertId();db()->prepare("UPDATE orders SET quote_id=? WHERE id=?")->execute([$quoteId,$oid]);db()->prepare("UPDATE quotes SET status='converted',updated_at=datetime('now') WHERE id=?")->execute([$quoteId]);sync_order_items($oid);audit($actor,'quote_convert_order','order',$oid,['quote_id'=>$quoteId]);return rows("SELECT * FROM orders WHERE id=?",[$oid])[0]; }

function admin_attention_centre(): array { return ['overdue_tasks'=>(int)scalar("SELECT COUNT(*) FROM tasks WHERE status='open' AND due_at IS NOT NULL AND due_at<datetime('now')"),'stale_leads'=>(int)scalar("SELECT COUNT(*) FROM leads WHERE stage NOT IN ('activated','lost') AND updated_at<datetime('now','-48 hours')"),'accepted_quotes'=>(int)scalar("SELECT COUNT(*) FROM quotes WHERE status='accepted'"),'failed_notifications'=>(int)scalar("SELECT COUNT(*) FROM notification_queue WHERE status='failed'"),'clawbacks'=>(int)scalar("SELECT COUNT(*) FROM commission_adjustments WHERE adjustment_type='clawback' AND status='open'"),'renewals'=>(int)scalar("SELECT COUNT(*) FROM renewal_opportunities WHERE status='open' AND due_at<=datetime('now','+30 days')")]; }
function finance_control_v4(): array { commission_clawback_sync();return ['gross'=>(float)scalar("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE status IN ('activated','completed')"),'protected'=>(float)scalar("SELECT COALESCE(SUM(salesperson_amount),0) FROM commission_ledger WHERE status IN ('approved','paid') AND clawback_status IN ('none','cleared')"),'hold'=>(float)scalar("SELECT COALESCE(SUM(salesperson_amount),0) FROM commission_ledger WHERE status='hold'"),'clawbacks'=>abs((float)scalar("SELECT COALESCE(SUM(amount),0) FROM commission_adjustments WHERE adjustment_type='clawback' AND status='open'")),'referral_liability'=>(float)scalar("SELECT COALESCE(SUM(reward_amount),0) FROM referrals WHERE status IN ('pending','processing','partial_completed','other_processing','approved','eligible')"),'customer_credits'=>(float)scalar("SELECT COALESCE(SUM(credit),0) FROM order_items")]; }
function audit_centre_v2(): array { return rows("SELECT a.*,u.name actor_name,u.email actor_email FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_user_id ORDER BY a.id DESC LIMIT 250"); }
function global_search_v2(string $q): array {
    $out=global_command_search($q);$like='%'.trim($q).'%';if(strlen(trim($q))>=2){foreach(rows("SELECT d.id,d.name,p.name provider,d.category FROM deals d JOIN providers p ON p.id=d.provider_id WHERE d.name LIKE ? OR p.name LIKE ? OR d.category LIKE ? LIMIT 6",[$like,$like,$like]) as $x)$out[]=['type'=>'Offer','title'=>$x['provider'].' · '.$x['name'],'meta'=>$x['category'],'url'=>'?page=admin&view=deals&edit='.$x['id']];foreach(rows("SELECT id,title,status,priority FROM tasks WHERE title LIKE ? OR description LIKE ? LIMIT 5",[$like,$like]) as $x)$out[]=['type'=>'Task','title'=>$x['title'],'meta'=>$x['priority'].' · '.$x['status'],'url'=>'?page=admin&view=tasks'];}return array_slice($out,0,30);
}
function executive_dashboard_v4(): array {
    $x=executive_dashboard_v3();$q=quote_analytics_v2();$pay=commission_payroll_v3();$x['quote_view_rate']=$q['view_rate'];$x['quote_accept_rate']=$q['accept_rate'];$x['quote_value']=$q['value'];$x['payroll_payable']=$pay['totals']['payable'];$x['leads_attention']=(int)scalar("SELECT COUNT(*) FROM leads WHERE stage NOT IN ('activated','lost') AND updated_at<datetime('now','-48 hours')");$x['documents_pending']=(int)scalar("SELECT COUNT(*) FROM documents WHERE status IN ('requested','received')");$x['provider_mix']=rows("SELECT p.name provider,COUNT(*) orders,SUM(o.commission_amount) commission FROM orders o JOIN providers p ON p.id=o.provider_id GROUP BY p.id ORDER BY orders DESC");return $x;
}


function bundle_price_v3(array $dealIds): array {
    $base=quote_calculate($dealIds);$discount=0.0;$bonus=0.0;$waived=0.0;$applied=[];$byProvider=[];
    foreach($base['items'] as $d){$byProvider[(int)$d['provider_id']][]=strtolower((string)$d['category']);}
    foreach(rows("SELECT br.*,p.name provider FROM bundle_rules br LEFT JOIN providers p ON p.id=br.provider_id WHERE br.active=1 ORDER BY br.id") as $r){
        $required=array_values(array_filter(array_map('strtolower',json_decode($r['required_categories']?:'[]',true)?:[])));$providers=$r['provider_id']?[(int)$r['provider_id']=>($byProvider[(int)$r['provider_id']]??[])]:$byProvider;
        foreach($providers as $cats){if($required && count(array_diff($required,$cats))===0){$discount+=(float)$r['discount_monthly'];$bonus+=(float)$r['bonus_credit'];if((int)$r['waive_activation']){$waived=$base['fees'];}$applied[]=$r['name'];break;}}
    }
    $base['bundle_discount']=$discount;$base['bundle_credit']=$bonus;$base['waived_fees']=$waived;$base['final_monthly']=max(0,$base['monthly']-$discount);$base['final_credits']=$base['credits']+$bonus;$base['final_fees']=max(0,$base['fees']-$waived);$base['applied_rules']=$applied;return $base;
}
function operations_centre(): array {
    return rows("SELECT o.*,u.name customer,p.name provider,d.name deal,(SELECT COUNT(*) FROM documents x WHERE x.order_id=o.id AND x.status NOT IN ('approved')) missing_docs FROM orders o JOIN users u ON u.id=o.user_id JOIN providers p ON p.id=o.provider_id JOIN deals d ON d.id=o.deal_id WHERE o.status NOT IN ('completed','cancelled','rejected') ORDER BY CASE o.status WHEN 'submitted' THEN 0 WHEN 'reviewing' THEN 1 WHEN 'need_information' THEN 2 WHEN 'ready_to_process' THEN 3 WHEN 'submitted_to_provider' THEN 4 WHEN 'appointment_confirmed' THEN 5 WHEN 'activated' THEN 6 ELSE 7 END,o.created_at");
}
function smart_action_queue(?string $salesAgent=null): array {
    $out=[];$agentSql=$salesAgent!==null?" AND sales_agent=?":"";$params=$salesAgent!==null?[$salesAgent]:[];
    foreach(rows("SELECT * FROM leads WHERE stage NOT IN ('activated','lost') AND updated_at<datetime('now','-24 hours')".$agentSql." ORDER BY lead_score DESC LIMIT 30",$params) as $l)$out[]=['priority'=>$l['lead_score']>=75?'urgent':'high','type'=>'Lead','title'=>'Follow up '.$l['name'],'reason'=>'No lead activity in 24+ hours','url'=>'?page=admin&view=leads&edit='.$l['id']];
    foreach(rows("SELECT * FROM quotes WHERE viewed_at IS NOT NULL AND accepted_at IS NULL AND status IN ('sent','viewed') AND viewed_at<datetime('now','-6 hours') ORDER BY viewed_at LIMIT 20") as $q)$out[]=['priority'=>'high','type'=>'Quote','title'=>'Follow up '.$q['customer_name'],'reason'=>'Quote viewed but not accepted','url'=>'?page=admin&view=quotes'];
    foreach(rows("SELECT o.*,u.name customer FROM orders o JOIN users u ON u.id=o.user_id WHERE o.status='submitted_to_provider' AND (o.provider_reference IS NULL OR o.provider_reference='') AND o.updated_at<datetime('now','-12 hours') ORDER BY o.updated_at LIMIT 20") as $o)$out[]=['priority'=>'urgent','type'=>'Order','title'=>'Provider reference missing · '.$o['customer'],'reason'=>$o['public_id'].' submitted 12+ hours ago','url'=>'?page=admin&view=operations'];
    return array_slice($out,0,60);
}
function salesperson_performance(): array {
    return rows("SELECT COALESCE(NULLIF(o.sales_agent,''),'Unassigned') sales_agent,COUNT(o.id) orders,SUM(CASE WHEN o.status IN ('activated','completed') THEN 1 ELSE 0 END) activations,ROUND(100.0*SUM(CASE WHEN o.status IN ('activated','completed') THEN 1 ELSE 0 END)/CASE WHEN COUNT(o.id)=0 THEN 1 ELSE COUNT(o.id) END,1) conversion,COALESCE(SUM(o.commission_amount),0) commission,COALESCE(AVG(CASE WHEN o.first_contact_at IS NOT NULL THEN (julianday(o.first_contact_at)-julianday(o.created_at))*1440 END),0) avg_response_minutes FROM orders o GROUP BY COALESCE(NULLIF(o.sales_agent,''),'Unassigned') ORDER BY activations DESC,commission DESC");
}
function security_health(): array {
    $path=database_path();return ['db_exists'=>is_file($path),'db_writable'=>is_file($path)&&is_writable($path),'app_key'=>(strlen((string)envv('APP_KEY',''))===64),'debug'=>envv('APP_DEBUG','false')==='true','https'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https','backups'=>(int)scalar("SELECT COUNT(*) FROM deployment_backups"),'failed_notifications'=>(int)scalar("SELECT COUNT(*) FROM notification_queue WHERE status='failed'")];
}


function permission_defaults(): array { return ['owner'=>['*'],'admin'=>['catalog.manage','orders.manage','customers.manage','sales.manage','finance.view','imports.manage'],'manager'=>['catalog.manage','orders.manage','customers.manage','sales.manage'],'salesperson'=>['orders.own','customers.own','quotes.manage','leads.own'],'support'=>['orders.manage','customers.manage','documents.manage'],'finance'=>['finance.view','finance.manage','imports.manage']]; }
function can_permission(array $u,string $permission): bool { if(in_array($u['role'],['owner'],true))return true;$override=scalar("SELECT allowed FROM role_permissions WHERE role=? AND permission=? LIMIT 1",[$u['role'],$permission]);if($override!==false&&$override!==null)return (bool)$override;$defs=permission_defaults();return in_array('*',$defs[$u['role']]??[],true)||in_array($permission,$defs[$u['role']]??[],true); }
function product_catalog(): array { return rows("SELECT pr.*,p.name provider,dc.name category_name,(SELECT COUNT(*) FROM deal_products dp WHERE dp.product_id=pr.id) offers FROM products pr JOIN providers p ON p.id=pr.provider_id LEFT JOIN deal_categories dc ON lower(dc.slug)=lower(pr.category_slug) ORDER BY p.name,dc.display_order,pr.name"); }
function clone_deal(int $dealId,int $actor): int { $d=deal_snapshot_state($dealId);if(!$d)throw new RuntimeException('Offer not found.');$fields=['provider_id','category','name','description','monthly_price','regular_price','bill_credit','referral_reward','other_reward','activation_fee','installation_fee','term_months','speed_data','fine_print'];$vals=[];foreach($fields as $f)$vals[]=$f==='name'?$d[$f].' · Copy':($d[$f]??null);$vals[]='draft';db()->prepare("INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,bill_credit,referral_reward,other_reward,activation_fee,installation_fee,term_months,speed_data,fine_print,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute($vals);$id=(int)db()->lastInsertId();$product=scalar("SELECT product_id FROM deal_products WHERE deal_id=?",[$dealId]);if($product)db()->prepare("INSERT INTO deal_products(deal_id,product_id) VALUES(?,?)")->execute([$id,$product]);record_deal_history($id,$actor,'clone');return $id; }
function order_profit(int $orderId): array { $o=rows("SELECT commission_amount FROM orders WHERE id=?",[$orderId])[0]??[];$gross=(float)($o['commission_amount']??0);$sales=(float)(scalar("SELECT salesperson_amount FROM commission_ledger WHERE order_id=?",[$orderId])?:0);$ref=(float)(scalar("SELECT COALESCE(SUM(reward_amount),0) FROM referrals WHERE order_id=? AND status NOT IN ('cancelled','rejected')",[$orderId])?:0);$credits=(float)(scalar("SELECT COALESCE(SUM(credit),0) FROM order_items WHERE order_id=?",[$orderId])?:0);return ['gross'=>$gross,'salesperson'=>$sales,'referrals'=>$ref,'customer_incentives'=>$credits,'net'=>$gross-$sales-$ref-$credits]; }
function customer_timeline(int $userId): array { $events=[];foreach(rows("SELECT 'order' type,id,public_id title,status detail,created_at FROM orders WHERE user_id=?",[$userId]) as $x)$events[]=$x;foreach(rows("SELECT 'quote' type,id,public_id title,status detail,created_at FROM quotes WHERE user_id=?",[$userId]) as $x)$events[]=$x;foreach(rows("SELECT 'communication' type,id,COALESCE(subject,kind) title,delivery_status detail,created_at FROM communications WHERE user_id=?",[$userId]) as $x)$events[]=$x;foreach(rows("SELECT 'document' type,id,label title,status detail,created_at FROM documents WHERE user_id=?",[$userId]) as $x)$events[]=$x;usort($events,fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));return array_slice($events,0,100); }
function salesperson_targets_v2(): array { $period=date('Y-m');$perf=salesperson_performance();foreach($perf as &$p){$t=rows("SELECT * FROM sales_targets WHERE sales_agent=? AND period=? LIMIT 1",[$p['sales_agent'],$period])[0]??[];$p['target_orders']=(int)($t['target_orders']??0);$p['target_activations']=(int)($t['target_activations']??0);$p['target_commission']=(float)($t['target_commission']??0);$p['activation_progress']=$p['target_activations']?min(100,round($p['activations']*100/$p['target_activations'])):0;$p['commission_progress']=$p['target_commission']?min(100,round($p['commission']*100/$p['target_commission'])):0;}unset($p);return $perf; }


function customer_services(int $userId): array { return rows("SELECT o.*,p.name provider,d.name deal FROM orders o JOIN providers p ON p.id=o.provider_id JOIN deals d ON d.id=o.deal_id WHERE o.user_id=? AND o.status IN ('activated','completed') ORDER BY o.id DESC",[$userId]); }
function customer_savings(int $userId): array { $services=customer_services($userId);$monthly=0;$regular=0;$credits=0;$termSavings=0;foreach($services as &$o){$d=json_decode($o['deal_snapshot']?:'{}',true)?:[];$m=(float)($d['monthly_price']??0);$r=(float)($d['regular_price']??$m);$credit=(float)($d['bill_credit']??0);$term=max(1,(int)($d['term_months']??24));$save=max(0,$r-$m);$o['monthly']=$m;$o['regular']=$r;$o['credit']=$credit;$o['monthly_saving']=$save;$o['term_saving']=$save*$term+$credit;$monthly+=$m;$regular+=$r;$credits+=$credit;$termSavings+=$o['term_saving'];}unset($o);return compact('services','monthly','regular','credits','termSavings'); }
function customer_action_centre(int $userId): array { $out=[];foreach(rows("SELECT d.*,o.public_id FROM documents d LEFT JOIN orders o ON o.id=d.order_id WHERE d.user_id=? AND d.status IN ('requested','needs_replacement') ORDER BY d.id",[$userId]) as $d)$out[]=['type'=>'document','title'=>($d['status']==='needs_replacement'?'Replace ':'Upload ').$d['label'],'meta'=>$d['public_id']?:'Document requested','url'=>'?page=documents'];foreach(rows("SELECT o.* FROM orders o WHERE o.user_id=? AND o.status='need_information' ORDER BY o.id DESC",[$userId]) as $o)$out[]=['type'=>'order','title'=>'Information needed for '.$o['public_id'],'meta'=>'SecureLink needs information before processing can continue.','url'=>'?page=support&order_id='.$o['id']];foreach(rows("SELECT o.* FROM orders o WHERE o.user_id=? AND o.appointment_at IS NOT NULL AND o.status='appointment_confirmed' AND o.appointment_at>=datetime('now') ORDER BY o.appointment_at LIMIT 3",[$userId]) as $o)$out[]=['type'=>'appointment','title'=>'Upcoming appointment · '.date('M j · g:i A',strtotime($o['appointment_at'])),'meta'=>$o['public_id'],'url'=>'?page=appointments'];return $out; }
function customer_portal_v3(array $u): array { $base=customer_dashboard_data($u);$base['services']=customer_services((int)$u['id']);$base['savings']=customer_savings((int)$u['id']);$base['actions']=customer_action_centre((int)$u['id']);$base['documents_pending']=(int)scalar("SELECT COUNT(*) FROM documents WHERE user_id=? AND status IN ('requested','needs_replacement')",[(int)$u['id']]);$base['support_open']=(int)scalar("SELECT COUNT(*) FROM support_threads WHERE user_id=? AND status='open'",[(int)$u['id']]);$base['renewals']=renewal_centre((int)$u['id']);$base['household']=customer_household((int)$u['id']);return $base; }
function order_next_step(array $o): string { return match($o['status']){'submitted'=>'SecureLink will review your request.','reviewing'=>'Your order is being verified.','need_information'=>'We need information from you.','ready_to_process'=>'Your order is ready for provider submission.','submitted_to_provider'=>'The provider is processing your order.','appointment_confirmed'=>'Prepare for your scheduled appointment.','activated'=>'Your service is active.','completed'=>'Everything is complete.',default=>'Check back for the next update.'}; }


function sync_order_items(int $orderId): void { if((int)scalar("SELECT COUNT(*) FROM order_items WHERE order_id=?",[$orderId])>0)return;$o=rows("SELECT o.*,d.name deal_name,dp.product_id FROM orders o JOIN deals d ON d.id=o.deal_id LEFT JOIN deal_products dp ON dp.deal_id=d.id WHERE o.id=? LIMIT 1",[$orderId])[0]??null;if(!$o)return;$snap=json_decode($o['deal_snapshot']?:'{}',true)?:[];db()->prepare("INSERT INTO order_items(order_id,deal_id,product_id,category,name,quantity,monthly_price,credit,commission_amount,snapshot_json) VALUES(?,?,?,?,?,1,?,?,?,?)")->execute([$orderId,$o['deal_id'],$o['product_id']?:null,$o['category'],$o['deal_name'],(float)($snap['monthly_price']??0),(float)($snap['bill_credit']??0),commission_for_order_v2($orderId),json_encode($snap)]); }
function active_promotions_for_items(array $items,bool $newCustomer=true): array { $out=[];$cats=array_map(fn($x)=>strtolower((string)$x['category']),$items);$monthly=array_sum(array_map(fn($x)=>(float)$x['monthly_price'], $items));foreach(rows("SELECT * FROM promotions WHERE status='active' AND (starts_at IS NULL OR starts_at<=datetime('now')) AND (ends_at IS NULL OR ends_at>=datetime('now')) ORDER BY id") as $p){if($p['new_customer_only']&&!$newCustomer)continue;if($monthly<(float)$p['min_monthly'])continue;$req=array_map('strtolower',json_decode($p['required_categories']?:'[]',true)?:[]);if($req&&array_diff($req,$cats))continue;$out[]=$p;}return $out; }
function renewal_centre(int $userId): array { $contracts=rows("SELECT sc.*,o.public_id,oi.name item_name,oi.monthly_price FROM service_contracts sc LEFT JOIN orders o ON o.id=sc.order_id LEFT JOIN order_items oi ON oi.id=sc.order_item_id WHERE sc.user_id=? AND sc.status='active' ORDER BY COALESCE(sc.promo_ends_at,sc.contract_ends_at,'9999-12-31')",[$userId]);foreach($contracts as &$x){$date=$x['promo_ends_at']?:$x['contract_ends_at'];$x['days_remaining']=$date?(int)ceil((strtotime($date)-time())/86400):null;}unset($x);return $contracts; }
function generate_renewal_opportunities(): int { $n=0;foreach(rows("SELECT * FROM service_contracts WHERE status='active' AND COALESCE(promo_ends_at,contract_ends_at) IS NOT NULL") as $contract){$end=$contract['promo_ends_at']?:$contract['contract_ends_at'];$endTs=strtotime((string)$end);if(!$endTs||$endTs<time())continue;foreach([90,60,30] as $days){$due=date('Y-m-d H:i:s',$endTs-($days*86400));if(strtotime($due)>time()+86400)continue;$st=db()->prepare("INSERT OR IGNORE INTO renewal_opportunities(service_contract_id,user_id,order_id,due_at) VALUES(?,?,?,?)");$st->execute([$contract['id'],$contract['user_id'],$contract['order_id'],$due]);$n+=$st->rowCount();}}return $n; }
function customer_household(int $userId): array { $h=rows("SELECT * FROM households WHERE owner_user_id=? ORDER BY id LIMIT 1",[$userId])[0]??null;if(!$h)return ['household'=>null,'members'=>[]];return ['household'=>$h,'members'=>rows("SELECT * FROM household_members WHERE household_id=? ORDER BY id",[$h['id']])]; }
function notification_preferences_get(int $userId): array { $x=rows("SELECT * FROM notification_preferences WHERE user_id=? LIMIT 1",[$userId])[0]??null;return $x?:['user_id'=>$userId,'order_email'=>1,'order_sms'=>0,'appointment_email'=>1,'appointment_sms'=>0,'promo_email'=>1,'promo_sms'=>0,'referral_email'=>1,'referral_sms'=>0]; }
function customer_session_hash(): string { return hash('sha256',session_id()); }
function customer_session_register(int $userId): void { if(session_status()!==PHP_SESSION_ACTIVE||!db_table_exists('user_sessions'))return;$hash=customer_session_hash();$ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'');db()->prepare("INSERT INTO user_sessions(user_id,session_hash,user_agent,ip_hash,last_seen_at) VALUES(?,?,?,?,datetime('now')) ON CONFLICT(session_hash) DO UPDATE SET user_id=excluded.user_id,user_agent=excluded.user_agent,ip_hash=excluded.ip_hash,last_seen_at=datetime('now'),revoked_at=NULL")->execute([$userId,$hash,substr($_SERVER['HTTP_USER_AGENT']??'',0,250),$ip]); }
function customer_session_allowed(int $userId): bool { if(!db_table_exists('user_sessions'))return true;$hash=customer_session_hash();$s=db()->prepare("SELECT revoked_at FROM user_sessions WHERE user_id=? AND session_hash=? LIMIT 1");$s->execute([$userId,$hash]);$row=$s->fetch();return !$row||empty($row['revoked_at']); }
function customer_sessions(int $userId): array { return rows("SELECT *,CASE WHEN session_hash=? THEN 1 ELSE 0 END is_current FROM user_sessions WHERE user_id=? ORDER BY last_seen_at DESC LIMIT 20",[customer_session_hash(),$userId]); }
function revoke_customer_session(int $userId,int $sessionId): bool { $s=db()->prepare("UPDATE user_sessions SET revoked_at=datetime('now') WHERE id=? AND user_id=? AND revoked_at IS NULL");$s->execute([$sessionId,$userId]);return $s->rowCount()>0; }
function communication_template(string $key,array $vars=[]): ?array { $t=rows("SELECT * FROM communication_templates WHERE template_key=? AND active=1 LIMIT 1",[$key])[0]??null;if(!$t)return null;foreach($vars as $k=>$v){$t['subject']=str_replace('{{'.$k.'}}',(string)$v,$t['subject']);$t['message']=str_replace('{{'.$k.'}}',(string)$v,$t['message']);}return $t; }
function sync_customer_order_event(int $orderId,string $status,int $actor=0): void { $message=order_next_step(['status'=>$status]);db()->prepare("INSERT INTO order_status_events(order_id,status,message,actor_user_id) VALUES(?,?,?,?)")->execute([$orderId,$status,$message,$actor?:null]);queue_order_status_notification($orderId,$actor); }
function customer_feedback_summary(): array { return ['responses'=>(int)scalar("SELECT COUNT(*) FROM customer_feedback"),'nps'=>(float)(scalar("SELECT COALESCE(AVG(CASE WHEN score>=9 THEN 100 WHEN score<=6 THEN -100 ELSE 0 END),0) FROM customer_feedback")?:0),'rating'=>(float)(scalar("SELECT COALESCE(AVG(rating),0) FROM customer_feedback")?:0),'attention'=>(int)scalar("SELECT COUNT(*) FROM customer_feedback WHERE score<=6 AND status='new'")]; }

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
    if(!$u) return;
    if(!empty($u['email_verified_at'])){ cancel_verification_messages($userId); return; }
    cancel_verification_messages($userId,'Superseded by a newer verification link');
    db()->prepare("DELETE FROM email_verification_tokens WHERE user_id=? AND used_at IS NULL")->execute([$userId]);
    $token=bin2hex(random_bytes(32));
    db()->prepare("INSERT INTO email_verification_tokens(user_id,token_hash,expires_at) VALUES(?,?,datetime('now','+24 hours'))")
      ->execute([$userId,hash('sha256',$token)]);
    $link=app_absolute_url('?page=verify-email&token='.rawurlencode($token));
    $subject='Verify your SecureLink email';$message="Hi ".($u['name']?:'there').",\n\nVerify your email to finish securing your SecureLink account:\n".$link."\n\nThis link expires in 24 hours.";
    if(db_table_exists('notification_queue')) queue_notification($userId,null,null,$subject,$message,$userId,'email',(string)$u['email'],null,'security_verification');
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
        cancel_verification_messages((int)$row['user_id'],'Email verified');
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
    if(db_table_exists('notification_queue')) queue_notification((int)$u['id'],null,null,$subject,$message,(int)$u['id'],'email',(string)$u['email'],null,'security_reset');
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
