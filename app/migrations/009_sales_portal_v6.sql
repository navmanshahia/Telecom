-- SecureLink Sales Portal V6
CREATE TABLE IF NOT EXISTS sales_customer_owners(
 user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
 salesperson_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_sales_customer_owners_salesperson ON sales_customer_owners(salesperson_user_id,user_id);

CREATE TABLE IF NOT EXISTS customer_identity_records(
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 id_type TEXT NOT NULL,
 id_number_encrypted TEXT,
 id_last4 TEXT,
 document_id INTEGER REFERENCES documents(id) ON DELETE SET NULL,
 status TEXT NOT NULL DEFAULT 'received',
 created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_customer_identity_user ON customer_identity_records(user_id,status);

CREATE TABLE IF NOT EXISTS quote_templates(
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 name TEXT NOT NULL,
 description TEXT,
 deal_ids_json TEXT NOT NULL DEFAULT '[]',
 notes TEXT,
 active INTEGER NOT NULL DEFAULT 1,
 created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS application_checklists(
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
 check_key TEXT NOT NULL,
 label TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'pending',
 notes TEXT,
 completed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
 completed_at TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(user_id,order_id,check_key)
);

CREATE TABLE IF NOT EXISTS sales_scripts(
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 script_key TEXT NOT NULL UNIQUE,
 title TEXT NOT NULL,
 category TEXT NOT NULL DEFAULT 'general',
 script TEXT NOT NULL,
 active INTEGER NOT NULL DEFAULT 1,
 display_order INTEGER NOT NULL DEFAULT 100,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sales_bonus_rules(
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 name TEXT NOT NULL,
 metric TEXT NOT NULL DEFAULT 'activations',
 threshold_value REAL NOT NULL DEFAULT 0,
 bonus_amount REAL NOT NULL DEFAULT 0,
 active INTEGER NOT NULL DEFAULT 1,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO sale_sources(name,active) VALUES ('Salesperson Generated',1);

INSERT OR IGNORE INTO sales_scripts(script_key,title,category,script,active,display_order) VALUES
('internet_open','Internet opener','Internet','Hi {{first_name}}, I’m with SecureLink. I can compare the current Internet options and show you the monthly price, credits and term side by side so you can decide without guessing.',1,10),
('mobility_open','Mobility opener','Mobility','Hi {{first_name}}, I can check current TELUS and Koodo mobility options based on how much data you use and whether you need Canada-US coverage. I’ll show you the total monthly cost before anything is submitted.',1,20),
('bundle_close','Bundle close','Bundle','If you’re already considering more than one service, I can price the bundle together and show you both the customer savings and the exact services included before you decide.',1,30),
('price_objection','Price objection','Objection','That makes sense. Instead of only looking at the advertised monthly price, I can compare the regular price, promotional period, credits and any one-time fees so you can see the real value over the term.',1,40),
('think_about_it','Need to think','Objection','Absolutely. I can send you a written quote so you have the price, services and credits in one place. You can review it without committing, and I’ll follow up when it works for you.',1,50),
('follow_up','Follow-up','Follow-up','Hi {{first_name}}, just checking in on the SecureLink options we discussed. If your needs or budget changed, I can update the comparison before you make a decision.',1,60);
