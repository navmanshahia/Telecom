PRAGMA foreign_keys=ON;
ALTER TABLE orders ADD COLUMN activated_at TEXT;
CREATE TABLE IF NOT EXISTS referral_bonuses(
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 provider_id INTEGER NOT NULL REFERENCES providers(id),
 referred_by TEXT NOT NULL,
 milestone INTEGER NOT NULL,
 amount REAL NOT NULL,
 status TEXT NOT NULL DEFAULT 'eligible',
 eligible_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 paid_at TEXT,
 payment_reference TEXT,
 UNIQUE(provider_id,referred_by,milestone)
);
CREATE INDEX IF NOT EXISTS idx_orders_status_created ON orders(status,created_at);
CREATE INDEX IF NOT EXISTS idx_orders_campaign ON orders(campaign_id);
CREATE INDEX IF NOT EXISTS idx_referrals_status ON referrals(status);
CREATE INDEX IF NOT EXISTS idx_communications_order_visibility ON communications(order_id,visibility);
