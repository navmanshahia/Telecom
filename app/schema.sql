PRAGMA foreign_keys=ON;
CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,email TEXT NOT NULL UNIQUE,phone TEXT,password_hash TEXT NOT NULL,role TEXT NOT NULL DEFAULT 'customer',status TEXT NOT NULL DEFAULT 'pending',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,approved_at TEXT,email_verified_at TEXT);
CREATE TABLE IF NOT EXISTS providers(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL UNIQUE,description TEXT,website TEXT,status TEXT NOT NULL DEFAULT 'active',display_order INTEGER NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS deals(id INTEGER PRIMARY KEY AUTOINCREMENT,provider_id INTEGER NOT NULL REFERENCES providers(id),category TEXT NOT NULL,name TEXT NOT NULL,description TEXT,monthly_price REAL NOT NULL DEFAULT 0,regular_price REAL,bill_credit REAL NOT NULL DEFAULT 0,referral_reward REAL NOT NULL DEFAULT 0,other_reward REAL NOT NULL DEFAULT 0,activation_fee REAL NOT NULL DEFAULT 0,installation_fee REAL NOT NULL DEFAULT 0,term_months INTEGER,speed_data TEXT,fine_print TEXT,status TEXT NOT NULL DEFAULT 'draft',starts_at TEXT,expires_at TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS sale_sources(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL UNIQUE,active INTEGER NOT NULL DEFAULT 1);
CREATE TABLE IF NOT EXISTS campaigns(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,code TEXT NOT NULL UNIQUE,source_id INTEGER REFERENCES sale_sources(id),status TEXT NOT NULL DEFAULT 'active',destination TEXT NOT NULL DEFAULT '/?page=register',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS leads(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,email TEXT,phone TEXT,stage TEXT NOT NULL DEFAULT 'lead',provider_id INTEGER REFERENCES providers(id),deal_id INTEGER REFERENCES deals(id),source_id INTEGER REFERENCES sale_sources(id),campaign_id INTEGER REFERENCES campaigns(id),referred_by TEXT,sales_agent TEXT,next_follow_up_at TEXT,lost_reason TEXT,notes TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS orders(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT NOT NULL UNIQUE,user_id INTEGER NOT NULL REFERENCES users(id),deal_id INTEGER NOT NULL REFERENCES deals(id),provider_id INTEGER NOT NULL REFERENCES providers(id),category TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'submitted',deal_snapshot TEXT NOT NULL,source_id INTEGER REFERENCES sale_sources(id),campaign_id INTEGER REFERENCES campaigns(id),lead_id INTEGER REFERENCES leads(id),referred_by TEXT,sales_agent TEXT,contact_email TEXT NOT NULL,contact_phone TEXT NOT NULL,service_address TEXT,basement_details TEXT,porting INTEGER NOT NULL DEFAULT 0,current_phone TEXT,current_provider TEXT,current_account_number TEXT,imei TEXT,eid TEXT,id_type TEXT,id_value_encrypted TEXT,provider_reference TEXT,appointment_at TEXT,admin_notes TEXT,lost_reason TEXT,first_contact_at TEXT,commission_amount REAL NOT NULL DEFAULT 0,commission_status TEXT NOT NULL DEFAULT 'pending',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS referrals(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER REFERENCES orders(id),referred_by TEXT NOT NULL,referred_customer TEXT NOT NULL,reward_amount REAL NOT NULL DEFAULT 0,status TEXT NOT NULL DEFAULT 'pending',eligible_at TEXT,paid_at TEXT,payment_reference TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS tasks(id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT NOT NULL,description TEXT,entity_type TEXT NOT NULL,entity_id INTEGER NOT NULL,assigned_to INTEGER REFERENCES users(id),status TEXT NOT NULL DEFAULT 'open',priority TEXT NOT NULL DEFAULT 'normal',due_at TEXT,created_by INTEGER REFERENCES users(id),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,completed_at TEXT);
CREATE TABLE IF NOT EXISTS communications(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER REFERENCES users(id),lead_id INTEGER REFERENCES leads(id),order_id INTEGER REFERENCES orders(id),kind TEXT NOT NULL,visibility TEXT NOT NULL DEFAULT 'internal',subject TEXT,message TEXT NOT NULL,delivery_status TEXT NOT NULL DEFAULT 'logged',created_by INTEGER REFERENCES users(id),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS audit_logs(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER REFERENCES users(id),action TEXT NOT NULL,entity TEXT,entity_id INTEGER,metadata TEXT,ip TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
INSERT OR IGNORE INTO sale_sources(name) VALUES ('Referral'),('Website'),('Door-to-door'),('Existing Customer'),('Repeat Customer'),('Social Media'),('QR Campaign'),('Phone'),('Technician'),('Other');
CREATE TABLE IF NOT EXISTS deal_merchandising(deal_id INTEGER PRIMARY KEY REFERENCES deals(id) ON DELETE CASCADE,featured INTEGER NOT NULL DEFAULT 0,display_order INTEGER NOT NULL DEFAULT 0,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS rate_limits(scope TEXT NOT NULL,key_hash TEXT NOT NULL,hits INTEGER NOT NULL DEFAULT 0,window_started INTEGER NOT NULL,PRIMARY KEY(scope,key_hash));

CREATE TABLE IF NOT EXISTS schema_migrations(version TEXT PRIMARY KEY,applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS email_verification_tokens(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,token_hash TEXT NOT NULL UNIQUE,expires_at TEXT NOT NULL,used_at TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS idx_email_verification_user ON email_verification_tokens(user_id,used_at);
CREATE TABLE IF NOT EXISTS password_reset_tokens(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,token_hash TEXT NOT NULL UNIQUE,expires_at TEXT NOT NULL,used_at TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS idx_password_reset_user ON password_reset_tokens(user_id,used_at);

CREATE TABLE IF NOT EXISTS deal_history(
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 deal_id INTEGER NOT NULL REFERENCES deals(id) ON DELETE CASCADE,
 version_no INTEGER NOT NULL,
 snapshot_json TEXT NOT NULL,
 change_type TEXT NOT NULL DEFAULT 'update',
 changed_by INTEGER REFERENCES users(id),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(deal_id,version_no)
);
CREATE INDEX IF NOT EXISTS idx_deal_history_deal ON deal_history(deal_id,version_no DESC);

CREATE TABLE IF NOT EXISTS notification_queue(
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
 order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,
 lead_id INTEGER REFERENCES leads(id) ON DELETE SET NULL,
 channel TEXT NOT NULL DEFAULT 'email',
 notification_type TEXT NOT NULL DEFAULT 'transactional',
 recipient TEXT,
 subject TEXT NOT NULL,
 message TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'queued',
 scheduled_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 sent_at TEXT,
 attempts INTEGER NOT NULL DEFAULT 0,
 last_error TEXT,
 created_by INTEGER REFERENCES users(id),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_notification_queue_due ON notification_queue(status,scheduled_at);

CREATE TABLE IF NOT EXISTS analytics_events(
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
 event_name TEXT NOT NULL,
 entity_type TEXT,
 entity_id INTEGER,
 session_key TEXT,
 metadata TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_analytics_events_name_time ON analytics_events(event_name,created_at);


CREATE TABLE IF NOT EXISTS quotes(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT NOT NULL UNIQUE,user_id INTEGER REFERENCES users(id),lead_id INTEGER REFERENCES leads(id),customer_name TEXT NOT NULL,customer_email TEXT,customer_phone TEXT,status TEXT NOT NULL DEFAULT 'draft',deal_ids_json TEXT NOT NULL DEFAULT '[]',monthly_total REAL NOT NULL DEFAULT 0,regular_total REAL NOT NULL DEFAULT 0,credits_total REAL NOT NULL DEFAULT 0,fees_total REAL NOT NULL DEFAULT 0,term_months INTEGER NOT NULL DEFAULT 24,notes TEXT,expires_at TEXT,viewed_at TEXT,accepted_at TEXT,created_by INTEGER REFERENCES users(id),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS automation_rules(id INTEGER PRIMARY KEY AUTOINCREMENT,rule_key TEXT NOT NULL UNIQUE,name TEXT NOT NULL,enabled INTEGER NOT NULL DEFAULT 1,delay_minutes INTEGER NOT NULL DEFAULT 0,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS commission_rules(id INTEGER PRIMARY KEY AUTOINCREMENT,provider_id INTEGER NOT NULL REFERENCES providers(id),category TEXT,amount REAL NOT NULL DEFAULT 0,salesperson_percent REAL NOT NULL DEFAULT 0,active INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS idx_quotes_status ON quotes(status,created_at);
CREATE INDEX IF NOT EXISTS idx_commission_rules_provider ON commission_rules(provider_id,active);


CREATE TABLE IF NOT EXISTS customer_notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,title TEXT NOT NULL,message TEXT NOT NULL,link TEXT,is_read INTEGER NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS idx_customer_notifications_user ON customer_notifications(user_id,is_read,created_at);
CREATE TABLE IF NOT EXISTS documents(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,document_type TEXT NOT NULL,label TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'requested',storage_path TEXT,original_name TEXT,expires_at TEXT,reviewed_by INTEGER REFERENCES users(id),reviewed_at TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS idx_documents_user ON documents(user_id,status,created_at);
CREATE TABLE IF NOT EXISTS salesperson_profiles(user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,display_name TEXT,active INTEGER NOT NULL DEFAULT 1,commission_percent REAL NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS salesperson_commission_rates(
 user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 product_key TEXT NOT NULL,
 amount REAL NOT NULL DEFAULT 0,
 active INTEGER NOT NULL DEFAULT 1,
 updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(user_id,product_key)
);
CREATE INDEX IF NOT EXISTS idx_salesperson_commission_rates_user ON salesperson_commission_rates(user_id,active);


CREATE TABLE IF NOT EXISTS commission_ledger(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,sales_agent TEXT,provider_id INTEGER REFERENCES providers(id),category TEXT,gross_amount REAL NOT NULL DEFAULT 0,salesperson_amount REAL NOT NULL DEFAULT 0,company_amount REAL NOT NULL DEFAULT 0,status TEXT NOT NULL DEFAULT 'pending',earned_at TEXT,paid_at TEXT,payment_reference TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(order_id));
CREATE INDEX IF NOT EXISTS idx_commission_ledger_status ON commission_ledger(status,sales_agent);
CREATE TABLE IF NOT EXISTS bundle_carts(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,deal_ids_json TEXT NOT NULL DEFAULT '[]',status TEXT NOT NULL DEFAULT 'active',monthly_total REAL NOT NULL DEFAULT 0,credits_total REAL NOT NULL DEFAULT 0,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS idx_bundle_carts_user ON bundle_carts(user_id,status);


CREATE TABLE IF NOT EXISTS bundle_rules(id INTEGER PRIMARY KEY AUTOINCREMENT,provider_id INTEGER REFERENCES providers(id),name TEXT NOT NULL,required_categories TEXT NOT NULL DEFAULT '[]',discount_monthly REAL NOT NULL DEFAULT 0,bonus_credit REAL NOT NULL DEFAULT 0,waive_activation INTEGER NOT NULL DEFAULT 0,active INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS deployment_backups(id INTEGER PRIMARY KEY AUTOINCREMENT,filename TEXT NOT NULL,bytes INTEGER NOT NULL DEFAULT 0,sha256 TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS idx_bundle_rules_active ON bundle_rules(active,provider_id);

CREATE TABLE IF NOT EXISTS deal_categories(id INTEGER PRIMARY KEY AUTOINCREMENT,slug TEXT NOT NULL UNIQUE,name TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'active',display_order INTEGER NOT NULL DEFAULT 100,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
INSERT OR IGNORE INTO deal_categories(slug,name,status,display_order) VALUES ('internet','Internet','active',10),('mobility','Mobility','active',20),('tv','TV','active',30),('homephone','Home Phone','active',40),('security','Security','active',50),('streaming','Streaming','active',60),('devices','Devices','active',70);


CREATE TABLE IF NOT EXISTS products(id INTEGER PRIMARY KEY AUTOINCREMENT,provider_id INTEGER NOT NULL REFERENCES providers(id),category_slug TEXT NOT NULL,name TEXT NOT NULL,sku TEXT,status TEXT NOT NULL DEFAULT 'active',description TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS idx_products_catalog ON products(provider_id,category_slug,status);
CREATE TABLE IF NOT EXISTS deal_products(deal_id INTEGER PRIMARY KEY REFERENCES deals(id) ON DELETE CASCADE,product_id INTEGER REFERENCES products(id) ON DELETE SET NULL);
CREATE TABLE IF NOT EXISTS order_items(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,deal_id INTEGER REFERENCES deals(id),product_id INTEGER REFERENCES products(id),category TEXT NOT NULL,name TEXT NOT NULL,quantity INTEGER NOT NULL DEFAULT 1,monthly_price REAL NOT NULL DEFAULT 0,credit REAL NOT NULL DEFAULT 0,commission_amount REAL NOT NULL DEFAULT 0,snapshot_json TEXT NOT NULL DEFAULT '{}',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS mobility_lines(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,line_no INTEGER NOT NULL,phone_number TEXT,porting INTEGER NOT NULL DEFAULT 0,current_provider TEXT,plan_name TEXT,device_mode TEXT NOT NULL DEFAULT 'byod',imei TEXT,eid TEXT,monthly_price REAL NOT NULL DEFAULT 0,activation_fee REAL NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(order_id,line_no));
CREATE TABLE IF NOT EXISTS order_changes(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,change_type TEXT NOT NULL,summary TEXT NOT NULL,before_json TEXT,after_json TEXT,created_by INTEGER REFERENCES users(id),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS approval_requests(id INTEGER PRIMARY KEY AUTOINCREMENT,request_type TEXT NOT NULL,entity_type TEXT NOT NULL,entity_id INTEGER NOT NULL,requested_by INTEGER REFERENCES users(id),payload_json TEXT NOT NULL DEFAULT '{}',reason TEXT,status TEXT NOT NULL DEFAULT 'pending',reviewed_by INTEGER REFERENCES users(id),reviewed_at TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS idx_approval_pending ON approval_requests(status,request_type);
CREATE TABLE IF NOT EXISTS sales_targets(id INTEGER PRIMARY KEY AUTOINCREMENT,sales_agent TEXT NOT NULL,period TEXT NOT NULL,target_orders INTEGER NOT NULL DEFAULT 0,target_activations INTEGER NOT NULL DEFAULT 0,target_commission REAL NOT NULL DEFAULT 0,target_bundles INTEGER NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(sales_agent,period));
CREATE TABLE IF NOT EXISTS role_permissions(role TEXT NOT NULL,permission TEXT NOT NULL,allowed INTEGER NOT NULL DEFAULT 1,PRIMARY KEY(role,permission));
CREATE TABLE IF NOT EXISTS import_jobs(id INTEGER PRIMARY KEY AUTOINCREMENT,import_type TEXT NOT NULL,filename TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'preview',total_rows INTEGER NOT NULL DEFAULT 0,valid_rows INTEGER NOT NULL DEFAULT 0,error_rows INTEGER NOT NULL DEFAULT 0,report_json TEXT NOT NULL DEFAULT '{}',created_by INTEGER REFERENCES users(id),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS release_runs(id INTEGER PRIMARY KEY AUTOINCREMENT,commit_sha TEXT,status TEXT NOT NULL,backup_file TEXT,preflight_output TEXT,health_output TEXT,created_by INTEGER REFERENCES users(id),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,completed_at TEXT);


CREATE TABLE IF NOT EXISTS customer_profiles(user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,service_address TEXT,alternate_phone TEXT,preferred_contact TEXT NOT NULL DEFAULT 'email',updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS support_threads(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,subject TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'open',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS support_messages(id INTEGER PRIMARY KEY AUTOINCREMENT,thread_id INTEGER NOT NULL REFERENCES support_threads(id) ON DELETE CASCADE,sender_user_id INTEGER REFERENCES users(id),message TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS quote_responses(id INTEGER PRIMARY KEY AUTOINCREMENT,quote_id INTEGER NOT NULL REFERENCES quotes(id) ON DELETE CASCADE,response_type TEXT NOT NULL,message TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS appointment_requests(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,user_id INTEGER NOT NULL REFERENCES users(id),request_type TEXT NOT NULL DEFAULT 'reschedule',preferred_window TEXT,note TEXT,status TEXT NOT NULL DEFAULT 'pending',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS login_activity(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,ip_hash TEXT,user_agent TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);


CREATE TABLE IF NOT EXISTS service_contracts(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,order_item_id INTEGER REFERENCES order_items(id) ON DELETE SET NULL,activation_date TEXT,promo_ends_at TEXT,contract_ends_at TEXT,status TEXT NOT NULL DEFAULT 'active',renewal_stage TEXT NOT NULL DEFAULT 'none',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS idx_service_contracts_renewal ON service_contracts(status,promo_ends_at,contract_ends_at);
CREATE TABLE IF NOT EXISTS households(id INTEGER PRIMARY KEY AUTOINCREMENT,owner_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,name TEXT NOT NULL DEFAULT 'My household',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS household_members(id INTEGER PRIMARY KEY AUTOINCREMENT,household_id INTEGER NOT NULL REFERENCES households(id) ON DELETE CASCADE,name TEXT NOT NULL,email TEXT,phone TEXT,relationship TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS promotions(id INTEGER PRIMARY KEY AUTOINCREMENT,provider_id INTEGER REFERENCES providers(id),name TEXT NOT NULL,promo_type TEXT NOT NULL,amount REAL NOT NULL DEFAULT 0,duration_months INTEGER,min_monthly REAL NOT NULL DEFAULT 0,required_categories TEXT NOT NULL DEFAULT '[]',new_customer_only INTEGER NOT NULL DEFAULT 0,stackable INTEGER NOT NULL DEFAULT 1,starts_at TEXT,ends_at TEXT,status TEXT NOT NULL DEFAULT 'active',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS order_item_adjustments(id INTEGER PRIMARY KEY AUTOINCREMENT,order_item_id INTEGER NOT NULL REFERENCES order_items(id) ON DELETE CASCADE,promotion_id INTEGER REFERENCES promotions(id) ON DELETE SET NULL,kind TEXT NOT NULL,amount REAL NOT NULL DEFAULT 0,duration_months INTEGER,description TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS communication_templates(id INTEGER PRIMARY KEY AUTOINCREMENT,template_key TEXT NOT NULL UNIQUE,name TEXT NOT NULL,subject TEXT NOT NULL,message TEXT NOT NULL,channel TEXT NOT NULL DEFAULT 'email',active INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS notification_preferences(user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,order_email INTEGER NOT NULL DEFAULT 1,order_sms INTEGER NOT NULL DEFAULT 0,appointment_email INTEGER NOT NULL DEFAULT 1,appointment_sms INTEGER NOT NULL DEFAULT 0,promo_email INTEGER NOT NULL DEFAULT 1,promo_sms INTEGER NOT NULL DEFAULT 0,referral_email INTEGER NOT NULL DEFAULT 1,referral_sms INTEGER NOT NULL DEFAULT 0,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS cancellation_requests(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id),order_id INTEGER NOT NULL REFERENCES orders(id),reason TEXT NOT NULL,note TEXT,retention_status TEXT NOT NULL DEFAULT 'open',resolution TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,resolved_at TEXT);
CREATE TABLE IF NOT EXISTS renewal_opportunities(id INTEGER PRIMARY KEY AUTOINCREMENT,service_contract_id INTEGER NOT NULL REFERENCES service_contracts(id) ON DELETE CASCADE,user_id INTEGER NOT NULL REFERENCES users(id),order_id INTEGER REFERENCES orders(id),stage TEXT NOT NULL DEFAULT 'upcoming',due_at TEXT NOT NULL,assigned_to INTEGER REFERENCES users(id),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(service_contract_id,due_at));
CREATE TABLE IF NOT EXISTS customer_feedback(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id),order_id INTEGER REFERENCES orders(id),score INTEGER NOT NULL CHECK(score BETWEEN 0 AND 10),rating INTEGER CHECK(rating BETWEEN 1 AND 5),comment TEXT,status TEXT NOT NULL DEFAULT 'new',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS user_sessions(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,session_hash TEXT NOT NULL UNIQUE,user_agent TEXT,ip_hash TEXT,last_seen_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,revoked_at TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS idx_user_sessions_user ON user_sessions(user_id,revoked_at,last_seen_at);
CREATE TABLE IF NOT EXISTS order_status_events(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,status TEXT NOT NULL,message TEXT,actor_user_id INTEGER REFERENCES users(id),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
