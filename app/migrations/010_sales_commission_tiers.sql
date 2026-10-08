-- SecureLink automatic salesperson commission tiers
-- Standard: first 10 activated sales each calendar month.
-- Premier: sale #11 onward. Runtime schema hardening adds ledger metadata columns.

CREATE TABLE IF NOT EXISTS sales_commission_tiers(
 tier_key TEXT PRIMARY KEY,
 name TEXT NOT NULL,
 min_prior_activations INTEGER NOT NULL DEFAULT 0,
 display_order INTEGER NOT NULL DEFAULT 100,
 active INTEGER NOT NULL DEFAULT 1,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sales_commission_tier_rates(
 tier_key TEXT NOT NULL REFERENCES sales_commission_tiers(tier_key) ON DELETE CASCADE,
 product_key TEXT NOT NULL,
 amount REAL NOT NULL DEFAULT 0,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(tier_key,product_key)
);

INSERT OR IGNORE INTO sales_commission_tiers(tier_key,name,min_prior_activations,display_order,active) VALUES
('standard','Standard',0,10,1),
('premier','Premier',10,20,1);

INSERT OR IGNORE INTO sales_commission_tier_rates(tier_key,product_key,amount) VALUES
('standard','internet',70),
('standard','security',70),
('standard','tv',70),
('standard','homephone',12),
('standard','telus_mobility',60),
('standard','koodo_mobility',35),
('premier','internet',80),
('premier','security',80),
('premier','tv',80),
('premier','homephone',15),
('premier','telus_mobility',70),
('premier','koodo_mobility',40);
