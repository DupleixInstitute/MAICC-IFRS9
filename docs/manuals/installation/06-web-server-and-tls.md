## 6. Web Server and TLS

The application's document root is the `public` folder only. Never expose the repository root.

### 6.1 Nginx

Create `/etc/nginx/sites-available/maiic-ifrs9`:

```nginx
server {
    listen 80;
    server_name ifrs9.maiic.mw;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ifrs9.maiic.mw;
    root /var/www/maiic-ifrs9/public;
    index index.php;

    ssl_certificate     /etc/ssl/maiic/ifrs9.maiic.mw.crt;
    ssl_certificate_key /etc/ssl/maiic/ifrs9.maiic.mw.key;
    ssl_protocols TLSv1.2 TLSv1.3;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

Enable and reload:

```bash
sudo ln -s /etc/nginx/sites-available/maiic-ifrs9 /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 6.2 Apache

Enable the modules and create a virtual host:

```bash
sudo a2enmod rewrite ssl headers proxy_fcgi setenvif
sudo a2enconf php8.2-fpm
```

```apache
<VirtualHost *:443>
    ServerName ifrs9.maiic.mw
    DocumentRoot /var/www/maiic-ifrs9/public
    SSLEngine on
    SSLCertificateFile    /etc/ssl/maiic/ifrs9.maiic.mw.crt
    SSLCertificateKeyFile /etc/ssl/maiic/ifrs9.maiic.mw.key
    <Directory /var/www/maiic-ifrs9/public>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/maiic-error.log
    CustomLog ${APACHE_LOG_DIR}/maiic-access.log combined
</VirtualHost>
```

`public/.htaccess` already contains the Laravel rewrite rules and a commented http-to-https block that can be enabled instead of a separate port 80 host.

### 6.3 Certificate

Use a certificate issued for the hostname. Let's Encrypt with Certbot renews automatically:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d ifrs9.maiic.mw
```

For a commercial certificate, place the certificate chain and key at the paths in the server block and restrict the key to root (`chmod 600`). A self-signed certificate is acceptable only for an internal test host; browsers will warn.

### 6.4 Turning on the application HTTPS flags

The order matters. A certificate must be working before the flags change, otherwise the site becomes unreachable over http with no working https to fall back to.

1. Confirm `https://ifrs9.maiic.mw/login` loads with a valid padlock.
2. Set in `.env`: `APP_URL=https://ifrs9.maiic.mw`, `FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true`.
3. Run `php artisan config:cache` and sign in again; the session cookie is now secure-only.
4. After a day of clean operation set `HSTS_ENABLED=true` and cache the config again.

`docs/SSL_SETUP.md` in the repository records the same procedure for the XAMPP Apache case.

### 6.5 Verifying headers

```bash
curl -sI https://ifrs9.maiic.mw/login | grep -Ei 'strict-transport|x-frame|x-content-type|referrer-policy'
curl -sI http://ifrs9.maiic.mw/login | grep -i location
```

Expect the four security headers on the https response and a redirect to https on the http request.
