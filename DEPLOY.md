# Hostinger Deployment Guide

## Files created
```
index.html              ← the website
send-mail.php           ← form handler (Gmail SMTP)
composer.json           ← PHPMailer dependency
config.example.php      ← template — copy to config.php on server
.htaccess               ← protects config.php & vendor/ from web access
```

---

## Step 1a — Set up Cloudflare Turnstile (spam protection)

Turnstile is Cloudflare's free CAPTCHA — invisible to most real users, blocks bots.

1. Sign up / log in at [dash.cloudflare.com](https://dash.cloudflare.com) (free account, no domain needed).
2. Left sidebar → **Turnstile** → **Add Site**.
3. Fill in:
   - **Site name:** `Minauras`
   - **Domains:** `minauras.com` (add `localhost` too if you want to test locally)
   - **Widget type:** **Managed** (recommended)
4. Click **Create**. You'll get two keys:
   - **Site Key** (public) — goes in `index.html`
   - **Secret Key** (private) — goes in `config.php`
5. In [index.html](index.html), find `YOUR_TURNSTILE_SITE_KEY` and replace with the **Site Key**.
6. Copy the **Secret Key** — you'll paste it into `config.php` in Step 3.

> Unlimited free usage. No puzzles for ~99% of real visitors — just a quick checkbox or nothing at all.

---

## Step 1b — Generate a Gmail App Password

App Passwords are required because Gmail blocks regular-password SMTP logins.

1. Sign in to Google as **hello@minauras.com**.
2. Enable **2-Step Verification** at https://myaccount.google.com/security (required before App Passwords are available).
3. Go to https://myaccount.google.com/apppasswords.
4. Create an app password named **"Minauras Website"**.
5. Copy the 16-character password (no spaces).

> If you don't see "App Passwords", your Workspace admin may have disabled them. Turn on "Allow users to manage their access to less secure apps" in Google Admin → Security → Less secure apps, OR use OAuth2 instead.

---

## Step 2 — Install PHPMailer locally, then upload

Hostinger shared hosting usually doesn't have composer available in the file manager, so install locally and upload the `vendor/` folder.

```bash
cd "/Users/jtworld/Development/AI Apps/minaurasweb"
composer install --no-dev --optimize-autoloader
```

This creates a `vendor/` directory with PHPMailer inside.

> No composer installed? Run `brew install composer` first (on macOS).

---

## Step 3 — Create config.php

```bash
cp config.example.php config.php
```

Edit `config.php` and paste BOTH secrets:

```php
define('SMTP_PASSWORD',    'abcdefghijklmnop');                              // Gmail App Password
define('TURNSTILE_SECRET', '0x4AAAAAAxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');        // from Cloudflare
```

**Do NOT commit config.php to git.**

---

## Step 4 — Upload to Hostinger

Upload the following to your domain's `public_html/` directory (via hPanel File Manager or FTP):

```
public_html/
├── index.html
├── send-mail.php
├── config.php              ← (the real one, not the example)
├── composer.json
├── .htaccess
└── vendor/                 ← entire folder from composer install
    └── phpmailer/...
```

**Do NOT upload** `config.example.php` or `DEPLOY.md` — they are not needed on the server.

---

## Step 5 — Test

1. Visit `https://minauras.com` (or whatever your domain is).
2. Fill out the contact form and submit.
3. Within ~5 seconds, you should see "Thank you! Your message has been sent..."
4. Check the **hello@minauras.com** inbox for the message.

### If it fails

- Check Hostinger error logs in hPanel → **Advanced → Error Logs**.
- Common issues:
  - **"SMTP Error: Could not authenticate"** → wrong App Password in `config.php`.
  - **"Connection refused"** → Hostinger blocks outbound port 587 on very old plans. Switch to port 465 with `ENCRYPTION_SMTPS` in `send-mail.php`.
  - **500 error** → `vendor/` folder didn't upload; re-check.

### Test from CLI (optional)
```bash
curl -X POST https://minauras.com/send-mail.php \
  -F "firstName=Test" -F "lastName=User" \
  -F "email=you@example.com" -F "message=Hello from curl"
```

---

## Security notes

- `.htaccess` blocks web access to `config.php` and `vendor/` — verify by visiting `https://minauras.com/config.php` (should 403).
- The form includes two layers of spam protection:
  1. **Honeypot** field (`website`) — traps naive bots silently.
  2. **Cloudflare Turnstile** — blocks automated submissions via browser fingerprinting + behavior analysis. Verified server-side so forged tokens get rejected.
- Rate limiting is not included — add if abuse becomes an issue.

---

## Alternative: Hostinger SMTP (instead of Gmail)

If you'd rather use Hostinger's own email (still delivers to hello@minauras.com if you set up the mailbox there):

In `send-mail.php`, swap the SMTP block to:
```php
$mail->Host = 'smtp.hostinger.com';
$mail->Port = 465;
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
$mail->Username = 'hello@minauras.com';
$mail->Password = 'your-hostinger-mailbox-password'; // not an App Password
```

But since you're on Google Workspace, **stick with Gmail SMTP** — mail lands natively in your Workspace inbox with no forwarding delays.
