-- 009_growth_operations.sql
-- Offer history, queued notifications and conversion analytics.
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

INSERT OR IGNORE INTO schema_migrations(version) VALUES('009_growth_operations');
