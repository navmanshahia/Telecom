PRAGMA foreign_keys=ON;
CREATE TABLE IF NOT EXISTS users(
 id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, phone TEXT,
 password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'customer', status TEXT NOT NULL DEFAULT 'pending',
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, approved_at TEXT
);
CREATE TABLE IF NOT EXISTS providers(
 id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, description TEXT, website TEXT,
 status TEXT NOT NULL DEFAULT 'active', display_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS deals(
 id INTEGER PRIMARY KEY AUTOINCREMENT, provider_id INTEGER NOT NULL REFERENCES providers(id),
 category TEXT NOT NULL, name TEXT NOT NULL, description TEXT, monthly_price REAL NOT NULL DEFAULT 0,
 regular_price REAL, bill_credit REAL NOT NULL DEFAULT 0, referral_reward REAL NOT NULL DEFAULT 0,
 other_reward REAL NOT NULL DEFAULT 0, term_months INTEGER, speed_data TEXT, fine_print TEXT,
 status TEXT NOT NULL DEFAULT 'draft', starts_at TEXT, expires_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS sale_sources(
 id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, active INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS orders(
 id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT NOT NULL UNIQUE, user_id INTEGER NOT NULL REFERENCES users(id),
 deal_id INTEGER NOT NULL REFERENCES deals(id), provider_id INTEGER NOT NULL REFERENCES providers(id),
 category TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'submitted', deal_snapshot TEXT NOT NULL,
 source_id INTEGER REFERENCES sale_sources(id), referred_by TEXT, sales_agent TEXT,
 contact_email TEXT NOT NULL, contact_phone TEXT NOT NULL,
 service_address TEXT, basement_details TEXT, porting INTEGER NOT NULL DEFAULT 0,
 current_phone TEXT, current_provider TEXT, current_account_number TEXT, imei TEXT, eid TEXT,
 id_type TEXT, id_value_encrypted TEXT, provider_reference TEXT, appointment_at TEXT, admin_notes TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS referrals(
 id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER REFERENCES orders(id), referred_by TEXT NOT NULL,
 referred_customer TEXT NOT NULL, reward_amount REAL NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'pending',
 paid_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS audit_logs(
 id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER REFERENCES users(id), action TEXT NOT NULL,
 entity TEXT, entity_id INTEGER, metadata TEXT, ip TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
INSERT OR IGNORE INTO sale_sources(name) VALUES ('Referral'),('Website'),('Door-to-door'),('Existing Customer'),('Repeat Customer'),('Social Media'),('QR Campaign'),('Phone'),('Technician'),('Other');
