# SecureLink cPanel deployment

## Current live-checkout mode

The cPanel Git repository is currently the live application checkout at:

`~/public_html/Telecom`

The public entry point is:

`~/public_html/Telecom/public/index.php`

The repository therefore **must not copy files back into itself** during deployment. The root `.cpanel.yml` intentionally performs only runtime-directory preparation and validation.

## Reliable update workflow

Do not edit tracked PHP/CSS/JS files directly in cPanel File Manager. Make source changes in GitHub, then update the checkout.

If cPanel reports local changes that block a pull and GitHub is the source of truth:

```bash
cd ~/public_html/Telecom
git fetch origin
git reset --hard origin/main
git clean -fd
git status
```

Expected final status:

`nothing to commit, working tree clean`

Runtime data remains safe because `.env`, SQLite databases and logs are ignored by Git.

## Preflight

From the repository root:

```bash
php scripts/preflight.php
```

This checks required files, PHP extensions, storage permissions, production environment basics and the PWA manifest.

## Notification worker

The application queues email notifications in SQLite. Safe default:

`MAIL_TRANSPORT=log`

After cPanel mail delivery is configured and tested, set:

`MAIL_TRANSPORT=mail`

Then add a cPanel cron job, for example every five minutes:

```bash
cd "$HOME/public_html/Telecom" && php app/notify.php 50
```

Do not enable mail transport until the sending domain/from address is configured.

## Future atomic deployment

For zero-touch releases, move the cPanel Git repository outside `public_html` and deploy into a separate live directory. That cPanel repository-path change cannot be performed by repository code itself and must be changed once in cPanel.
