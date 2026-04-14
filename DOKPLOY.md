# Dokploy Deployment Guide (Hostinger VPS + GoDaddy Domain)

Dokploy runs the site as a Docker container and handles Traefik routing + automatic Let's Encrypt SSL. You don't need to touch nginx or certbot manually.

---

## Phase 1 — Point GoDaddy → VPS IP

1. **GoDaddy** → **My Products** → `minauras.com` → **DNS**.
2. Edit the **A record**:
   - Type: `A`, Name: `@`, Value: your VPS IP, TTL: `600`.
3. Add a **CNAME** for www:
   - Type: `CNAME`, Name: `www`, Value: `minauras.com`.
4. **Leave MX / SPF / DKIM / DMARC alone** — Google Workspace email keeps working.
5. Verify: `dig minauras.com +short` should return your VPS IP.

> ⚠️ Dokploy's Traefik needs DNS to resolve correctly **before** deploying, otherwise Let's Encrypt will fail to issue the cert.

---

## Phase 2 — Get your secrets ready

### Cloudflare Turnstile keys
1. [dash.cloudflare.com](https://dash.cloudflare.com) → **Turnstile** → **Add Site**.
2. Domain: `minauras.com`, widget type: **Managed**.
3. Copy **Site Key** (public) and **Secret Key** (private).

### Gmail App Password
1. Log in to Google as `hello@minauras.com` → enable **2-Step Verification**.
2. [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords) → create "Minauras Website" → copy the 16-char password.

### Hardcode the Turnstile site key in index.html
The **site key** is public and baked into the HTML. Open [index.html](index.html), find `YOUR_TURNSTILE_SITE_KEY`, replace with your real site key, and commit.

---

## Phase 3 — Push to a Git repo

Dokploy deploys from git. Create a repo (GitHub / GitLab / Gitea — Dokploy supports all):

```bash
cd "/Users/jtworld/Development/AI Apps/minaurasweb"
git init
git add .
git commit -m "Initial Minauras site with Dokploy Docker setup"
# create repo on github.com first, then:
git remote add origin git@github.com:yourusername/minauras-web.git
git branch -M main
git push -u origin main
```

Files pushed:
```
Dockerfile
.dockerignore
.htaccess
index.html
send-mail.php
composer.json
DOKPLOY.md / DEPLOY.md (optional)
```

**Do NOT push** `config.php` (it's in `.dockerignore` and `.gitignore` anyway — secrets come from Dokploy env vars).

---

## Phase 4 — Create the app in Dokploy

1. Log in to your Dokploy dashboard (usually `https://<vps-ip>:3000` or your Dokploy subdomain).
2. **Projects** → **Create Project** → name it `minauras`.
3. Inside the project: **Create Service** → **Application**.
4. Configure:

### Source
- **Provider:** GitHub (connect if first time) / GitLab / Git (public URL).
- **Repository:** `yourusername/minauras-web`
- **Branch:** `main`
- **Build Path:** `/` (repo root)

### Build
- **Build Type:** **Dockerfile**
- **Dockerfile Path:** `./Dockerfile`
- **Docker Context:** `.`

### Environment Variables
Click **Environment** tab → add:

| Key | Value |
|---|---|
| `SMTP_PASSWORD` | your 16-char Gmail App Password |
| `TURNSTILE_SECRET` | your Cloudflare Turnstile secret key |

Mark both as **secret** (Dokploy masks them in logs).

### Domains
Click **Domains** tab → **Add Domain**:
- **Host:** `minauras.com`
- **Path:** `/`
- **Container Port:** `80`
- **HTTPS:** ✅ **Enabled**
- **Certificate Provider:** **Let's Encrypt**

Add a second domain for `www.minauras.com` the same way.

### Deploy
Click **Deploy**. Dokploy will:
1. Clone the repo.
2. Build the Docker image (takes ~1–2 min — composer install + Apache setup).
3. Start the container.
4. Traefik auto-routes `minauras.com` → container port 80.
5. Let's Encrypt issues an SSL cert (~30 seconds after DNS resolves).

Watch the **Deployments** tab logs — you should see:
```
Successfully built <image-id>
Container started
```

---

## Phase 5 — Test

Visit `https://minauras.com`:
- Padlock icon ✅
- Site loads
- Submit the form → check `hello@minauras.com` inbox within a minute.

### Sanity checks
| URL | Expected |
|---|---|
| `https://minauras.com` | Site loads |
| `https://minauras.com/config.php` | 403 Forbidden (file doesn't exist in the image anyway) |
| `https://minauras.com/vendor/` | 403 Forbidden |
| `https://www.minauras.com` | Site loads |

---

## Debugging

### Dokploy logs
Your service → **Logs** tab → live Apache + PHP output.

```
# Common errors
PHPMailer SMTP Error: Could not authenticate  → wrong SMTP_PASSWORD
Verification failed                             → wrong TURNSTILE_SECRET or widget site key mismatch
500 with "Server misconfigured"                → env vars not set / not restarted after setting them
```

After changing env vars, click **Redeploy** (env vars are injected at container start).

### Shell into the container
Dokploy service → **Terminal** tab → drops you in `/var/www/html/`. Useful for:
```bash
ls -la vendor/phpmailer/    # confirm PHPMailer installed
php -r "var_dump(getenv('SMTP_PASSWORD'));"   # confirm env var is set (masks value if you log it)
```

### SMTP outbound blocked?
Some VPS providers block port 587 on fresh accounts. Test from the container terminal:
```bash
apt update && apt install -y netcat-openbsd
nc -vz smtp.gmail.com 587
```
If it hangs → open a Hostinger ticket: *"Please unblock outbound SMTP on my VPS."*

---

## Updates

Every `git push` to `main` can trigger an auto-deploy. Enable it:
Service → **General** → **Auto Deploy** → **On** → set webhook on your Git provider (Dokploy gives you the URL).

---

## Summary diagram

```
GoDaddy DNS (A record)
         │
         ▼
  [Your VPS IP]
         │
         ▼
   Dokploy Traefik  ◄── auto SSL via Let's Encrypt
         │
         ▼
  Docker container (php:8.3-apache)
   • index.html (static)
   • send-mail.php ──► Gmail SMTP ──► hello@minauras.com
   • Turnstile verification via Cloudflare API
```

## Checklist

- [ ] GoDaddy A record points to VPS IP
- [ ] DNS propagated (`dig minauras.com +short`)
- [ ] Turnstile site key pasted into `index.html`, committed to repo
- [ ] Repo pushed to GitHub/GitLab
- [ ] Dokploy application created with Dockerfile build
- [ ] `SMTP_PASSWORD` + `TURNSTILE_SECRET` env vars set (as secrets)
- [ ] Domain `minauras.com` + `www.minauras.com` added with Let's Encrypt
- [ ] Deploy succeeds, logs show Apache started
- [ ] Form submission lands in inbox
