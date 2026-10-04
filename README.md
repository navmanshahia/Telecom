# SecureLink Telecom Portal

Private, approval-gated telecom deal portal and manual order CRM for PHP 8.2+ and SQLite.

## Features
- Customer registration, login, pending approval and admin approval
- Dynamic providers with active / paused / hidden visibility
- Dynamic Internet and Mobility deals
- Referral and sales-source attribution
- Manual order-processing workflow and appointment confirmation
- Mobility intake: contact details, porting details, IMEI/EID for eSIM, protected identity field
- Internet intake: contact details, full service address/basement details and protected ID field
- Immutable deal snapshot on application submission
- Masked sensitive identity values in admin
- Audit logging, CSRF protection, secure sessions and role separation

## Server requirements
PHP 8.2+, PDO SQLite, Sodium, HTTPS.

## Install
1. Point the web root/document root at `public/`.
2. Copy `.env.example` to `.env` outside the public web root and set a strong `APP_KEY`.
3. Ensure the PHP process can write to `storage/`.
4. Visit the app's `/install` route once and create the owner account (for example, if deployed at `/Telecom-main/public/`, open `/Telecom-main/public/install`).
5. Delete/disable the installer after setup.
6. Keep HTTPS enabled in production.

Do not commit real customer data, uploaded identity documents, production SQLite databases, or production secrets to GitHub.

## Privacy
Government identifiers are sensitive. Only collect identifier types that your authorized provider workflow and applicable law permit. SIN and health-card handling should receive separate privacy/legal review before production use.


## CRM Operations Upgrade
The admin now includes a sales command centre, Lead → Interested → Follow-up → Application → Order → Activated pipeline, lost-sale reasons, SLA queue, internal tasks, internal/customer updates, communications log, referral payout queue, campaign tracking URLs for QR use, global search, funnel/source analytics and safe CSV exports.

### Existing installations
Back up the database, then run `app/migrations/002_crm_upgrade.sql` once before using the new CRM screens. Test the migration on a copy first. Email records are queued/logged in the communications table; connect a transactional mail provider/SMTP worker before expecting external delivery.


## Production experience upgrade

The main branch now includes:
- Side-by-side offer comparison with listed rewards, effective monthly cost and estimated term cost
- Premium neutral SecureLink storefront with provider-aware TELUS and Rogers presentation
- Rich deal cards with regular price, current price, term, speed/data, rewards, expiry and fine print
- Four-step guided application flow with validation, safe progress persistence and review-before-submit
- Admin deal/pricing editor, featured merchandising, drag ordering, offer-expiry visibility and provider conversion analytics
- Mobile dock navigation, empty/error states, reduced-motion support, SEO metadata, cache-busted assets and success toasts
- Hardened security headers, strict sessions and server-side request throttling
- GitHub Actions PHP 8.2 syntax linting on main

### Existing production databases

The runtime keeps a `schema_migrations` ledger and performs the additive v007 hardening migration safely: email verification metadata, known activation/installation fees, commission tracking, and one-time security-token tables. The earlier merchandising/rate-limit migration remains available as `app/migrations/006_merchandising_and_rate_limits.sql`.

Before a production change, use **Admin → System → Download database backup**. The backup handler checkpoints SQLite WAL first and requires an authenticated admin session plus CSRF token.

### Email-dependent account features

Registration now creates an email-verification token and password recovery uses single-use, hashed, expiring reset tokens. Security messages are queued in `communications`; connect a transactional SMTP/mail worker for external delivery. Keep `REQUIRE_EMAIL_VERIFICATION=false` until delivery is working, then set it to `true` to require verification at sign-in.

### Sales operations additions

- Provider metadata/status/order editor
- Deal activation and installation fee fields included in effective-cost comparison
- Appointment and provider-reference tracking
- Salesperson and commission amount/status tracking
- Provider conversion, projected activated MRR and commission pipeline analytics
- System health view with schema version, database size, queued email and verification counts


## Growth & operations V2

The main branch now also includes:

- Command Centre V2 with 7-day order pulse, provider conversion scoreboard, action queue, commissions, SLA risk, follow-ups, notification load and offer-expiry risk
- Customer-facing deal search, provider filtering, price/reward/term filters and sorting
- Provider-specific TELUS purple/green and Rogers red brand systems while keeping SecureLink clearly independent
- Offer version history with restore support and audit attribution
- Queued customer notifications for order changes, account verification and password recovery
- Smart 24-hour order/lead follow-ups that automatically cancel when the underlying order/lead has progressed
- Admin notification centre with scheduling, cancellation, retry and queue processing
- PWA manifest, install experience, static-only service worker and privacy-safe offline fallback
- Help-me-choose conversion form, trust disclosures and FAQ content
- Deployment preflight, normalized line endings, expanded CI and cPanel live-checkout safeguards

### Notification worker

The safe default is:

`MAIL_TRANSPORT=log`

After outbound cPanel mail is configured and verified, set:

`MAIL_TRANSPORT=mail`

Then schedule:

```bash
cd "$HOME/public_html/Telecom" && php app/notify.php 50
```

The worker processes due messages, skips delivery when mail transport is disabled, and cancels stale scheduled follow-ups when the related order or lead has already progressed.

### Deployment reliability

The current cPanel repository is itself the live checkout. The deployment file deliberately does not copy the repository into itself. Use `php scripts/preflight.php` for a production readiness check and see `DEPLOYMENT.md` for the clean-reset workflow and the future atomic-deployment path.
