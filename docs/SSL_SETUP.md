# SSL / HTTPS Setup — MAIIC IFRS 9 Platform

This platform ships with **application-level HTTPS hardening** that is switched
**off by default** so local XAMPP development over `http://localhost` keeps
working. Turning HTTPS on is a two-part job:

1. **Install a TLS certificate on the web server** (this document).
2. **Flip the application flags** so Laravel generates `https://` URLs, marks
   session cookies secure, redirects http → https, and sends HSTS.

> ⚠️ A certificate must be installed **before** enabling the flags, otherwise
> the site becomes unreachable over http with no working https to fall back to.

---

## What is already wired in the app

| Concern | Where | Default |
|---|---|---|
| Force `https://` on all generated URLs | `config/security.php` → `FORCE_HTTPS` | `false` |
| Secure session cookie | `config/session.php` → `SESSION_SECURE_COOKIE` | unset |
| HSTS header (only on secure requests) | `config/security.php` → `HSTS_ENABLED` | `false` |
| Baseline headers (nosniff, X-Frame-Options, Referrer-Policy) | `app/Http/Middleware/SecurityHeaders.php` | **always on** |
| http → https redirect | `public/.htaccess` (commented block) | commented out |

The baseline headers are safe and already active. Everything TLS-specific is
gated behind env flags.

---

## Step 1 — Install a certificate

### Option A: Production — a real certificate (recommended)

Use a CA-issued certificate for the platform's domain (e.g. `ifrs9.maiic.mw`):

- **Let's Encrypt (free, auto-renewing)** via Certbot, or
- A commercial certificate from the organisation's provider.

Place the certificate and key on the server and note their paths.

### Option B: Local / staging — self-signed certificate (testing only)

Generate a self-signed cert (browsers will warn; fine for internal testing):

```bash
openssl req -x509 -nodes -days 825 -newkey rsa:2048 \
  -keyout C:/xampp/apache/conf/ssl.key/maiic.key \
  -out    C:/xampp/apache/conf/ssl.crt/maiic.crt \
  -subj "//CN=localhost"
```

## Step 2 — Configure Apache (XAMPP) for SSL

1. Enable the SSL module and the SSL vhost include in
   `C:/xampp/apache/conf/httpd.conf` (uncomment):

   ```apache
   LoadModule ssl_module modules/mod_ssl.so
   Include conf/extra/httpd-ssl.conf
   ```

2. Add / edit a vhost in `C:/xampp/apache/conf/extra/httpd-ssl.conf`:

   ```apache
   <VirtualHost *:443>
       ServerName ifrs9.maiic.mw
       DocumentRoot "C:/xampp/htdocs/MAICC-IFRS9/public"

       SSLEngine on
       SSLCertificateFile    "conf/ssl.crt/maiic.crt"
       SSLCertificateKeyFile "conf/ssl.key/maiic.key"

       <Directory "C:/xampp/htdocs/MAICC-IFRS9/public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

3. Restart Apache from the XAMPP Control Panel and confirm
   `https://<host>/` loads.

## Step 3 — Turn on the application flags

Once https serves correctly, edit `.env`:

```dotenv
FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
HSTS_ENABLED=true          # only after https is confirmed end-to-end
APP_URL=https://ifrs9.maiic.mw
```

Then uncomment the redirect block in `public/.htaccess`:

```apache
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

Finally, refresh cached config:

```bash
php artisan config:clear
php artisan optimize
```

## Behind a reverse proxy / load balancer that terminates TLS

If TLS is terminated upstream (e.g. Cloudflare, an nginx proxy), set
`FORCE_HTTPS=true` and make sure the proxy's IP is listed in
`app/Http/Middleware/TrustProxies.php` so Laravel honours the
`X-Forwarded-Proto` header.

## Rollback

Set `FORCE_HTTPS=false`, `HSTS_ENABLED=false`, `SESSION_SECURE_COOKIE=false`,
re-comment the `.htaccess` redirect, run `php artisan config:clear`.

> Note: browsers cache HSTS for `max-age`. Only enable `HSTS_ENABLED` when you
> are confident https will stay available, or clients may be unable to reach an
> http-only fallback until the header expires.
