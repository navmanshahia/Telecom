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

The runtime creates the optional merchandising/rate-limit tables safely with `CREATE TABLE IF NOT EXISTS`. For an explicit migration record, run `app/migrations/006_merchandising_and_rate_limits.sql` once against an existing database.

### Email-dependent account features

Password-reset email and email verification should only be enabled after a transactional email provider/SMTP worker is connected. The application does not fake delivery of security-critical email.
