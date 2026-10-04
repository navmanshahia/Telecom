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
