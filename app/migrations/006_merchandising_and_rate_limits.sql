-- 006_merchandising_and_rate_limits.sql
-- Safe/idempotent production support for deal merchandising and request throttling.
CREATE TABLE IF NOT EXISTS deal_merchandising(
 deal_id INTEGER PRIMARY KEY REFERENCES deals(id) ON DELETE CASCADE,
 featured INTEGER NOT NULL DEFAULT 0,
 display_order INTEGER NOT NULL DEFAULT 0,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS rate_limits(
 scope TEXT NOT NULL,
 key_hash TEXT NOT NULL,
 hits INTEGER NOT NULL DEFAULT 0,
 window_started INTEGER NOT NULL,
 PRIMARY KEY(scope,key_hash)
);
