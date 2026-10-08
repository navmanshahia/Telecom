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
