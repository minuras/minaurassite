# Dokploy Deployment Guide

Stack: **static HTML + nginx:alpine** — form submissions go directly to Web3Forms, which delivers to `hello@minauras.com`. No backend secrets, no SMTP, no env vars.

---

## Phase 1 — Point GoDaddy → VPS IP

1. **GoDaddy** → **My Products** → `minauras.com` → **DNS**.
2. Edit the **A record**: Type `A`, Name `@`, Value = your VPS IP, TTL `600`.
3. Add a **CNAME**: Type `CNAME`, Name `www`, Value `minauras.com`.
4. **Leave MX / SPF / DKIM / DMARC alone** — Google Workspace email keeps working.
5. Verify: `dig minauras.com +short` → should return your VPS IP.

> ⚠️ DNS must resolve **before** deploying, or Let's Encrypt can't issue the cert.

---

## Phase 2 — Deploy via Dokploy

1. Dokploy dashboard → **Projects** → **Create Project** → `minauras`.
2. Inside: **Create Service** → **Application**.

### Source
- Provider: **GitHub**
- Repo: `minuras/minaurassite`
- Branch: `main`

### Build
- Build Type: **Dockerfile**
- Dockerfile Path: `./Dockerfile`

### Environment Variables
**None needed.** The Web3Forms access key is already in `index.html` (it's a public client-side token, safe to commit).

### Domains
- Add `minauras.com` → container port `80` → HTTPS ✅ → Let's Encrypt
- Add `www.minauras.com` → same config

### Deploy
Click **Deploy**. Takes ~30 seconds (nginx:alpine is tiny).

---

## Phase 3 — Test

1. Visit `https://minauras.com` → padlock, site loads.
2. Fill out the contact form → submit.
3. Check `hello@minauras.com` inbox — email from Web3Forms within seconds.

---

## How the form works

```
User submits form
      │
      ▼
 POST to api.web3forms.com
 (with access_key, form data)
      │
      ▼
 Web3Forms validates + sends email
      │
      ▼
 hello@minauras.com
```

**Spam protection:**
- `botcheck` honeypot field (hidden checkbox — bots tick everything, Web3Forms drops those)
- Web3Forms' own spam-filter scoring
- Can add hCaptcha / reCAPTCHA in Web3Forms dashboard if spam becomes an issue

**Managing replies:** just reply from Gmail as normal. Web3Forms includes the sender's email in the `reply-to` header automatically.

---

## Updating the site

Every `git push` to `main` can auto-deploy:
- Dokploy service → **General** → **Auto Deploy** → **On**
- Dokploy gives you a webhook URL → add it in GitHub → **Settings** → **Webhooks**

---

## Checklist

- [ ] GoDaddy A record → VPS IP
- [ ] DNS propagated (`dig minauras.com +short`)
- [ ] Dokploy application created with Dockerfile build
- [ ] Domains `minauras.com` + `www.minauras.com` with Let's Encrypt HTTPS
- [ ] Deploy succeeds
- [ ] Test form submission arrives at `hello@minauras.com`
