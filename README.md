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
