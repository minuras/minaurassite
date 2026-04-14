<?php
/**
 * Copy this file to config.php on the server and fill in the real values.
 * NEVER commit config.php — it contains secrets.
 */

// ---- Gmail SMTP (Google Workspace App Password) ----
// Generate at https://myaccount.google.com/apppasswords (requires 2-Step Verification)
define('SMTP_PASSWORD', 'xxxxxxxxxxxxxxxx');

// ---- Cloudflare Turnstile secret key ----
// Get at https://dash.cloudflare.com/?to=/:account/turnstile (free)
define('TURNSTILE_SECRET', '0x0000000000000000000000000000000000000000');
