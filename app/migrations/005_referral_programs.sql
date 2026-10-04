-- 005_referral_programs.sql
CREATE TABLE IF NOT EXISTS referral_programs(
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
);
INSERT INTO referral_programs(provider_id,name,active,reward_amount,promo_reward_amount,promo_ends_at,retention_days,bonus_5_amount,bonus_10_amount,terms)
SELECT p.id,'TELUS Referral Program',1,50,100,'2026-10-31 23:59:59',90,200,250,'Referral is successful only after the referred customer keeps eligible TELUS services active for at least 90 days. $100 per successful referral through October 31, 2026; $50 afterward. Bonus: $200 after 5 successful referrals and $250 after 10 successful referrals.'
FROM providers p WHERE p.name='TELUS'
AND NOT EXISTS(SELECT 1 FROM referral_programs rp WHERE rp.provider_id=p.id AND rp.name='TELUS Referral Program');